<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\InstallKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DesktopOwnerSetupController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $this->ensureDesktopLoopbackRequest($request);

        if (User::query()->exists()) {
            return redirect()->route('login');
        }

        return view('desktop.owner-setup');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureDesktopLoopbackRequest($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(6)->max(255)],
            'install_key' => ['required', 'string', 'max:64'],
        ]);

        if (! InstallKey::matches($validated['email'], $validated['install_key'])) {
            throw ValidationException::withMessages([
                'install_key' => 'The install pass key does not match this email.',
            ]);
        }

        $owner = DB::transaction(function () use ($validated): User {
            abort_if(User::query()->exists(), 409);

            return User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'owner',
                'status' => true,
            ]);
        });

        Auth::login($owner);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    private function ensureDesktopLoopbackRequest(Request $request): void
    {
        // Fail closed on servers: even if THAI2D3D_DESKTOP is set by mistake on
        // a VPS behind a local reverse proxy (peer 127.0.0.1), owner signup only
        // opens for the desktop app, which always runs SQLite locally.
        abort_unless(
            config('app.desktop_mode')
            && config('database.default') === 'sqlite'
            && in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true),
            404
        );
    }
}
