<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreeDigitDrawResultChange extends Model
{
    protected $fillable = ['three_digit_draw_result_id', 'changed_by', 'old_value', 'new_value'];

    public function result(): BelongsTo
    {
        return $this->belongsTo(ThreeDigitDrawResult::class, 'three_digit_draw_result_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
