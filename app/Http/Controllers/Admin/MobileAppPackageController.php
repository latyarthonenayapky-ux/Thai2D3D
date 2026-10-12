<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MobileAppPackage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Publishes the Agent mobile app. Kept admin-only on purpose: the APK is handed
 * to Agents by the admin, so no operator, Agent or guest may pull it from here.
 */
class MobileAppPackageController extends Controller
{
    private const DISK = 'local';

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeBusinessAdmin($request);

        $validated = $request->validate([
            'package' => [
                'required',
                'file',
                'max:122880',
                'extensions:apk',
                'mimetypes:application/vnd.android.package-archive,application/java-archive,application/zip,application/octet-stream',
            ],
            'version' => ['nullable', 'string', 'max:32'],
        ]);

        $directory = 'mobile-apps/'.$request->user()->id;

        /** @var UploadedFile $upload */
        $upload = $validated['package'];

        if ($upload->storeAs($directory, 'agent-app.apk', ['disk' => self::DISK]) === false) {
            return back()->withErrors(['package' => 'The upload could not be stored on this server.']);
        }

        $package = MobileAppPackage::query()->updateOrCreate(
            ['admin_id' => $request->user()->id],
            [
                'stored_path' => $directory.'/agent-app.apk',
                'file_name' => $this->sanitizeName($upload->getClientOriginalName()),
                'version' => $validated['version'] ?? null,
                'size_bytes' => $upload->getSize() ?: 0,
                'published_at' => now(),
            ]
        );

        return back()->with('status', 'Mobile app published'.$this->describe($package));
    }

    public function download(Request $request): StreamedResponse
    {
        $this->authorizeBusinessAdmin($request);

        $package = MobileAppPackage::query()->where('admin_id', $request->user()->id)->first();

        abort_unless($package !== null && $package->hasFile(), 404, 'No mobile app has been published yet.');

        return Storage::disk(self::DISK)->download(
            $package->stored_path,
            $this->attachmentName($package, $request->user()),
            ['Content-Type' => 'application/vnd.android.package-archive']
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authorizeBusinessAdmin($request);

        $package = MobileAppPackage::query()->where('admin_id', $request->user()->id)->first();

        if ($package !== null) {
            if ($package->stored_path !== null) {
                Storage::disk(self::DISK)->delete($package->stored_path);
            }

            $package->delete();
        }

        return back()->with('status', 'Mobile app removed. Agents can no longer download it.');
    }

    private function describe(MobileAppPackage $package): string
    {
        $parts = [];

        if ($package->version) {
            $parts[] = 'v'.$package->version;
        }

        $parts[] = number_format($package->size_bytes / 1048576, 1).' MB';

        return $parts === [] ? '' : ' ('.implode(', ', $parts).').';
    }

    private function sanitizeName(string $name): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '-', $name) ?? '';

        return $clean === '' ? 'agent-app.apk' : $clean;
    }

    /**
     * Every business publishes its own APK, so the saved file has to say whose it
     * is: a phone that keeps build files from more than one office needs to tell
     * them apart before it installs one.
     */
    private function attachmentName(MobileAppPackage $package, User $admin): string
    {
        $label = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $admin->name) ?? '', '-');
        // A Burmese business name has nothing left after ASCII sanitising, so the
        // admin id — which is always unique — labels the file instead.
        $owner = $label === '' ? 'admin-'.$admin->id : strtolower($label);
        $version = $package->version ? '-'.strtolower($this->sanitizeName($package->version)) : '';

        return $owner.'-agent-app'.$version.'.apk';
    }

    private function authorizeBusinessAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }
}
