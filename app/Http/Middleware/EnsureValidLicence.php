<?php

namespace App\Http\Middleware;

use App\Services\Licence\LicenceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs an expired desktop copy in read-only mode.
 *
 * The customer's data is never held hostage: every page can still be opened,
 * the SQL backup and restore screen keeps working, and a signed renewal can
 * still be pasted in. Only writes that change the business are refused, which
 * keeps a lapsed copy usable as a viewer until the vendor replies.
 *
 * The VPS install is not affected: a server runs MySQL, and only a desktop
 * install (desktop mode plus SQLite) is licensed at all.
 */
class EnsureValidLicence
{
    /**
     * Writes that stay open while the licence is locked. Everything here is
     * either the way out of the lock, or the way to keep the data safe.
     */
    private const ALWAYS_ALLOWED = [
        'desktop.licence.store',
        'desktop.owner-setup.store',
        'admin.data-backup.store',
        'admin.data-backup.restore',
        'admin.data-backup.download',
        'admin.data-backup.destroy',
        'admin.business-move.export',
        'admin.business-move.import.preview',
        'admin.business-move.import.apply',
        'logout',
        'preferences.locale',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! LicenceService::enforcedHere()) {
            return $next($request);
        }

        $licence = app(LicenceService::class);
        $status = $licence->status();

        if ($status->isWritable()) {
            $licence->touchLastSeen();

            return $next($request);
        }

        if ($this->isAllowed($request)) {
            $licence->touchLastSeen();

            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('app.licence.locked'),
                'licence_status' => $status->state,
            ], Response::HTTP_LOCKED);
        }

        return redirect()->route('desktop.licence')
            ->with('licence_locked', $status->state);
    }

    private function isAllowed(Request $request): bool
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        return in_array((string) $request->route()?->getName(), self::ALWAYS_ALLOWED, true);
    }
}
