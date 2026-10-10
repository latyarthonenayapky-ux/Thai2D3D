<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentRoundSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'round_id',
        'operator_id',
        'amount_limit',
        'amount_limit_3d',
    ];

    protected $casts = [
        'amount_limit' => 'decimal:2',
        'amount_limit_3d' => 'decimal:2',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
