<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Agent;
use App\Models\AgentLedgerEntry;
use App\Services\AgentLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The Admin's cash book for each Agent: what was deposited, what was handed out
 * as float, what came back. Agents belong to one business, so every lookup is
 * pinned to the signed-in Admin's id and a foreign Agent simply 404s.
 */
class AgentLedgerController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        $filters = $request->validate(['q' => ['nullable', 'string', 'max:60']]);
        $search = trim($filters['q'] ?? '');

        $agents = Agent::query()
            ->where('admin_id', $request->user()->id)
            ->when($search !== '', fn ($query) => $this->search($query, $search))
            ->orderBy('agent_code')
            ->paginate(25)
            ->withQueryString();

        $ledger = app(AgentLedger::class);

        return view('admin.ledger.index', [
            'agents' => $agents,
            'balances' => $ledger->balances($agents->getCollection()),
            'portfolio' => $ledger->portfolio($request->user()->id),
            'search' => $search,
        ]);
    }

    public function show(Request $request, Agent $agent, AgentLedger $ledger): View
    {
        $this->authorizeAgent($request, $agent);

        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return view('admin.ledger.show', [
            'agent' => $agent,
            'statement' => $ledger->statement($agent, $filters['from'] ?? null, $filters['to'] ?? null),
            'filters' => $filters,
            'today' => now('Asia/Yangon')->toDateString(),
        ]);
    }

    public function store(Request $request, Agent $agent, AgentLedger $ledger): RedirectResponse
    {
        $this->authorizeAgent($request, $agent);

        $validated = $request->validate([
            'type' => ['required', Rule::in(AgentLedgerEntry::TYPES)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'occurs_on' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = $ledger->record($agent, $request->user(), $validated);

        // The route-level entry that RecordAdminActivity writes never sees the
        // request body, so a cash book needs the amount and kind recorded here.
        ActivityLog::record('agent_ledger.recorded', $entry, [
            'agent_id' => $agent->id,
            'type' => $entry->type,
            'amount' => (float) $entry->amount,
            'occurs_on' => $entry->occurs_on->toDateString(),
        ]);

        return redirect()->route('admin.ledger.show', $agent)
            ->with('status', __('app.ledger.recorded', [
                'type' => __('app.ledger.type_'.$validated['type']),
                'amount' => number_format((float) $validated['amount'], 2),
                'agent' => $agent->agent_code,
            ]));
    }

    public function destroy(Request $request, Agent $agent, AgentLedgerEntry $entry): RedirectResponse
    {
        $this->authorizeAgent($request, $agent);
        abort_unless(
            (int) $entry->agent_id === (int) $agent->id
            && (int) $entry->admin_id === (int) $request->user()->id,
            404
        );

        $detail = [
            'agent_id' => $agent->id,
            'entry_id' => $entry->id,
            'type' => $entry->type,
            'amount' => (float) $entry->amount,
        ];

        $entry->delete();

        ActivityLog::record('agent_ledger.deleted', $agent, $detail);

        return back()->with('status', __('app.ledger.deleted'));
    }

    private function authorizeAgent(Request $request, Agent $agent): void
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless((int) $agent->admin_id === (int) $request->user()->id, 404);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Agent>  $query
     */
    private function search($query, string $search): void
    {
        // A search term may contain the LIKE wildcards, so neutralise them.
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        $query->where(function ($scope) use ($like): void {
            $scope->where('agent_name', 'like', $like)
                ->orWhere('agent_code', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }
}
