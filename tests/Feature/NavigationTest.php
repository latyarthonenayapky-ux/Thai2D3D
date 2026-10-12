<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The nav nests its own <details> popovers (Display, Language), so the
     * block has to be read with a balance-aware scan rather than a regex.
     */
    private function popover(string $html, string $label): string
    {
        $summary = '<summary>'.$label.'</summary>';
        $start = strpos($html, $summary);

        if ($start === false) {
            throw new RuntimeException("No {$label} popover in the rendered navigation.");
        }

        $cursor = $start + strlen($summary);
        $depth = 0;

        while (true) {
            $open = strpos($html, '<details', $cursor);
            $close = strpos($html, '</details>', $cursor);

            if ($close === false) {
                throw new RuntimeException("Unbalanced {$label} popover.");
            }

            if ($open !== false && $open < $close) {
                $depth++;
                $cursor = $open + strlen('<details');

                continue;
            }

            if ($depth > 0) {
                $depth--;
                $cursor = $close + strlen('</details>');

                continue;
            }

            return substr($html, $start, $close - $start);
        }
    }

    /** @return list<string> */
    private function menuLinks(string $block): array
    {
        preg_match_all('/<a href="(?<url>[^"]+)"/', $block, $links);

        return $links['url'];
    }

    public function test_setting_holds_the_configuration_items_and_menu_ends_with_the_manual(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();

        $setting = $this->popover($html, 'Setting');
        $menu = $this->popover($html, 'Menu');

        foreach (['Application settings', 'Add Agent', 'Add Operator', 'Display', 'Language',
            'Sale Entry behaviour', 'Notifications', 'Security', 'Data Backup', 'Activity log'] as $item) {
            $this->assertStringContainsString($item, $setting, "Setting should list {$item}");
        }

        foreach (['Round schedule', 'Agents and limits', 'Hot Numbers', 'Results and settlements',
            '3D Draw settings', 'Period summaries', 'Pending Review', 'Agent Statement'] as $item) {
            $this->assertStringContainsString($item, $menu, "Menu should list {$item}");
        }

        $this->assertStringNotContainsString('Data Backup', $menu);
        $this->assertStringNotContainsString('Add Operator', $menu);
        $links = $this->menuLinks($menu);
        $this->assertStringEndsWith('/manual', end($links));
    }

    public function test_the_dashboard_no_longer_duplicates_the_moved_shortcuts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();

        $body = substr($html, (int) strpos($html, '</nav>'));

        $this->assertStringNotContainsString('Enable phone alerts', $body);
        $this->assertStringNotContainsString('id="enable-review-notifications"', $body);
        $this->assertStringNotContainsString('#round-schedule">Round schedule', $body);
        $this->assertStringNotContainsString('Results and settlements', $body);
        $this->assertStringNotContainsString('">3D Sale Entry</a>', $body);
        $this->assertStringNotContainsString('Hot Numbers</a>', $body);

        $this->assertStringContainsString('Live sales dashboard', $html);
        $this->assertStringContainsString('id="pending-review-count"', $this->popover($html, 'Menu'));
    }

    public function test_a_desktop_owner_gets_the_setting_entries_they_can_actually_open(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'status' => true]);

        $html = $this->actingAs($owner)->get(route('dashboard'))->assertOk()->getContent();

        $setting = $this->popover($html, 'Setting');
        $menu = $this->popover($html, 'Menu');

        foreach (['Add Operator', 'Display', 'Language', 'Data Backup', 'Activity log'] as $item) {
            $this->assertStringContainsString($item, $setting, "Setting should list {$item} for the owner");
        }

        // The business settings page answers 403 for owners, so it must not be linked.
        foreach (['Application settings', 'Add Agent', 'Sale Entry behaviour', 'Notifications', 'Security'] as $item) {
            $this->assertStringNotContainsString($item, $setting, "Setting should hide {$item} from the owner");
        }

        foreach (['admin.users.index', 'admin.data-backup.index', 'admin.activity-log.index'] as $route) {
            $this->actingAs($owner)->get(route($route))->assertOk();
        }

        $this->assertStringNotContainsString('Language', $menu);
        $this->assertStringNotContainsString('Round schedule', $menu);
        $links = $this->menuLinks($menu);
        $this->assertStringEndsWith('/manual', end($links));
    }

    public function test_an_operator_gets_language_and_statement_entries_without_the_admin_menu(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => true,
            'admin_id' => $admin->id,
        ]);

        $html = $this->actingAs($operator)->get(route('dashboard'))->assertOk()->getContent();

        $this->expectException(RuntimeException::class);
        $this->popover($html, 'Setting');
    }

    public function test_an_operator_menu_offers_language_statement_and_the_manual(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => true,
            'admin_id' => $admin->id,
        ]);

        $menu = $this->popover(
            $this->actingAs($operator)->get(route('dashboard'))->assertOk()->getContent(),
            'Menu'
        );

        $this->assertStringContainsString('Language', $menu);
        $this->assertStringContainsString('Display', $menu);
        $this->assertStringContainsString('Agent Statement', $menu);
        $this->assertStringNotContainsString('Round schedule', $menu);
        $this->assertStringNotContainsString('Pending Review', $menu);
        $links = $this->menuLinks($menu);
        $this->assertStringEndsWith('/manual', end($links));
    }
}
