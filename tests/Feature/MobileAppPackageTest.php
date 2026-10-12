<?php

namespace Tests\Feature;

use App\Models\MobileAppPackage;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileAppPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_publishes_the_apk_and_downloads_it_again(): void
    {
        Storage::fake('local');
        $admin = $this->businessAdmin();

        $this->actingAs($admin)
            ->post(route('admin.settings.mobile-app.store'), [
                'package' => UploadedFile::fake()->create('wya-agent-1.1.0.apk', 600, 'application/vnd.android.package-archive'),
                'version' => '1.1.0',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $package = MobileAppPackage::query()->where('admin_id', $admin->id)->sole();
        $this->assertSame('1.1.0', $package->version);
        $this->assertSame('wya-agent-1.1.0.apk', $package->file_name);
        Storage::disk('local')->assertExists($package->stored_path);

        // The private disk keeps the file out of public/, so a direct URL 404s.
        Storage::disk('public')->assertMissing('mobile-apps/'.$admin->id.'/agent-app.apk');

        $this->actingAs($admin)
            ->get(route('admin.settings.mobile-app.download'))
            ->assertOk()
            // The saved file is named after the business, not the upload, because
            // an Agent phone can hold builds issued by more than one office.
            ->assertDownload('wya-pearl-office-agent-app-1.1.0.apk');

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('wya-agent-1.1.0.apk')
            ->assertSee('1.1.0');
    }

    public function test_uploading_again_replaces_the_published_app(): void
    {
        Storage::fake('local');
        $admin = $this->businessAdmin();

        $this->actingAs($admin)->post(route('admin.settings.mobile-app.store'), [
            'package' => UploadedFile::fake()->create('old.apk', 100, 'application/vnd.android.package-archive'),
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.settings.mobile-app.store'), [
            'package' => UploadedFile::fake()->create('new-build.apk', 200, 'application/vnd.android.package-archive'),
            'version' => '1.2.0',
        ])->assertSessionHasNoErrors();

        $package = MobileAppPackage::query()->where('admin_id', $admin->id)->sole();
        $this->assertSame('new-build.apk', $package->file_name);
        $this->assertSame('1.2.0', $package->version);
        $this->assertSame(1, MobileAppPackage::query()->count());
    }

    public function test_a_non_apk_file_is_rejected(): void
    {
        Storage::fake('local');
        $admin = $this->businessAdmin();

        $this->actingAs($admin)
            ->post(route('admin.settings.mobile-app.store'), [
                'package' => UploadedFile::fake()->create('instructions.pdf', 50, 'application/pdf'),
            ])
            ->assertSessionHasErrors('package');

        $this->assertSame(0, MobileAppPackage::query()->count());
        $this->assertNull(session('status'));
    }

    public function test_removing_the_app_deletes_the_stored_file(): void
    {
        Storage::fake('local');
        $admin = $this->businessAdmin();

        $this->actingAs($admin)->post(route('admin.settings.mobile-app.store'), [
            'package' => UploadedFile::fake()->create('wya.apk', 100, 'application/vnd.android.package-archive'),
        ])->assertSessionHasNoErrors();

        $path = MobileAppPackage::query()->sole()->stored_path;

        $this->actingAs($admin)
            ->delete(route('admin.settings.mobile-app.destroy'))
            ->assertSessionHas('status');

        $this->assertSame(0, MobileAppPackage::query()->count());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_operators_and_agents_cannot_reach_the_apk(): void
    {
        Storage::fake('local');
        $admin = $this->businessAdmin();
        $this->actingAs($admin)->post(route('admin.settings.mobile-app.store'), [
            'package' => UploadedFile::fake()->create('wya.apk', 100, 'application/vnd.android.package-archive'),
        ])->assertSessionHasNoErrors();

        $operator = User::factory()->create(['role' => 'operator', 'admin_id' => $admin->id, 'status' => true]);
        $agentUser = User::factory()->create(['role' => 'agent', 'admin_id' => $admin->id, 'status' => true]);

        foreach ([$operator, $agentUser] as $blocked) {
            $this->actingAs($blocked)
                ->get(route('admin.settings.mobile-app.download'))
                ->assertForbidden();
            $this->actingAs($blocked)
                ->post(route('admin.settings.mobile-app.store'), [
                    'package' => UploadedFile::fake()->create('other.apk', 10, 'application/vnd.android.package-archive'),
                ])
                ->assertForbidden();
        }

        $this->assertSame(1, MobileAppPackage::query()->count());
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('admin.settings.mobile-app.download'))->assertRedirect(route('login'));
        $this->post(route('admin.settings.mobile-app.store'))->assertRedirect(route('login'));
    }

    public function test_download_without_a_published_app_is_not_found(): void
    {
        $admin = $this->businessAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.mobile-app.download'))
            ->assertNotFound();
    }

    public function test_each_admin_only_reaches_their_own_published_app(): void
    {
        Storage::fake('local');
        $pearl = $this->businessAdmin('WYA Pearl Office');
        $thida = $this->businessAdmin('Thida Office');

        $this->actingAs($pearl)->post(route('admin.settings.mobile-app.store'), [
            'package' => UploadedFile::fake()->create('pearl.apk', 100, 'application/vnd.android.package-archive'),
            'version' => '1.0.0',
        ])->assertSessionHasNoErrors();

        $this->actingAs($thida)
            ->get(route('admin.settings.mobile-app.download'))
            ->assertNotFound();
        $this->actingAs($thida)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertDontSee('pearl.apk');
        $this->actingAs($thida)
            ->delete(route('admin.settings.mobile-app.destroy'))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, MobileAppPackage::query()->count());
        Storage::disk('local')->assertExists('mobile-apps/'.$pearl->id.'/agent-app.apk');

        $this->actingAs($thida)->post(route('admin.settings.mobile-app.store'), [
            'package' => UploadedFile::fake()->create('thida.apk', 100, 'application/vnd.android.package-archive'),
            'version' => '2.0.0',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, MobileAppPackage::query()->count());
        $this->actingAs($thida)
            ->get(route('admin.settings.mobile-app.download'))
            ->assertOk()
            ->assertDownload('thida-office-agent-app-2.0.0.apk');
        $this->actingAs($pearl)
            ->get(route('admin.settings.mobile-app.download'))
            ->assertOk()
            ->assertDownload('wya-pearl-office-agent-app-1.0.0.apk');
    }

    public function test_a_non_ascii_business_name_labels_the_file_with_the_admin_id(): void
    {
        Storage::fake('local');
        $admin = $this->businessAdmin('ရွှေတောင်ကြီး မှတ်ပုံတင်');

        $this->actingAs($admin)->post(route('admin.settings.mobile-app.store'), [
            'package' => UploadedFile::fake()->create('build.apk', 100, 'application/vnd.android.package-archive'),
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->get(route('admin.settings.mobile-app.download'))
            ->assertOk()
            ->assertDownload('admin-'.$admin->id.'-agent-app.apk');
    }

    private function businessAdmin(string $name = 'WYA Pearl Office'): User
    {
        $admin = User::factory()->create(['name' => $name, 'role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($admin);

        return $admin;
    }
}
