<?php

namespace App\Models;

use App\Services\Licence\Licence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One row per licence this installation has been given. Never updated except to
 * mark a revocation, so the history stays honest.
 */
class InstalledLicence extends Model
{
    protected $table = 'licences';

    protected $fillable = [
        'licence_id',
        'email',
        'plan',
        'max_admins',
        'max_agents',
        'issued_at',
        'expires_at',
        'licence_string',
        'signature_ok',
        'installed_at',
        'revoked_reason',
    ];

    protected $casts = [
        'max_admins' => 'integer',
        'max_agents' => 'integer',
        'issued_at' => 'date:Y-m-d',
        'expires_at' => 'date:Y-m-d',
        'signature_ok' => 'boolean',
        'installed_at' => 'datetime',
    ];

    public function state(): HasOne
    {
        return $this->hasOne(LicenceState::class, 'active_licence_id');
    }

    /**
     * The parsed, re-verified licence. Verification runs again on read: a row
     * that was tampered with in the database no longer passes its own signature.
     */
    public function licence(): Licence
    {
        return Licence::parse($this->licence_string);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_reason !== null;
    }
}
