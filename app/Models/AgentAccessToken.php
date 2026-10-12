<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentAccessToken extends Model
{
    protected $fillable = [
        'agent_id',
        'token_hash',
        'device_name',
        'expires_at',
        'revoked_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime:Asia/Yangon',
            'last_used_at' => 'datetime:Asia/Yangon',
            'revoked_at' => 'datetime:Asia/Yangon',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function scopeUsable($query): void
    {
        $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
