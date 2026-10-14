<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentLedgerEntry extends Model
{
    public const TYPES = ['deposit', 'credit', 'repayment', 'withdrawal'];

    /**
     * Types that put cash into the office. The other two take cash out, so an
     * Agent's balance goes negative when the office has handed over more than it
     * has been given.
     */
    public const MONEY_IN = ['deposit', 'repayment'];

    protected $fillable = [
        'admin_id',
        'agent_id',
        'type',
        'amount',
        'occurs_on',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurs_on' => 'date:Y-m-d',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isMoneyIn(): bool
    {
        return in_array($this->type, self::MONEY_IN, true);
    }

    /**
     * The amount with the sign the running balance needs.
     */
    public function signedAmount(): float
    {
        return $this->isMoneyIn() ? (float) $this->amount : -(float) $this->amount;
    }

    public function scopeForAgent(Builder $query, int $agentId): Builder
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeOwnedBy(Builder $query, int $adminId): Builder
    {
        return $query->where('admin_id', $adminId);
    }

    /**
     * @param  string|null  $from  inclusive Y-m-d date
     * @param  string|null  $to  inclusive Y-m-d date
     */
    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from !== null && $from !== '', fn (Builder $q) => $q->whereDate('occurs_on', '>=', $from))
            ->when($to !== null && $to !== '', fn (Builder $q) => $q->whereDate('occurs_on', '<=', $to));
    }

    /**
     * The order a cash book is written in, which is also the order a running
     * balance has to be accumulated in.
     */
    public function scopeInBookOrder(Builder $query): Builder
    {
        return $query->orderBy('occurs_on')->orderBy('id');
    }
}
