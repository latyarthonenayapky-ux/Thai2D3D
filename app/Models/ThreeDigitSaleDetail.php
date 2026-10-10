<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreeDigitSaleDetail extends Model
{
    protected $fillable = [
        'three_digit_sale_input_id',
        'number',
        'amount',
        'status',
        'reject_reason',
    ];

    protected $casts = ['amount' => 'decimal:2'];

    public function saleInput(): BelongsTo
    {
        return $this->belongsTo(ThreeDigitSaleInput::class, 'three_digit_sale_input_id');
    }
}
