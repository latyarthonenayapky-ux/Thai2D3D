<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApplyUserPreferences
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        app()->setLocale($user->locale());

        $session = $request->session();
        $idleMinutes = (int) $user->preference('idle_minutes', '0');
        $lastActivity = $session->get('last_activity_at');

        if ($idleMinutes >= 5 && is_string($lastActivity) &&
            Carbon::parse($lastActivity)->lt(Carbon::now()->subMinutes($idleMinutes))) {
            Auth::logout();
            $session->invalidate();
            $session->put('status', __('app.signed_out_idle', ['minutes' => $idleMinutes]));

            return redirect()->route('login');
        }

        $session->put('last_activity_at', Carbon::now()->toIso8601String());

        return $next($request);
    }
}
