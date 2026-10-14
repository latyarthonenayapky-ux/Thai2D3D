<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\BusinessDataExport;
use App\Services\BusinessDataImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use RuntimeException;

/**
 * Moving a business between machines: export from the desktop, import on the VPS.
 *
 * The import always runs twice - once as a dry run whose report is shown to the
 * Admin, then again for real after they confirm - because BusinessDataImport
 * rolls a dry run back inside a transaction, so the preview is the same walk
 * rather than a second implementation that could disagree with it.
 */
class BusinessMoveController extends Controller
{
    private const IMPORT_PATTERN = '^wya-import-[0-9]{8}-[0-9]{6}-[a-f0-9]{6}\.json$';

    public function __construct(
        private readonly BusinessDataExport $export,
        private readonly BusinessDataImport $import,
    ) {}

    public function download(Request $request): Response|RedirectResponse
    {
        try {
            $json = $this->export->toJson((int) $request->user()->id);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['move' => $exception->getMessage()]);
        }

        $name = $this->export->filename();

        ActivityLog::record('business_export', null, ['file' => $name, 'bytes' => strlen($json)]);

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $request->validate([
            'bundle' => ['required', 'file', 'max:204800', 'extensions:json'],
        ], [], ['bundle' => 'bundle']);

        $upload = $request->file('bundle');

        $token = null;

        try {
            $bundle = $this->import->read($upload->getRealPath());
            // The bundle is kept so the confirmation click re-runs it without
            // asking for the file again; only the preview walk is thrown away.
            $token = $this->store($upload);
            $report = $this->import->run($bundle, (int) $request->user()->id, true);
        } catch (RuntimeException $exception) {
            if ($token !== null) {
                @unlink($this->directory().'/'.$token);
            }

            return back()->withErrors(['move' => $exception->getMessage()]);
        }

        $request->session()->put('business_import_token', $token);

        return view('admin.business-move.preview', [
            'token' => $token,
            'report' => $report,
        ]);
    }

    public function apply(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'regex:/'.self::IMPORT_PATTERN.'/'],
            'confirm' => ['required', 'in:on'],
        ], [], ['token' => 'bundle', 'confirm' => 'import confirmation']);

        $token = $request->input('token');
        $session = $request->session()->get('business_import_token');

        if (! is_string($session) || ! hash_equals($session, $token)) {
            return back()->withErrors(['move' => __('app.move.stale')]);
        }

        $path = $this->directory().'/'.$token;

        try {
            $bundle = $this->import->read($path);
            $report = $this->import->run($bundle, (int) $request->user()->id, false);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.data-backup.index')
                ->withErrors(['move' => $exception->getMessage()]);
        }

        if ($report['conflicts'] !== []) {
            return redirect()
                ->route('admin.data-backup.index')
                ->withErrors(['move' => __('app.move.conflicts', [
                    'count' => count($report['conflicts']),
                    'first' => $report['conflicts'][0]['table'].' '.$report['conflicts'][0]['key'],
                ])]);
        }

        @unlink($path);
        $request->session()->forget('business_import_token');

        ActivityLog::record('business_import', null, [
            'file' => $token,
            'from' => $report['business']['email'] ?? '?',
            'rows' => $report['totals']['new'],
            'matched' => $report['totals']['matched'],
            'skipped' => $report['totals']['skipped'],
            'orphan' => $report['totals']['orphan'],
        ]);

        return redirect()
            ->route('admin.data-backup.index')
            ->with('status', __('app.move.imported', [
                'rows' => number_format($report['totals']['new']),
                'here' => number_format($report['totals']['matched']),
            ]));
    }

    private function directory(): string
    {
        $directory = storage_path('app/imports');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory;
    }

    private function store(UploadedFile $upload): string
    {
        $directory = $this->directory();

        foreach (glob($directory.'/wya-import-*.json') ?: [] as $stale) {
            if (filemtime($stale) < now()->subDay()->getTimestamp()) {
                @unlink($stale);
            }
        }

        $token = 'wya-import-'.now()->format('Ymd-His').'-'.substr(bin2hex(random_bytes(3)), 0, 6).'.json';

        // Written from the decoded string rather than moved from /tmp, because
        // PHP removes the upload's temporary file at the end of this request and
        // move_uploaded_file() refuses anything that is not a real upload.
        if (file_put_contents($directory.'/'.$token, $upload->get()) === false) {
            throw new RuntimeException(__('app.move.read_failed'));
        }

        return $token;
    }
}
