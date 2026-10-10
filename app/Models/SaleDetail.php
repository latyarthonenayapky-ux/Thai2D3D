<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleDetail extends Model
{
    protected $fillable = [
        'sale_input_id',
        'number',
        'amount',
        'is_excluded',
        'status',
        'reject_reason',
    ];

    protected $casts = [
        'is_excluded' => 'boolean',
        'amount' => 'decimal:2',
    ];

    public function saleInput(): BelongsTo
    {
        return $this->belongsTo(SaleInput::class, 'sale_input_id');
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isExcluded(): bool
    {
        return $this->status === 'excluded' || $this->is_excluded;
    }
}
