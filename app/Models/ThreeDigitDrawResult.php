<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThreeDigitDrawResult extends Model
{
    protected $fillable = ['three_digit_draw_id', 'admin_id', 'result', 'updated_by'];

    public function draw(): BelongsTo
    {
        return $this->belongsTo(ThreeDigitDraw::class, 'three_digit_draw_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(ThreeDigitDrawResultChange::class)->latest();
    }
}
