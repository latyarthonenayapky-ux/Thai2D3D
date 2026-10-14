<?php

namespace App\Services\Licence;

use App\Models\InstalledLicence;
use App\Models\LicenceState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Installs licences and answers "what may this copy do right now".
 *
 * Statuses: valid and grace allow everything, expired and clock_rollback drop
 * the app to read-only. A rolled-back clock is never given grace, because the
 * whole point of that state is that the clock cannot be trusted.
 */
class LicenceService
{
    /** Days ahead of expiry that the navigation starts warning. */
    public const BANNER_THRESHOLDS = [30, 7, 1];

    /**
     * Whether this process is a licensed desktop install at all. A server
     * install runs MySQL and has no licence tables, and a desktop flag left on
     * by mistake on a MySQL box must not gate anything either.
     */
    public static function enforcedHere(): bool
    {
        return (bool) config('app.desktop_mode') && config('database.default') === 'sqlite';
    }

    public function install(string $candidate): InstalledLicence
    {
        $licence = Licence::parse($candidate);

        return DB::transaction(function () use ($licence, $candidate): InstalledLicence {
            $stored = InstalledLicence::query()->create([
                'licence_id' => $licence->id,
                'email' => $licence->email,
                'plan' => $licence->plan,
                'max_admins' => $licence->maxAdmins,
                'max_agents' => $licence->maxAgents,
                'issued_at' => $licence->issuedAt,
                'expires_at' => $licence->expiresAt,
                'licence_string' => $candidate,
                'signature_ok' => true,
                'installed_at' => CarbonImmutable::now('Asia/Yangon'),
            ]);

            $this->activate($stored);

            return $stored;
        });
    }

    public function activate(InstalledLicence $stored): void
    {
        $state = LicenceState::single();
        $state->active_licence_id = $stored->id;
        $state->last_seen_at = CarbonImmutable::now('Asia/Yangon');
        $state->save();
    }

    public function status(): LicenceStatus
    {
        $now = CarbonImmutable::now('Asia/Yangon');
        $state = LicenceState::single();
        $stored = $this->active($state);

        if ($stored === null) {
            return new LicenceStatus(Licence::STATE_MISSING, now: $now);
        }

        if ($stored->isRevoked()) {
            return new LicenceStatus(Licence::STATE_EXPIRED, $stored, $stored->revoked_reason, $now);
        }

        if ($this->isClockRolledBack($state, $now)) {
            return new LicenceStatus(Licence::STATE_CLOCK_ROLLBACK, $stored, null, $now);
        }

        try {
            $licence = $stored->licence();
        } catch (LicenceException $exception) {
            return new LicenceStatus(Licence::STATE_INVALID, $stored, $exception->getMessage(), $now);
        }

        return new LicenceStatus($licence->statusAt($now), $stored, null, $now);
    }

    /**
     * Records how far forward the clock has been seen, at most once a minute so
     * a busy desktop does not turn every request into a write.
     */
    public function touchLastSeen(): void
    {
        $state = LicenceState::single();
        $now = CarbonImmutable::now('Asia/Yangon');
        $lastSeen = $state->last_seen_at;

        if ($lastSeen !== null && $now->lessThan($lastSeen->addMinute())) {
            return;
        }

        $state->last_seen_at = $now;
        $state->save();
    }

    /**
     * The navigation banner for a licence that still works: the threshold tier
     * (30, 7 or 1 day) that now applies, or null when there is nothing to warn
     * about. Grace and locked states get the licence page instead of a banner.
     *
     * @return array{days: int, tier: int}|null
     */
    public function banner(): ?array
    {
        $status = $this->status();

        if ($status->state !== Licence::STATE_VALID) {
            return null;
        }

        $days = $status->daysRemaining();

        if ($days === null || $days > self::BANNER_THRESHOLDS[0]) {
            return null;
        }

        $tier = self::BANNER_THRESHOLDS[0];

        foreach (self::BANNER_THRESHOLDS as $threshold) {
            if ($days <= $threshold) {
                $tier = $threshold;
            }
        }

        return ['days' => $days, 'tier' => $tier];
    }

    private function active(LicenceState $state): ?InstalledLicence
    {
        if ($state->active_licence_id !== null) {
            $stored = InstalledLicence::query()->find($state->active_licence_id);

            if ($stored !== null) {
                return $stored;
            }
        }

        // A licence row can exist without the state row pointing at it (an
        // interrupted install); the newest one is still the copy's licence.
        return InstalledLicence::query()->orderByDesc('id')->first();
    }

    private function isClockRolledBack(LicenceState $state, CarbonImmutable $now): bool
    {
        $lastSeen = $state->last_seen_at;

        return $lastSeen !== null
            && $now->lt($lastSeen->subSeconds(Licence::CLOCK_ROLLBACK_TOLERANCE_SECONDS));
    }
}
