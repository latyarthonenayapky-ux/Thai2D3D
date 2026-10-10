<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Round;
use App\Models\RoundResult;
use App\Models\RoundResultChange;
use App\Services\RoundSettlementCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoundResultController extends Controller
{
    public function index(Request $request): View
    {
        $request->session()->put('draw_mode', '2d');
        abort_unless($request->user()->isAdmin(), 403);

        return view('admin.rounds.index', [
            'rounds' => Round::query()
                ->with('result')
                ->where('admin_id', $request->user()->id)
                ->where('status', 'closed')
                ->orderByDesc('round_date')
                ->orderByDesc('round_no')
                ->paginate(25),
        ]);
    }

    public function show(Request $request, Round $round, RoundSettlementCalculator $calculator): View
    {
        $request->session()->put('draw_mode', '2d');
        $this->authorizeRound($request, $round);
        $round->load(['result.changes.changedBy', 'agentRoundSettings.operator', 'agentRoundSettings.agent']);

        return view('admin.rounds.settlement', [
            'round' => $round,
            'settlement' => $calculator->calculate($round),
        ]);
    }

    public function update(Request $request, Round $round): RedirectResponse
    {
        $request->session()->put('draw_mode', '2d');
        $this->authorizeRound($request, $round);
        abort_unless($round->status === 'closed', 409, 'Round results can be entered only after the Round is closed.');

        $validated = $request->validate([
            'result_2d' => ['nullable', 'regex:/\A[0-9]{2}\z/D'],
        ]);

        DB::transaction(function () use ($request, $round, $validated): void {
            $lockedRound = Round::query()->whereKey($round->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedRound->status === 'closed', 409, 'Round results can be entered only after the Round is closed.');

            $result = RoundResult::query()->firstOrCreate(
                ['round_id' => $round->id],
                [
                    'admin_id' => $request->user()->id,
                    'result_2d' => null,
                    'result_3d' => null,
                    'updated_by' => $request->user()->id,
                ]
            );
            $result = RoundResult::query()->whereKey($result->id)->lockForUpdate()->firstOrFail();

            foreach (['result_2d'] as $field) {
                if (! array_key_exists($field, $validated)) {
                    continue;
                }

                $newValue = $validated[$field] === '' ? null : ($validated[$field] ?? null);
                $oldValue = $result->{$field};

                if ($newValue === $oldValue) {
                    continue;
                }

                RoundResultChange::query()->create([
                    'round_result_id' => $result->id,
                    'changed_by' => $request->user()->id,
                    'field' => $field,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                ]);

                $result->{$field} = $newValue;
            }

            $result->updated_by = $request->user()->id;
            $result->save();
        });

        return redirect()->route('admin.rounds.settlement', $round)
            ->with('status', 'Round result saved. Settlement totals were recalculated from accepted sales.');
    }

    private function authorizeRound(Request $request, Round $round): void
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless((int) $round->admin_id === (int) $request->user()->id, 404);
    }
}
