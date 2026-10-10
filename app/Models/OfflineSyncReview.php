<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineSyncReview extends Model
{
    protected $fillable = [
        'client_uuid',
        'sale_type',
        'operator_id',
        'agent_session_id',
        'round_id',
        'agent_id',
        'original_input',
        'recorded_at',
        'status',
        'conflict_reason',
        'reviewed_by',
        'reviewed_at',
        'sale_input_id',
        'three_digit_sale_input_id',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function agentSession(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class);
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
