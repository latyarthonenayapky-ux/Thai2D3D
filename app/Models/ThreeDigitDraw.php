<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ThreeDigitDraw extends Model
{
    protected $fillable = [
        'admin_id',
        'draw_date',
        'open_time',
        'result_time',
        'number_limit',
        'status',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'draw_date' => 'date',
        'number_limit' => 'integer',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function agentSettings(): HasMany
    {
        return $this->hasMany(ThreeDigitDrawAgentSetting::class);
    }

    public function hotNumbers(): HasMany
    {
        return $this->hasMany(ThreeDigitDrawHotNumber::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(ThreeDigitDrawResult::class);
    }

    public function saleInputs(): HasMany
    {
        return $this->hasMany(ThreeDigitSaleInput::class);
    }
}
