<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreeDigitDrawHotNumber extends Model
{
    protected $fillable = ['three_digit_draw_id', 'number'];

    public function draw(): BelongsTo
    {
        return $this->belongsTo(ThreeDigitDraw::class, 'three_digit_draw_id');
    }
}
