<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'round_id',
        'number',
    ];

    protected $casts = [
        'round_id' => 'integer',
        'number' => 'string',
    ];

    /**
     * Hot Number belongs to a Round.
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }
}
