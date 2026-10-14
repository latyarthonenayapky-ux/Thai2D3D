<?php

namespace App\Services\Licence;

use App\Models\InstalledLicence;
use Carbon\CarbonImmutable;

/**
 * The licence verdict for this moment, ready for middleware, the licence page
 * and the navigation banner.
 */
final class LicenceStatus
{
    public function __construct(
        public readonly string $state,
        public readonly ?InstalledLicence $licence = null,
        public readonly ?string $problem = null,
        public readonly ?CarbonImmutable $now = null,
    ) {
    }

    public function isWritable(): bool
    {
        return in_array($this->state, [Licence::STATE_VALID, Licence::STATE_GRACE], true);
    }

    /**
     * Only a licence that has passed its expiry (or a rolled-back clock) earns
     * the stern wording; grace still gets a warning.
     */
    public function isLocked(): bool
    {
        return ! $this->isWritable();
    }

    public function expiresOn(): ?string
    {
        return $this->licence?->expires_at?->toDateString();
    }

    public function graceEndsAt(): ?CarbonImmutable
    {
        if ($this->licence === null) {
            return null;
        }

        try {
            return $this->licence->licence()->graceEndsAt();
        } catch (LicenceException) {
            return null;
        }
    }

    public function daysRemaining(): ?int
    {
        if ($this->licence === null || $this->now === null) {
            return null;
        }

        try {
            return $this->licence->licence()->daysRemaining($this->now);
        } catch (LicenceException) {
            return null;
        }
    }
}
