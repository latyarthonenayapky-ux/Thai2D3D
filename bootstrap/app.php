<?php

use App\Http\Middleware\ApplyUserPreferences;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAgentAccessToken;
use App\Http\Middleware\EnsureValidLicence;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Reverse proxies are configured in config/trustedproxy.php; only the
        // local proxy (Cloudflare Tunnel / nginx on the same host) has its
        // X-Forwarded-* headers trusted unless TRUSTED_PROXIES widens the list.
        $middleware->append(SecurityHeaders::class);

        // Inert unless THAI2D3D_DESKTOP is on, so server installs are unchanged.
        $middleware->web(append: [EnsureValidLicence::class]);

        $middleware->alias([
            'active' => EnsureActiveAccount::class,
            'admin' => EnsureAdmin::class,
            'agent.token' => EnsureAgentAccessToken::class,
            'preferences' => ApplyUserPreferences::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

$app->useStoragePath(env('LARAVEL_STORAGE_PATH', $app->storagePath()));
$app->useBootstrapPath(env('LARAVEL_BOOTSTRAP_PATH', $app->bootstrapPath()));

return $app;
