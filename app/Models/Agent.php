<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'agent_code',
        'agent_name',
        'mobile_login_code',
        'mobile_password',
        'phone',
        'commission_2d_percent',
        'commission_3d_percent',
        'status',
    ];

    protected $hidden = [
        'mobile_password',
    ];

    protected $casts = [
        'commission_2d_percent' => 'decimal:2',
        'commission_3d_percent' => 'decimal:2',
        'mobile_password' => 'hashed',
    ];

    public function accessTokens(): HasMany
    {
        return $this->hasMany(AgentAccessToken::class, 'agent_id');
    }

    public function hasMobileAccess(): bool
    {
        return $this->mobile_login_code !== null && $this->mobile_password !== null;
    }

    public function sessions()
    {
        return $this->hasMany(
            AgentSession::class,
            'agent_id'
        );
    }

    public function roundSettings(): HasMany
    {
        return $this->hasMany(AgentRoundSetting::class);
    }

    public function threeDigitDrawSettings(): HasMany
    {
        return $this->hasMany(ThreeDigitDrawAgentSetting::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
