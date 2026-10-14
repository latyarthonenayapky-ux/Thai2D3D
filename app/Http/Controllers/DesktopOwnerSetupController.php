<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Licence\Licence;
use App\Services\Licence\LicenceException;
use App\Services\Licence\LicenceService;
use Carbon\CarbonImmutable;
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

    public function store(Request $request, LicenceService $licences): RedirectResponse
    {
        $this->ensureDesktopLoopbackRequest($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(6)->max(255)],
            'licence_string' => ['required', 'string', 'max:8192'],
        ]);

        $licence = $this->verifiedLicence($validated['licence_string'], $validated['email']);

        $owner = DB::transaction(function () use ($validated, $licence, $licences): User {
            abort_if(User::query()->exists(), 409);

            $licences->install($licence->licenceString);

            $owner = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'owner',
                'status' => true,
            ]);

            return $owner;
        });

        Auth::login($owner);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    /**
     * The licence must be genuinely signed, addressed to this exact email, and
     * still inside its grace window, or the copy is not licensed to start.
     */
    private function verifiedLicence(string $candidate, string $email): Licence
    {
        try {
            $licence = Licence::parse($candidate);
        } catch (LicenceException $exception) {
            throw ValidationException::withMessages([
                'licence_string' => __($exception->messageKey()),
            ]);
        }

        if (! $licence->isEmail($email)) {
            throw ValidationException::withMessages([
                'licence_string' => __('app.licence.error_email_mismatch', ['email' => $licence->email]),
            ]);
        }

        if ($licence->statusAt(CarbonImmutable::now('Asia/Yangon')) === Licence::STATE_EXPIRED) {
            throw ValidationException::withMessages([
                'licence_string' => __('app.licence.error_expired', ['date' => $licence->expiresAt]),
            ]);
        }

        return $licence;
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
