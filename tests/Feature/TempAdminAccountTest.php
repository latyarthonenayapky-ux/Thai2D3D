<?php

namespace Tests\Feature;

use App\Models\AdminBusinessSetting;
use App\Models\RoundSchedule;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\CodeRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TempAdminAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CodeRuleSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner', 'status' => true]);
    }

    public function test_owner_can_create_a_seven_day_temp_admin(): void
    {
        $response = $this->actingAs($this->owner())->post(route('admin.users.store'), [
            'name' => 'Field Tester',
            'email' => 'tester@example.test',
            'role' => 'temp_admin',
            'password' => 'FieldTester1234!',
            'password_confirmation' => 'FieldTester1234!',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $temp = User::query()->where('email', 'tester@example.test')->firstOrFail();
        $this->assertSame('temp_admin', $temp->role);
        $this->assertTrue($temp->isAdmin());
        $this->assertTrue($temp->isTempAdmin());
        $this->assertFalse($temp->isExpired());
        $this->assertNull($temp->admin_id);

        $days = (int) round(Carbon::now('Asia/Yangon')->diffInDays($temp->expires_at));
        $this->assertSame(7, $days);

        // Given a full admin sandbox so the field tester can actually explore.
        $this->assertTrue(AdminBusinessSetting::query()->where('admin_id', $temp->id)->exists());
        $this->assertSame(3, RoundSchedule::query()->where('admin_id', $temp->id)->count());
    }

    public function test_admin_cannot_create_a_temp_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.test',
            'role' => 'temp_admin',
            'password' => 'SneakyPass1234!',
            'password_confirmation' => 'SneakyPass1234!',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.test']);
    }

    public function test_temp_admin_can_reach_the_admin_area_before_expiry(): void
    {
        $temp = User::factory()->create([
            'role' => 'temp_admin',
            'status' => true,
            'expires_at' => Carbon::now('Asia/Yangon')->addDays(7),
        ]);

        $this->actingAs($temp)
            ->get(route('admin.settings.index'))
            ->assertOk();
    }

    public function test_expired_temp_admin_is_redirected_to_login(): void
    {
        $temp = User::factory()->create([
            'role' => 'temp_admin',
            'status' => true,
            'expires_at' => Carbon::now('Asia/Yangon')->subMinute(),
        ]);

        $this->actingAs($temp)
            ->get(route('admin.settings.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_expire_command_deactivates_expired_accounts_and_their_operators(): void
    {
        $expired = User::factory()->create([
            'role' => 'temp_admin',
            'status' => true,
            'expires_at' => Carbon::now('Asia/Yangon')->subDay(),
        ]);
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => true,
            'admin_id' => $expired->id,
        ]);
        $active = User::factory()->create([
            'role' => 'temp_admin',
            'status' => true,
            'expires_at' => Carbon::now('Asia/Yangon')->addDays(3),
        ]);

        $this->artisan('users:expire-temp')->assertSuccessful();

        $this->assertFalse($expired->fresh()->status);
        $this->assertFalse($operator->fresh()->status);
        $this->assertTrue($active->fresh()->status);
    }
}
