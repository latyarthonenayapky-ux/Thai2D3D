<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundNumberAmountLimit extends Model
{
    protected $fillable = [
        'round_id',
        'number',
        'amount_limit',
    ];

    protected $casts = [
        'amount_limit' => 'decimal:2',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }
}
