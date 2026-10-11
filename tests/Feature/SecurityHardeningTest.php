<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const CSP = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'";

    public function test_security_headers_are_sent_on_web_responses(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', self::CSP);
    }

    public function test_hsts_is_sent_on_https_requests_only(): void
    {
        $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        $this->get('http://localhost/login')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_forwarded_proto_is_ignored_from_untrusted_addresses(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('http://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-For' => '127.0.0.1',
            ])
            ->get('http://localhost/login')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_owner_setup_stays_closed_when_the_database_is_not_sqlite(): void
    {
        $originalConnection = config('database.default');
        config(['app.desktop_mode' => true, 'database.default' => 'mysql']);

        try {
            $this->get(route('desktop.owner-setup'))->assertNotFound();
        } finally {
            // RefreshDatabase tears down the sqlite in-memory connection using
            // the current default connection name, so restore it before the
            // test ends or every later test in the run inherits a broken PDO.
            config(['database.default' => $originalConnection]);
        }
    }

    public function test_money_touching_write_routes_carry_a_throttle(): void
    {
        $throttled = [
            'operator.sales.store',
            'operator.sales.store-batch',
            'three-digit.sales.store',
            'three-digit.sales.store-batch',
            'admin.settings.schedule',
            'admin.settings.payouts',
            'admin.settings.agents.store',
            'admin.settings.agents.update',
            'admin.settings.rounds.agents.amount-limit',
            'admin.settings.rounds.agents.amount-limit-3d',
            'admin.settings.rounds.reopen',
            'admin.settings.rounds.close',
            'admin.settings.rounds.number-limits',
            'admin.rounds.result.update',
            'admin.three-digit.results.update',
            'admin.three-digit.draws.hot-numbers.store',
            'admin.settings.hot-numbers.store',
            'admin.offline-reviews.update',
            'admin.users.status',
        ];

        foreach ($throttled as $name) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} is missing.");
            $this->assertContains('throttle:120,1', $route->gatherMiddleware(), "Route {$name} is missing its throttle.");
        }
    }
}
