<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThreeDigitSaleInput extends Model
{
    protected $fillable = [
        'agent_session_id',
        'three_digit_draw_id',
        'agent_id',
        'draw_agent_setting_id',
        'operator_id',
        'client_uuid',
        'original_input',
        'normalized_input',
        'input_type',
        'code',
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
        return $this->belongsTo(AgentSession::class);
    }

    public function draw(): BelongsTo
    {
        return $this->belongsTo(ThreeDigitDraw::class, 'three_digit_draw_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    public function drawAgentSetting(): BelongsTo
    {
        return $this->belongsTo(ThreeDigitDrawAgentSetting::class, 'draw_agent_setting_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ThreeDigitSaleDetail::class);
    }
}
