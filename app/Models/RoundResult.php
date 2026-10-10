<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoundResult extends Model
{
    protected $fillable = [
        'admin_id',
        'round_id',
        'result_2d',
        'result_3d',
        'updated_by',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
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
        return $this->hasMany(RoundResultChange::class)->latest();
    }
}
