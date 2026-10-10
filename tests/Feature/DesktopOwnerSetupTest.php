<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DesktopOwnerSetupTest extends TestCase
{
    use RefreshDatabase;

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

        $this->post(route('desktop.owner-setup.store'), [
            'name' => 'Local Owner',
            'email' => 'owner@thai2d3d.local',
            'password' => 'local-desktop-password',
            'password_confirmation' => 'local-desktop-password',
        ])->assertRedirect(route('dashboard'));

        $owner = User::query()->where('email', 'owner@thai2d3d.local')->firstOrFail();

        $this->assertSame('owner', $owner->role);
        $this->assertTrue($owner->status);
        $this->assertTrue(Hash::check('local-desktop-password', $owner->password));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_setup_cannot_create_a_second_owner_account(): void
    {
        config(['app.desktop_mode' => true]);
        User::factory()->create();

        $this->get(route('desktop.owner-setup'))
            ->assertRedirect(route('login'));

        $this->post(route('desktop.owner-setup.store'), [
            'name' => 'Second Owner',
            'email' => 'second@thai2d3d.local',
            'password' => 'local-desktop-password',
            'password_confirmation' => 'local-desktop-password',
        ])->assertConflict();
    }
}
