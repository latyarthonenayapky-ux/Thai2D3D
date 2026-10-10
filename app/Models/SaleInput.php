<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleInput extends Model
{
    protected $fillable = [
        'agent_session_id',
        'operator_id',
        'client_uuid',
        'original_input',
        'normalized_input',
        'input_type',
        'code',
        'number_argument',
        'number_count',
        'amount',
        'total_amount',
        'status',
        'reject_reason',
    ];

    protected $casts = [
        'number_count' => 'integer',
        'amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function agentSession(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'agent_session_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(SaleDetail::class, 'sale_input_id');
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
