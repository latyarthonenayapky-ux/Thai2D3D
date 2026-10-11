<?php

namespace Tests\Feature;

use App\Services\InstallKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallPassKeyTest extends TestCase
{
    use RefreshDatabase;

    // The same known-answer vectors live in desktop/test/install-key.test.js;
    // if the PHP or JavaScript implementation drifts, one suite fails.
    private const VECTOR_EMAIL = 'owner@thai2d3d.local';

    private const VECTOR_KEY = 'HNIMR-YCWQS-NY3PQ-FURJE';

    private const SECOND_EMAIL = 'chief@thai2d3d.local';

    private const SECOND_KEY = 'E6NTS-RDA57-6KUV3-LIVBJ';

    private const OWNER_PASSWORD = 'local-desktop-password';

    public function test_known_answer_vectors_match_the_desktop_keygen(): void
    {
        $this->assertSame(self::VECTOR_KEY, InstallKey::derive(self::VECTOR_EMAIL));
        $this->assertSame(self::SECOND_KEY, InstallKey::derive(self::SECOND_EMAIL));
    }

    public function test_email_case_and_edge_whitespace_are_normalised(): void
    {
        $this->assertSame(
            self::VECTOR_KEY,
            InstallKey::derive("  OWNER@Thai2D3D.LOCAL\t")
        );
    }

    public function test_a_different_email_produces_a_different_key(): void
    {
        $this->assertNotSame(self::VECTOR_KEY, self::SECOND_KEY);
        $this->assertNotSame(self::VECTOR_KEY, InstallKey::derive('owner@thai2d3d.online'));
    }

    public function test_verification_ignores_case_dashes_and_spaces(): void
    {
        $this->assertTrue(InstallKey::matches(
            self::VECTOR_EMAIL,
            strtolower(str_replace('-', ' ', self::VECTOR_KEY))
        ));
        $this->assertFalse(InstallKey::matches(
            self::VECTOR_EMAIL,
            'AAAAA-BBBBB-CCCCC-DDDDD'
        ));
        $this->assertFalse(InstallKey::matches(
            'other@thai2d3d.local',
            self::VECTOR_KEY
        ));
    }

    public function test_owner_setup_rejects_a_missing_install_key(): void
    {
        config(['app.desktop_mode' => true]);

        $this->post(route('desktop.owner-setup.store'), [
            'name' => 'Local Owner',
            'email' => self::VECTOR_EMAIL,
            'password' => self::OWNER_PASSWORD,
            'password_confirmation' => self::OWNER_PASSWORD,
        ])->assertSessionHasErrors('install_key');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_owner_setup_rejects_a_key_for_a_different_email(): void
    {
        config(['app.desktop_mode' => true]);

        $this->post(route('desktop.owner-setup.store'), [
            'name' => 'Local Owner',
            'email' => self::VECTOR_EMAIL,
            'password' => self::OWNER_PASSWORD,
            'password_confirmation' => self::OWNER_PASSWORD,
            'install_key' => InstallKey::derive('other@thai2d3d.local'),
        ])->assertSessionHasErrors('install_key');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_owner_setup_accepts_a_matching_key_with_tolerant_formatting(): void
    {
        config(['app.desktop_mode' => true]);

        $this->post(route('desktop.owner-setup.store'), [
            'name' => 'Local Owner',
            'email' => self::VECTOR_EMAIL,
            'password' => self::OWNER_PASSWORD,
            'password_confirmation' => self::OWNER_PASSWORD,
            'install_key' => strtolower(str_replace('-', ' ', self::VECTOR_KEY)),
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => self::VECTOR_EMAIL,
            'role' => 'owner',
        ]);
    }

    public function test_owner_setup_key_is_independent_of_the_password(): void
    {
        config(['app.desktop_mode' => true]);

        $this->post(route('desktop.owner-setup.store'), [
            'name' => 'Local Owner',
            'email' => self::VECTOR_EMAIL,
            'password' => 'a-completely-different-1',
            'password_confirmation' => 'a-completely-different-1',
            'install_key' => self::VECTOR_KEY,
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => self::VECTOR_EMAIL,
            'role' => 'owner',
        ]);
    }
}
