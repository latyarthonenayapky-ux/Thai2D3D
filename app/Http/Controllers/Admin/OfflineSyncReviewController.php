<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OfflineSyncReview;
use App\Services\OfflineSaleSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfflineSyncReviewController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('admin.offline-reviews.index', [
            'reviews' => OfflineSyncReview::query()
                ->with(['operator', 'agent', 'round'])
                ->whereHas('round', fn ($query) => $query->where('admin_id', $request->user()->id))
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->latest()
                ->paginate(30),
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $query = OfflineSyncReview::query()
            ->where('status', 'pending')
            ->whereHas('round', fn ($round) => $round->where('admin_id', $request->user()->id));

        return response()->json([
            'count' => (clone $query)->count(),
            'latest' => (clone $query)->latest()->value('id'),
        ]);
    }

    public function update(
        Request $request,
        OfflineSyncReview $offlineSyncReview,
        OfflineSaleSyncService $sync
    ): RedirectResponse {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless((int) $offlineSyncReview->round()->value('admin_id') === (int) $request->user()->id, 404);
        $validated = $request->validate(['action' => ['required', 'in:approve,reject']]);

        if ($validated['action'] === 'approve') {
            $sync->approve($offlineSyncReview, $request->user());
            $message = 'Offline sale approved with an audited override. Accepted numbers now affect limits and settlement.';
        } else {
            $sync->reject($offlineSyncReview, $request->user());
            $message = 'Offline sale rejected and retained in the review history.';
        }

        return back()->with('status', $message);
    }
}
