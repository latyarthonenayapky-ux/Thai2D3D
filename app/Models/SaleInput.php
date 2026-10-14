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
        'source',
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
        'voided_at',
        'voided_by',
        'void_reason',
        'pre_void_status',
        'pre_void_detail_statuses',
    ];

    protected $casts = [
        'number_count' => 'integer',
        'amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'voided_at' => 'datetime',
        'pre_void_detail_statuses' => 'array',
    ];

    public function agentSession(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'agent_session_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
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

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }
}
