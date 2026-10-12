<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_locale_preference_is_saved_and_applied_to_every_later_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);

        $this->actingAs($admin)
            ->post(route('preferences.locale'), ['locale' => 'my'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('my', $admin->fresh()->locale());

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('လက်စွဲ');

        $this->assertSame('my', app()->getLocale());
    }

    public function test_an_unsupported_locale_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);

        $this->actingAs($admin)
            ->post(route('preferences.locale'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');

        $this->assertSame('en', $admin->fresh()->locale());
    }

    public function test_an_operator_can_choose_their_own_language(): void
    {
        $operator = User::factory()->create(['role' => 'operator', 'status' => true]);

        $this->actingAs($operator)
            ->post(route('preferences.locale'), ['locale' => 'my'])
            ->assertRedirect();

        $this->assertSame('my', $operator->fresh()->locale());
        $this->actingAs($operator)->get(route('dashboard'))->assertOk()->assertSee('လက်စွဲ');
    }

    public function test_the_clock_format_and_currency_label_are_stored_uppercase(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);

        $this->actingAs($admin)
            ->patch(route('preferences.display'), ['clock_format' => '12', 'currency_label' => 'ks'])
            ->assertRedirect();

        $fresh = $admin->fresh();
        $this->assertSame('12', $fresh->clockFormat());
        $this->assertSame('KS', $fresh->currencyLabel());
    }

    public function test_amounts_use_the_saved_currency_label(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $admin->rememberPreference('currency_label', 'BAHT');

        $this->actingAs($admin)
            ->get(route('reports.summary', ['mode' => '2d']))
            ->assertOk()
            ->assertSee('BAHT');
    }

    public function test_sale_entry_typing_normalization_follows_the_preference(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);

        $this->actingAs($admin)
            ->patch(route('preferences.sale-entry'), ['sale_typing_normalization' => 'off'])
            ->assertRedirect();

        $this->assertFalse($admin->fresh()->prefers('sale_typing_normalization', 'on'));

        $this->actingAs($admin)
            ->patch(route('preferences.sale-entry'), ['sale_typing_normalization' => 'on'])
            ->assertRedirect();

        $this->assertTrue($admin->fresh()->prefers('sale_typing_normalization', 'on'));
    }

    public function test_review_alerts_default_to_enabled_and_can_be_switched_off(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);

        $this->assertTrue($admin->prefers('review_alerts', 'on'));

        $this->actingAs($admin)
            ->patch(route('preferences.notifications'), ['review_alerts' => 'off'])
            ->assertRedirect();

        $this->assertFalse($admin->fresh()->prefers('review_alerts', 'on'));
    }

    public function test_a_stale_session_is_signed_out_after_the_chosen_idle_period(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $admin->rememberPreference('idle_minutes', '15');

        Carbon::setTestNow(Carbon::parse('2026-10-12 09:00:00', 'Asia/Yangon'));

        $this->actingAs($admin)
            ->withSession(['last_activity_at' => Carbon::now()->subMinutes(20)->toIso8601String()])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'You were signed out after 15 minutes of inactivity.');

        $this->assertGuest();

        Carbon::setTestNow();
    }

    public function test_a_recent_session_is_kept_and_stamped(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $admin->rememberPreference('idle_minutes', '15');

        $this->actingAs($admin)
            ->withSession(['last_activity_at' => Carbon::now()->subMinutes(2)->toIso8601String()])
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertAuthenticatedAs($admin);
        $this->assertNotEmpty(session('last_activity_at'));
    }

    public function test_an_idle_limit_of_zero_never_signs_the_user_out(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $admin->rememberPreference('idle_minutes', '0');

        $this->actingAs($admin)
            ->withSession(['last_activity_at' => Carbon::now()->subDays(3)->toIso8601String()])
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_only_whitelisted_idle_intervals_are_accepted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);

        $this->actingAs($admin)
            ->patch(route('preferences.security'), ['idle_minutes' => 7])
            ->assertSessionHasErrors('idle_minutes');

        $this->assertNull($admin->fresh()->preference('idle_minutes'));
    }
}
