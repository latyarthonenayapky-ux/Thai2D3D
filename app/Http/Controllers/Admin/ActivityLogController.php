<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeViewer($request);
        $filters = $this->filters($request);
        $viewer = $request->user();

        $entries = $this->query($filters, $viewer)->paginate(50)->withQueryString();

        return view('admin.activity-log.index', [
            'entries' => $entries,
            'accounts' => $this->businessUsers($viewer)
                ->orderBy('name')
                ->get(['id', 'name', 'role']),
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeViewer($request);
        $filters = $this->filters($request);
        $entries = $this->query($filters, $request->user());

        $filename = sprintf('wya-activity-log-%s.csv', now('Asia/Yangon')->format('Ymd-His'));

        return response()->streamDownload(function () use ($entries): void {
            $output = fopen('php://output', 'w');

            // BOM so Excel opens the Burmese-capable UTF-8 CSV correctly.
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['When', 'Account', 'Role', 'Action', 'Subject', 'IP address', 'Detail']);

            $entries->chunk(500, function ($batch) use ($output): void {
                foreach ($batch as $entry) {
                    fputcsv($output, [
                        $entry->created_at?->format('Y-m-d H:i:s'),
                        $this->csvText($entry->user?->name ?? ''),
                        $this->csvText($entry->user?->role ?? ''),
                        $this->csvText($entry->action),
                        $this->csvText(trim(($entry->subject_type ?? '').' '.($entry->subject_id ?? ''), ' ')),
                        $this->csvText($entry->ip ?? ''),
                        $this->csvText($this->flatten($entry->meta ?? [])),
                    ]);
                }
            });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The log is the business owner's audit trail: an Admin sees their own
     * actions and those of the accounts under them, and nobody else opens the
     * page at all.
     */
    private function authorizeViewer(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403);
    }

    private function businessUsers(User $viewer): \Illuminate\Database\Eloquent\Builder
    {
        return User::query()->where(fn ($query) => $query
            ->where('admin_id', $viewer->id)
            ->orWhere('id', $viewer->id));
    }

    /**
     * @return array{user_id?: int|null, action?: string|null}
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:80'],
        ]);
    }

    private function query(array $filters, User $viewer): \Illuminate\Database\Eloquent\Builder
    {
        return ActivityLog::query()
            ->with('user')
            ->whereIn('user_id', $this->businessUsers($viewer)->select('id'))
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', 'like', $action.'%'))
            ->latest();
    }

    private function flatten(array $meta): string
    {
        $parts = [];
        foreach ($meta as $key => $value) {
            $rendered = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
            $parts[] = $key.'='.mb_substr($rendered, 0, 200);
        }

        return implode(' · ', $parts);
    }

    private function csvText(string $value): string
    {
        return str_starts_with($value, '=') ? "'".$value : $value;
    }
}
