<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class AgentSession extends Model
{
    /**
     * Mass assignable fields
     */
    protected $fillable = [
        'round_id',
        'agent_id',
        'operator_id',
        'session_code',
        'status',
        'opened_at',
        'closed_at',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /**
     * Agent relationship
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    /**
     * Round relationship
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class, 'round_id');
    }

    /**
     * Operator relationship
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function saleInputs(): HasMany
    {
        return $this->hasMany(SaleInput::class, 'agent_session_id');
    }

    public function threeDigitSaleInputs(): HasMany
    {
        return $this->hasMany(ThreeDigitSaleInput::class, 'agent_session_id');
    }

    /**
     * Generate session code
     *
     * Example:
     * 260911-WYA01-01
     */
    public static function generateCode(
        Agent $agent,
        Round $round
    ): string {
        $date = Carbon::parse($round->round_date)->format('ymd');

        $agentCode = strtoupper(
            trim($agent->agent_code)
        );

        $roundNo = str_pad(
            (string) $round->round_no,
            2,
            '0',
            STR_PAD_LEFT
        );

        return "{$date}-{$agentCode}-{$roundNo}";
    }

    /**
     * Open a new Agent Session
     *
     * An Agent can have one active session per Round. The first Admin or
     * Operator to open it claims that Agent for the Round.
     */
    public static function openSession(
        Agent $agent,
        Round $round,
        int $operatorId,
        ?Carbon $openedAt = null
    ): self {
        return DB::transaction(function () use ($agent, $round, $operatorId, $openedAt): self {
            $lockedRound = Round::query()->lockForUpdate()->findOrFail($round->id);
            if ($lockedRound->status !== 'open') {
                throw new \RuntimeException(
                    "Round {$lockedRound->round_no} is not open."
                );
            }

            $lockedAgent = Agent::query()->lockForUpdate()->findOrFail($agent->id);
            $handler = User::query()->whereKey($operatorId)->lockForUpdate()->firstOrFail();
            $belongsToBusiness = $handler->isAdmin()
                ? (int) $handler->id === (int) $lockedRound->admin_id
                : $handler->isOperator() && (int) $handler->admin_id === (int) $lockedRound->admin_id;

            if (
                ! $handler->status
                || ! $belongsToBusiness
                || (int) $lockedAgent->admin_id !== (int) $lockedRound->admin_id
            ) {
                throw new \RuntimeException('This Agent and account do not belong to the Round business.');
            }

            $roundAssignment = AgentRoundSetting::query()
                ->where('agent_id', $lockedAgent->id)
                ->where('round_id', $lockedRound->id)
                ->lockForUpdate()
                ->first();

            if ($roundAssignment?->operator_id !== null && $roundAssignment->operator_id !== $handler->id) {
                throw new \RuntimeException(
                    "Agent {$lockedAgent->agent_code} has already been claimed by another user for Round {$lockedRound->round_no}."
                );
            }

            if ($roundAssignment === null) {
                $roundAssignment = AgentRoundSetting::query()->create([
                    'agent_id' => $lockedAgent->id,
                    'round_id' => $lockedRound->id,
                    'operator_id' => $handler->id,
                ]);
            } elseif ($roundAssignment->operator_id === null) {
                $roundAssignment->update(['operator_id' => $handler->id]);
            }

            $existingSession = self::query()
                ->where('agent_id', $lockedAgent->id)
                ->where('round_id', $lockedRound->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existingSession) {
                throw new \RuntimeException(
                    "Agent {$lockedAgent->agent_code} already has an active session in Round {$lockedRound->round_no}."
                );
            }

            $closedSession = self::query()
                ->where('agent_id', $lockedAgent->id)
                ->where('round_id', $lockedRound->id)
                ->where('status', 'closed')
                ->lockForUpdate()
                ->first();

            if ($closedSession) {
                $closedSession->forceFill([
                    'operator_id' => $handler->id,
                    'status' => 'open',
                    'opened_at' => $openedAt ?? now(),
                    'closed_at' => null,
                ])->save();

                return $closedSession;
            }

            return self::create([
                'round_id' => $lockedRound->id,
                'agent_id' => $lockedAgent->id,
                'operator_id' => $handler->id,
                'session_code' => self::generateCode($lockedAgent, $lockedRound),
                'status' => 'open',
                'opened_at' => $openedAt ?? now(),
                'closed_at' => null,
            ]);
        });
    }

    /**
     * Check session is open
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check session is closed
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Close session
     *
     * Close Time can be manually supplied.
     */
    public function close(?Carbon $closedAt = null): bool
    {
        $this->status = 'closed';

        $this->closed_at = $closedAt ?? now();

        return $this->save();
    }

    /**
     * Re-open a closed session
     */
    public function reopen(): bool
    {
        $this->status = 'open';

        $this->closed_at = null;

        return $this->save();
    }

    /**
     * Scope: Open sessions
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope: Closed sessions
     */
    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    /**
     * Scope: Sessions belonging to an operator
     */
    public function scopeForOperator(
        $query,
        int $operatorId
    ) {
        return $query->where(
            'operator_id',
            $operatorId
        );
    }

    /**
     * Scope: Sessions belonging to an Agent
     */
    public function scopeForAgent(
        $query,
        int $agentId
    ) {
        return $query->where(
            'agent_id',
            $agentId
        );
    }

    /**
     * Scope: Sessions belonging to a Round
     */
    public function scopeForRound(
        $query,
        int $roundId
    ) {
        return $query->where(
            'round_id',
            $roundId
        );
    }
}
