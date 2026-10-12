<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataBackupController extends Controller
{
    public function __construct(private readonly DatabaseBackupService $backups) {}

    public function index(): View
    {
        return view('admin.data-backup.index', [
            'backups' => $this->backups->all(),
            'directory' => $this->backups->directory(),
            'driver' => $this->backups->driver(),
            'database' => (string) config('database.connections.'.config('database.default').'.database'),
        ]);
    }

    public function store(): RedirectResponse
    {
        try {
            $name = $this->backups->create();
        } catch (RuntimeException $exception) {
            return back()->withErrors(['backup' => $exception->getMessage()]);
        }

        ActivityLog::record('data_backup.created', null, ['file' => $name]);

        return back()->with('status', __('app.backup.created', ['file' => $name]));
    }

    public function download(string $file): StreamedResponse|RedirectResponse
    {
        try {
            $path = $this->backups->resolve($file);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['backup' => $exception->getMessage()]);
        }

        return response()->streamDownload(
            function () use ($path): void {
                readfile($path);
            },
            basename($path),
            ['Content-Type' => 'application/sql']
        );
    }

    public function destroy(Request $request, string $file): RedirectResponse
    {
        try {
            $this->backups->delete($file);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['backup' => $exception->getMessage()]);
        }

        ActivityLog::record('data_backup.deleted', null, ['file' => $file]);

        return back()->with('status', __('app.backup.deleted', ['file' => $file]));
    }

    public function restore(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'in:on'],
            'upload' => ['nullable', 'file', 'max:204800'],
            'stored' => ['nullable', 'string'],
        ], [], ['confirm' => 'restore confirmation', 'upload' => 'backup file', 'stored' => 'stored backup']);

        $upload = $request->file('upload');
        $stored = $request->input('stored');

        if (! $upload && ! $stored) {
            return back()->withErrors(['upload' => __('app.backup.file_required')]);
        }

        try {
            $source = $stored
                ? $this->backups->resolve($stored)
                : $upload->getRealPath();

            if (! $this->backups->acceptsUploadedFile($source)) {
                throw new RuntimeException(__('app.backup.not_wya'));
            }

            $safety = $this->backups->restore($source);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['restore' => $exception->getMessage()]);
        }

        ActivityLog::record('data_backup.restored', null, [
            'from' => $stored ?? $upload->getClientOriginalName(),
            'safety_backup' => $safety,
        ]);

        $request->session()->flush();

        return redirect()
            ->route('login')
            ->with('status', __('app.backup.restored', ['file' => $safety]));
    }
}
