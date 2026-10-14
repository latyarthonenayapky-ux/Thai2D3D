<?php

namespace Tests\Feature;

use App\Models\LicenceState;
use App\Models\User;
use App\Services\Licence\Licence;
use App\Services\Licence\LicenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SignsLicences;
use Tests\TestCase;

class DesktopOwnerSetupTest extends TestCase
{
    use RefreshDatabase, SignsLicences;

    private const OWNER_EMAIL = 'owner@thai2d3d.local';

    public function test_first_run_owner_setup_is_available_only_in_desktop_mode(): void
    {
        config(['app.desktop_mode' => true]);

        $this->get(route('desktop.owner-setup'))
            ->assertOk()
            ->assertSee('Create Owner account');

        config(['app.desktop_mode' => false]);

        $this->get(route('desktop.owner-setup'))->assertNotFound();
    }

    public function test_first_run_setup_creates_and_authenticates_the_local_owner(): void
    {
        config(['app.desktop_mode' => true]);
        $candidate = $this->activeLicence(self::OWNER_EMAIL);

        $this->post(route('desktop.owner-setup.store'), $this->payload($candidate))
            ->assertRedirect(route('dashboard'));

        $owner = User::query()->where('email', self::OWNER_EMAIL)->firstOrFail();

        $this->assertSame('owner', $owner->role);
        $this->assertTrue($owner->status);
        $this->assertTrue(Hash::check('local-desktop-password', $owner->password));
        $this->assertAuthenticatedAs($owner);

        // The copy is licensed from the first click, so the owner can carry on
        // using it and renew later without re-entering the string.
        $this->assertSame($candidate, LicenceState::single()->licence->licence_string);
        $this->assertSame(Licence::STATE_VALID, app(LicenceService::class)->status()->state);
    }

    public function test_setup_requires_at_least_six_password_characters(): void
    {
        config(['app.desktop_mode' => true]);

        $this->post(route('desktop.owner-setup.store'), $this->payload(
            $this->activeLicence(self::OWNER_EMAIL), password: 'short'
        ))->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 0);

        $this->post(route('desktop.owner-setup.store'), $this->payload(
            $this->activeLicence(self::OWNER_EMAIL), password: 'sixchr'
        ))->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('users', 1);
    }

    public function test_setup_cannot_create_a_second_owner_account(): void
    {
        config(['app.desktop_mode' => true]);
        User::factory()->create();

        $this->get(route('desktop.owner-setup'))
            ->assertRedirect(route('login'));

        $this->post(route('desktop.owner-setup.store'), $this->payload(
            $this->activeLicence('second@thai2d3d.local'), email: 'second@thai2d3d.local'
        ))->assertConflict();

        $this->assertDatabaseCount('licences', 0);
    }

    public function test_setup_refuses_a_licence_signed_with_another_key(): void
    {
        config(['app.desktop_mode' => true]);
        $candidate = $this->activeLicence(self::OWNER_EMAIL);

        // A different product build, with a different public key baked in.
        config(['licence.public_key' => Licence::encodeBase64Url(random_bytes(32))]);

        $this->post(route('desktop.owner-setup.store'), $this->payload($candidate))
            ->assertSessionHasErrors('licence_string');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('licences', 0);
    }

    /**
     * @return array<string, string>
     */
    private function payload(string $licenceString, string $email = self::OWNER_EMAIL, string $password = 'local-desktop-password'): array
    {
        return [
            'name' => 'Local Owner',
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
            'licence_string' => $licenceString,
        ];
    }
}
