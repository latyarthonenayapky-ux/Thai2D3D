<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThreeDigitDrawAgentSetting extends Model
{
    protected $fillable = [
        'three_digit_draw_id',
        'agent_id',
        'handler_id',
        'amount_limit',
    ];

    protected $casts = ['amount_limit' => 'decimal:2'];

    public function draw(): BelongsTo
    {
        return $this->belongsTo(ThreeDigitDraw::class, 'three_digit_draw_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_id');
    }

    public function saleInputs(): HasMany
    {
        return $this->hasMany(ThreeDigitSaleInput::class, 'draw_agent_setting_id');
    }
}
