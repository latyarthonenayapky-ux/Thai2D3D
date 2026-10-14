<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentLedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The cash book between the office and its Agents.
 *
 * A balance is never stored: it is always the sum of the entries, so deleting a
 * mistyped entry cannot leave a stale figure behind. Positive means the office
 * is holding the Agent's money, negative means the Agent has taken more out than
 * has been paid in.
 */
class AgentLedger
{
    public function record(Agent $agent, User $recorder, array $data): AgentLedgerEntry
    {
        return AgentLedgerEntry::query()->create([
            'admin_id' => $agent->admin_id,
            'agent_id' => $agent->id,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'occurs_on' => $data['occurs_on'],
            'note' => filled($data['note'] ?? null) ? trim((string) $data['note']) : null,
            'recorded_by' => $recorder->id,
        ]);
    }

    /**
     * @return array{opening: float, rows: Collection, totals: array{in: float, out: float, net: float, closing: float}}
     */
    public function statement(Agent $agent, ?string $from, ?string $to): array
    {
        $opening = $from === null || $from === ''
            ? 0.0
            : $this->signedTotal(
                AgentLedgerEntry::query()
                    ->ownedBy($agent->admin_id)
                    ->forAgent($agent->id)
                    ->whereDate('occurs_on', '<', $from)
            );

        $running = $opening;
        $rows = AgentLedgerEntry::query()
            ->ownedBy($agent->admin_id)
            ->forAgent($agent->id)
            ->betweenDates($from, $to)
            ->inBookOrder()
            ->get()
            ->map(function (AgentLedgerEntry $entry) use (&$running): AgentLedgerEntry {
                $running += $entry->signedAmount();
                $entry->setAttribute('running_balance', round($running, 2));

                return $entry;
            });

        $in = (float) $rows->filter(fn (AgentLedgerEntry $e) => $e->isMoneyIn())->sum('amount');
        $out = (float) $rows->filter(fn (AgentLedgerEntry $e) => ! $e->isMoneyIn())->sum('amount');

        return [
            'opening' => round($opening, 2),
            'rows' => $rows,
            'totals' => [
                'in' => round($in, 2),
                'out' => round($out, 2),
                'net' => round($in - $out, 2),
                'closing' => round($running, 2),
            ],
        ];
    }

    /**
     * Outstanding balance of one Agent, ignoring any date filter.
     */
    public function balance(Agent $agent): float
    {
        return round($this->signedTotal(
            AgentLedgerEntry::query()->ownedBy($agent->admin_id)->forAgent($agent->id)
        ), 2);
    }

    /**
     * Balances for a whole page of Agents in one query, keyed by agent id.
     *
     * @return array<int, array{in: float, out: float, balance: float, entries: int}>
     */
    public function balances(Collection $agents): array
    {
        $ids = $agents->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $rows = AgentLedgerEntry::query()
            ->whereIn('agent_id', $ids)
            ->selectRaw('agent_id')
            ->selectRaw('COALESCE(SUM(CASE WHEN type IN ('.$this->moneyInList().') THEN amount ELSE 0 END), 0) as money_in')
            ->selectRaw('COALESCE(SUM(CASE WHEN type IN ('.$this->moneyInList().') THEN 0 ELSE amount END), 0) as money_out')
            ->selectRaw('COUNT(*) as entries')
            ->groupBy('agent_id')
            ->get();

        $balances = [];

        foreach ($rows as $row) {
            $in = (float) $row->money_in;
            $out = (float) $row->money_out;

            $balances[(int) $row->agent_id] = [
                'in' => round($in, 2),
                'out' => round($out, 2),
                'balance' => round($in - $out, 2),
                'entries' => (int) $row->entries,
            ];
        }

        return $balances;
    }

    /**
     * What the whole Agent book of one business looks like, so the Admin can see
     * how much cash is being held before opening a single page.
     *
     * @return array{in: float, out: float, net: float, agents: int}
     */
    public function portfolio(int $adminId): array
    {
        $row = AgentLedgerEntry::query()
            ->ownedBy($adminId)
            ->selectRaw('COALESCE(SUM(CASE WHEN type IN ('.$this->moneyInList().') THEN amount ELSE 0 END), 0) as money_in')
            ->selectRaw('COALESCE(SUM(CASE WHEN type IN ('.$this->moneyInList().') THEN 0 ELSE amount END), 0) as money_out')
            ->selectRaw('COUNT(DISTINCT agent_id) as agents')
            ->first();

        $in = (float) ($row->money_in ?? 0);
        $out = (float) ($row->money_out ?? 0);

        return [
            'in' => round($in, 2),
            'out' => round($out, 2),
            'net' => round($in - $out, 2),
            'agents' => (int) ($row->agents ?? 0),
        ];
    }

    private function signedTotal(Builder $query): float
    {
        return (float) $query->sum(DB::raw($this->signedCase()));
    }

    /**
     * The SQL form of AgentLedgerEntry::signedAmount(). Both read the same
     * constant list so a new type cannot be added in one place only. The list is
     * built from class constants, never from request data.
     */
    private function signedCase(): string
    {
        return 'CASE WHEN type IN ('.$this->moneyInList().') THEN amount ELSE -amount END';
    }

    private function moneyInList(): string
    {
        return "'".implode("', '", AgentLedgerEntry::MONEY_IN)."'";
    }
}
