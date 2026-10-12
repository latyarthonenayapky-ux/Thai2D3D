<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * The one Agent APK a business admin publishes, kept on the private disk so it
 * is only reachable through the admin-only download route.
 */
class MobileAppPackage extends Model
{
    protected $fillable = [
        'admin_id',
        'stored_path',
        'file_name',
        'version',
        'size_bytes',
        'published_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'published_at' => 'datetime:Y-m-d H:i',
        'created_at' => 'datetime:Y-m-d H:i',
        'updated_at' => 'datetime:Y-m-d H:i',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function hasFile(): bool
    {
        return $this->stored_path !== null && Storage::disk('local')->exists($this->stored_path);
    }
}
