<?php

namespace App\Http\Controllers;

use App\Models\InstalledLicence;
use App\Services\Licence\Licence;
use App\Services\Licence\LicenceException;
use App\Services\Licence\LicenceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The licence screen: status, history and the renewal box.
 *
 * It stays reachable while the copy is locked, which is the whole point of the
 * read-only design — an expired customer can always read their data, take a
 * backup and paste in the renewal the vendor sent them.
 */
class DesktopLicenceController extends Controller
{
    public function show(Request $request, LicenceService $licences): View
    {
        $this->ensureDesktopRequest($request);

        $status = $licences->status();
        $user = $request->user();

        return view('desktop.licence', [
            'status' => $status,
            'history' => $user?->isOwner() ? $this->history($licences) : collect(),
            'canManageLicence' => $user === null || $user->isOwner(),
            'graceEndsOn' => $status->graceEndsAt()?->toDateString(),
        ]);
    }

    public function store(Request $request, LicenceService $licences): RedirectResponse
    {
        $this->ensureDesktopRequest($request);

        $validated = $request->validate([
            'licence_string' => ['required', 'string', 'max:8192'],
        ]);

        try {
            $stored = $licences->install($validated['licence_string']);
        } catch (LicenceException $exception) {
            throw ValidationException::withMessages([
                'licence_string' => __($exception->messageKey()),
            ]);
        }

        $user = $request->user();

        if ($user === null) {
            // A guest reached this page because the copy was locked and they
            // cannot sign in. Now that a licence is in, send them to login.
            return redirect()->route('login')->with('status', __('app.licence.renewed', [
                'date' => $stored->expires_at->toDateString(),
            ]));
        }

        return redirect()->route('desktop.licence')
            ->with('status', __('app.licence.renewed', ['date' => $stored->expires_at->toDateString()]));
    }

    /**
     * @return list<array{licence: InstalledLicence, state: string, active: bool}>
     */
    private function history(LicenceService $licences): array
    {
        $now = CarbonImmutable::now('Asia/Yangon');
        $activeId = $licences->status()->licence?->id;

        return InstalledLicence::query()->orderByDesc('id')->get()
            ->map(function (InstalledLicence $stored) use ($now, $activeId): array {
                try {
                    $state = $stored->isRevoked()
                        ? Licence::STATE_EXPIRED
                        : $stored->licence()->statusAt($now);
                } catch (LicenceException) {
                    $state = Licence::STATE_INVALID;
                }

                return ['licence' => $stored, 'state' => $state, 'active' => $stored->id === $activeId];
            })
            ->all();
    }

    private function ensureDesktopRequest(Request $request): void
    {
        // Licence enforcement only exists for the desktop build; a server
        // install keeps its normal routes and must not expose this screen.
        abort_unless(
            config('app.desktop_mode')
            && config('database.default') === 'sqlite'
            && in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true),
            404
        );
    }
}
