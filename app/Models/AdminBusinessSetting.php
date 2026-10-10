<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminBusinessSetting extends Model
{
    protected $fillable = [
        'admin_id',
        'payout_2d_multiplier',
        'payout_3d_multiplier',
    ];

    protected $casts = [
        'payout_2d_multiplier' => 'decimal:2',
        'payout_3d_multiplier' => 'decimal:2',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
