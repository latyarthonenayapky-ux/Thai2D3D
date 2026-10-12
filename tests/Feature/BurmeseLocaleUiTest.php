<?php

namespace Tests\Feature;

use App\Models\Round;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BurmeseLocaleUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_screens_render_in_burmese_without_leaking_translation_keys(): void
    {
        $admin = $this->burmeseAdmin();

        $routes = [
            'admin.users.index',
            'admin.settlements.index',
            'admin.offline-reviews.index',
            'admin.three-digit.index',
            'admin.three-digit.results',
            'admin.activity-log.index',
            'admin.settings.index',
            'reports.summary',
        ];

        foreach ($routes as $name) {
            $this->actingAs($admin)
                ->get(route($name))
                ->assertOk()
                ->assertDontSee('app.', false);
        }
    }

    public function test_the_users_screen_shows_burmese_headings_and_labels(): void
    {
        $admin = $this->burmeseAdmin();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('အသုံးပြုသူ ဝင်ရောက်ခွင့်')
            ->assertSee('အကောင့် ဖန်တီးရန်')
            ->assertDontSee('Create an account');
    }

    public function test_the_settlement_screen_shows_burmese_labels(): void
    {
        $admin = $this->burmeseAdmin();
        $round = Round::query()->create([
            'admin_id' => $admin->id,
            'round_date' => '2026-10-07',
            'round_no' => 1,
            'start_time' => '07:00:00',
            'close_time' => '09:30:00',
            'status' => 'closed',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.rounds.settlement', $round))
            ->assertOk()
            ->assertSee('Round ချေကုန်ချက်')
            ->assertSee('တရားဝင် Round ရလဒ်များ')
            ->assertDontSee('Official Round results');
    }

    private function burmeseAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($admin);
        $admin->rememberPreference('locale', 'my');

        return $admin;
    }
}
