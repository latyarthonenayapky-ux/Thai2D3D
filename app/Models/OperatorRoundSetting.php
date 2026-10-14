<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorRoundSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'round_id',
        'operator_id',
        'amount_limit',
        'amount_limit_3d',
        'amount_limit_mode',
    ];

    protected $casts = [
        'amount_limit' => 'decimal:2',
        'amount_limit_3d' => 'decimal:2',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
