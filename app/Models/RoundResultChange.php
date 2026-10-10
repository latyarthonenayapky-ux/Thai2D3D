<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundResultChange extends Model
{
    protected $fillable = [
        'round_result_id',
        'changed_by',
        'field',
        'old_value',
        'new_value',
    ];

    public function roundResult(): BelongsTo
    {
        return $this->belongsTo(RoundResult::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
