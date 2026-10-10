<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Round extends Model
{
    protected $casts = [
        'round_date' => 'date',
        'number_limit' => 'integer',
        'number_limit_3d' => 'integer',
        'number_limits' => 'array',
        'opened_at' => 'datetime',
        'manual_reopen' => 'boolean',
        'manually_closed' => 'boolean',
    ];

    protected $fillable = [
        'round_date',
        'admin_id',
        'round_no',
        'start_time',
        'close_time',
        'number_limit',
        'number_limit_3d',
        'number_limits',
        'status',
        'opened_at',
        'manual_reopen',
        'manually_closed',
    ];

    public function sessions()
    {
        return $this->hasMany(AgentSession::class,
            'round_id');
    }

    public function hotNumbers(): HasMany
    {
        return $this->hasMany(HotNumber::class);
    }

    public function threeDigitHotNumbers(): HasMany
    {
        return $this->hasMany(ThreeDigitHotNumber::class);
    }

    public function agentRoundSettings(): HasMany
    {
        return $this->hasMany(AgentRoundSetting::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(RoundResult::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
