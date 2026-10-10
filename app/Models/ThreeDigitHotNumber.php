<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreeDigitHotNumber extends Model
{
    protected $fillable = ['round_id', 'number'];

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }
}
