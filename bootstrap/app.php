<?php

use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The app is served behind a reverse proxy (Cloudflare Tunnel terminates
        // TLS at the edge and forwards plain HTTP to the local server). Trusting
        // the proxy headers lets Laravel see the original https scheme and host,
        // so route()/url() generate correct absolute links and the session cookie
        // is not downgraded to an insecure origin.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'active' => EnsureActiveAccount::class,
            'admin' => EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

$app->useStoragePath(env('LARAVEL_STORAGE_PATH', $app->storagePath()));
$app->useBootstrapPath(env('LARAVEL_BOOTSTRAP_PATH', $app->bootstrapPath()));

return $app;
