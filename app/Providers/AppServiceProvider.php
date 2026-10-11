<?php

namespace App\Providers;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->forceHttpsForPublicHosts();
        $this->recordAuthenticationEvents();

        RateLimiter::for('login', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by(
                $email.'|'.$request->ip()
            );
        });
    }

    private function recordAuthenticationEvents(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            ActivityLog::record('auth.login', null, ['email' => $event->user->email], $event->user);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user !== null) {
                ActivityLog::record('auth.logout', null, ['email' => $event->user->email], $event->user);
            }
        });

        Event::listen(Failed::class, function (Failed $event): void {
            ActivityLog::record('auth.failed', null, [
                'email' => $event->credentials['email'] ?? null,
            ]);
        });
    }

    /**
     * The app is reached through a TLS-terminating reverse proxy (Cloudflare
     * Tunnel, localhost.run, or a production load balancer). Some of these
     * forward the original Host header but send no X-Forwarded-Proto, so
     * trustProxies() alone cannot recover the scheme and Laravel would emit
     * http:// URLs on an https:// page, which browsers block as mixed content.
     *
     * Any request that is not aimed at a loopback/local host is therefore
     * treated as HTTPS. Local http://127.0.0.1:8000 development is unaffected.
     */
    private function forceHttpsForPublicHosts(): void
    {
        if (! filter_var(env('ASSUME_HTTPS_FOR_PUBLIC_HOSTS', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $host = (string) request()->getHost();

        $isLocal = $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || $host === '0.0.0.0'
            || str_ends_with($host, '.localhost')
            || str_starts_with($host, '192.168.')
            || str_starts_with($host, '10.');

        if (! $isLocal) {
            URL::forceScheme('https');
        }
    }
}
