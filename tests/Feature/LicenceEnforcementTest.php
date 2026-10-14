<?php

namespace Tests\Feature;

use App\Models\InstalledLicence;
use App\Models\LicenceState;
use App\Models\User;
use App\Services\Licence\Licence;
use App\Services\Licence\LicenceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsLicences;
use Tests\TestCase;

/**
 * What the licence actually does to a running desktop copy: read-only when it
 * expires, and the narrow set of things that must keep working anyway.
 */
class LicenceEnforcementTest extends TestCase
{
    use RefreshDatabase, SignsLicences;

    private const OWNER_EMAIL = 'owner@wya.test';

    public function test_owner_setup_accepts_a_signed_licence_and_records_it(): void
    {
        config(['app.desktop_mode' => true]);
        $candidate = $this->activeLicence(self::OWNER_EMAIL);

        $this->post(route('desktop.owner-setup.store'), [
            'name' => 'Local Owner',
            'email' => self::OWNER_EMAIL,
            'password' => 'local-desktop-password',
            'password_confirmation' => 'local-desktop-password',
            'licence_string' => $candidate,
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', ['email' => self::OWNER_EMAIL, 'role' => 'owner']);
        $this->assertDatabaseHas('licences', ['email' => self::OWNER_EMAIL, 'licence_string' => $candidate]);
        $this->assertSame($candidate, LicenceState::single()->licence->licence_string);
        $this->assertAuthenticated();
    }

    public function test_owner_setup_refuses_a_licence_issued_to_a_different_email(): void
    {
        config(['app.desktop_mode' => true]);

        $this->post(route('desktop.owner-setup.store'), [
            'name' => 'Local Owner',
            'email' => self::OWNER_EMAIL,
            'password' => 'local-desktop-password',
            'password_confirmation' => 'local-desktop-password',
            'licence_string' => $this->activeLicence('someone-else@wya.test'),
        ])->assertSessionHasErrors('licence_string');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('licences', 0);
    }

    public function test_owner_setup_refuses_an_expired_licence(): void
    {
        config(['app.desktop_mode' => true]);

        $this->post(route('desktop.owner-setup.store'), [
            'name' => 'Local Owner',
            'email' => self::OWNER_EMAIL,
            'password' => 'local-desktop-password',
            'password_confirmation' => 'local-desktop-password',
            'licence_string' => $this->issueLicence(self::OWNER_EMAIL, '2024-01-01', '2025-01-01'),
        ])->assertSessionHasErrors('licence_string');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_owner_setup_refuses_a_string_that_is_not_signed_for_this_product(): void
    {
        config(['app.desktop_mode' => true]);

        $this->post(route('desktop.owner-setup.store'), [
            'name' => 'Local Owner',
            'email' => self::OWNER_EMAIL,
            'password' => 'local-desktop-password',
            'password_confirmation' => 'local-desktop-password',
            'licence_string' => 'dGVzdA==.ZmFrZQ',
        ])->assertSessionHasErrors('licence_string');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_an_expired_licence_stops_writes_but_every_page_still_opens(): void
    {
        $owner = $this->licensedDesktop(expiry: '-30 days');

        $this->actingAs($owner)
            ->patch(route('preferences.display'), ['clock_format' => '24', 'currency_label' => 'MMK'])
            ->assertRedirect(route('desktop.licence'));

        $this->actingAs($owner)->get(route('dashboard'))->assertOk();
        $this->actingAs($owner)->get(route('admin.users.index'))->assertOk();

        $this->assertDatabaseMissing('user_preferences', ['user_id' => $owner->id, 'key' => 'clock_format']);
    }

    public function test_an_expired_licence_also_stops_logging_in(): void
    {
        $this->licensedDesktop(expiry: '-30 days');

        $this->post(route('login.store'), [
            'email' => self::OWNER_EMAIL,
            'password' => 'local-desktop-password',
        ])->assertRedirect(route('desktop.licence'));

        $this->assertGuest();
    }

    public function test_backup_and_logout_stay_available_while_locked(): void
    {
        $owner = $this->licensedDesktop(expiry: '-30 days');

        $backup = $this->actingAs($owner)->post(route('admin.data-backup.store'));
        $backup->assertRedirect();
        $this->assertStringNotContainsString(
            '/desktop/licence',
            (string) $backup->headers->get('Location'),
            'Taking a backup must not be blocked by an expired licence.'
        );

        $this->actingAs($owner)->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_the_grace_window_keeps_the_copy_fully_writable(): void
    {
        $owner = $this->licensedDesktop(expiry: '-6 days');

        $this->assertSame(Licence::STATE_GRACE, app(LicenceService::class)->status()->state);

        $this->actingAs($owner)
            ->patch(route('preferences.display'), ['clock_format' => '12', 'currency_label' => 'MMK'])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('12', $owner->fresh()->preference('clock_format'));
    }

    public function test_the_day_after_grace_locks_the_copy(): void
    {
        $owner = $this->licensedDesktop(expiry: '-'.(Licence::GRACE_DAYS + 1).' days');

        $this->assertSame(Licence::STATE_EXPIRED, app(LicenceService::class)->status()->state);

        $this->actingAs($owner)
            ->patch(route('preferences.display'), ['clock_format' => '12', 'currency_label' => 'MMK'])
            ->assertRedirect(route('desktop.licence'));
    }

    public function test_a_rolled_back_clock_locks_even_an_unexpired_licence(): void
    {
        $owner = $this->licensedDesktop();
        LicenceState::single()->forceFill([
            'last_seen_at' => CarbonImmutable::now('Asia/Yangon')->addDays(3),
        ])->save();

        $this->assertSame(Licence::STATE_CLOCK_ROLLBACK, app(LicenceService::class)->status()->state);

        $this->actingAs($owner)
            ->patch(route('preferences.display'), ['clock_format' => '12', 'currency_label' => 'MMK'])
            ->assertRedirect(route('desktop.licence'));
    }

    public function test_pasting_a_renewal_unlocks_the_copy_and_keeps_the_history(): void
    {
        $owner = $this->licensedDesktop(expiry: '-30 days');
        $this->actingAs($owner)->patch(route('preferences.display'), ['clock_format' => '24', 'currency_label' => 'MMK'])
            ->assertRedirect(route('desktop.licence'));

        $this->post(route('desktop.licence.store'), [
            'licence_string' => $this->issueLicence(self::OWNER_EMAIL, '2026-10-14', '2028-10-14', ['id' => 'WYA-2027-0002']),
        ])->assertRedirect(route('desktop.licence'));

        $this->assertSame(Licence::STATE_VALID, app(LicenceService::class)->status()->state);
        $this->assertDatabaseCount('licences', 2);
        $this->assertSame('WYA-2027-0002', LicenceState::single()->licence->licence_id);
        $this->assertDatabaseHas('licences', ['licence_id' => 'WYA-TEST-0001']);

        $this->actingAs($owner->fresh())
            ->patch(route('preferences.display'), ['clock_format' => '12', 'currency_label' => 'MMK'])
            ->assertSessionDoesntHaveErrors();
    }

    public function test_a_signed_out_customer_can_still_renew(): void
    {
        $this->licensedDesktop(expiry: '-30 days');

        $this->post(route('desktop.licence.store'), [
            'licence_string' => $this->activeLicence(self::OWNER_EMAIL),
        ])->assertRedirect(route('login'));

        $this->assertSame(Licence::STATE_VALID, app(LicenceService::class)->status()->state);
    }

    public function test_the_licence_page_reports_the_locked_state_and_the_renewal_box(): void
    {
        $owner = $this->licensedDesktop(expiry: '-30 days');

        $this->actingAs($owner)->get(route('desktop.licence'))
            ->assertOk()
            ->assertSee(__('app.licence.state_expired'))
            ->assertSee(__('app.licence.renew_button'))
            ->assertSee(__('app.licence.history_title'), false);
    }

    public function test_a_non_owner_sees_the_status_but_cannot_install(): void
    {
        $this->licensedDesktop();
        $operator = User::factory()->create(['role' => 'operator', 'status' => true]);

        $this->actingAs($operator)->get(route('desktop.licence'))
            ->assertOk()
            ->assertSee(__('app.licence.owner_only'))
            ->assertDontSee(__('app.licence.renew_button'));
    }

    public function test_the_navigation_warns_inside_thirty_seven_and_one_days(): void
    {
        $owner = $this->licensedDesktop();
        $service = app(LicenceService::class);

        $this->assertNull($service->banner());

        $this->moveExpiry('+45 days');
        $this->assertNull($service->banner());

        $this->moveExpiry('+20 days');
        $this->assertSame(30, $service->banner()['tier']);

        $this->moveExpiry('+5 days');
        $this->assertSame(7, $service->banner()['tier']);

        $this->moveExpiry('+1 day');
        $this->assertSame(1, $service->banner()['tier']);

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('app.licence.banner', ['days' => 1]));
    }

    public function test_the_console_command_reports_the_licence_state(): void
    {
        config(['app.desktop_mode' => true]);
        app(LicenceService::class)->install($this->activeLicence(self::OWNER_EMAIL));

        $this->artisan('thai2d3d:licence-check')
            ->expectsOutputToContain('LICENCE-STATUS='.Licence::STATE_VALID)
            ->assertSuccessful();
    }

    public function test_a_server_install_is_not_licensed_at_all(): void
    {
        $owner = $this->licensedDesktop(expiry: '-30 days');
        config(['app.desktop_mode' => false]);

        $this->actingAs($owner)
            ->patch(route('preferences.display'), ['clock_format' => '12', 'currency_label' => 'MMK'])
            ->assertSessionDoesntHaveErrors();

        $this->assertAuthenticated();
        $this->get(route('desktop.licence'))->assertNotFound();
        $this->assertSame(Licence::STATE_EXPIRED, app(LicenceService::class)->status()->state);
    }

    /**
     * Puts a licence of the given age on the copy and returns its Owner.
     */
    private function licensedDesktop(string $expiry = '+365 days'): User
    {
        config(['app.desktop_mode' => true]);

        $owner = User::factory()->create([
            'role' => 'owner',
            'email' => self::OWNER_EMAIL,
            'password' => 'local-desktop-password',
            'status' => true,
        ]);

        $issuedAt = CarbonImmutable::now('Asia/Yangon')->subYear()->toDateString();
        $expiresAt = CarbonImmutable::now('Asia/Yangon')->modify($expiry)->toDateString();

        app(LicenceService::class)->install(
            $this->issueLicence(self::OWNER_EMAIL, $issuedAt, $expiresAt)
        );

        return $owner;
    }

    /**
     * Re-installs the copy's licence so it expires the given distance from now.
     */
    private function moveExpiry(string $modifier): void
    {
        $stored = LicenceState::single()->licence;
        $expiresAt = CarbonImmutable::now('Asia/Yangon')->modify($modifier);

        app(LicenceService::class)->install($this->issueLicence(
            $stored->email,
            $expiresAt->copy()->subYear()->toDateString(),
            $expiresAt->toDateString(),
            ['id' => $stored->licence_id],
        ));
    }
}
