<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The single row that says what this copy is running on right now.
 */
class LicenceState extends Model
{
    protected $table = 'licence_state';

    protected $fillable = ['active_licence_id', 'last_seen_at'];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function licence(): BelongsTo
    {
        return $this->belongsTo(InstalledLicence::class, 'active_licence_id');
    }

    /**
     * @return static  the one row, created on first need
     */
    public static function single(): self
    {
        $state = static::query()->orderBy('id')->first();

        return $state ?? static::query()->create([]);
    }
}
