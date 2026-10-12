<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Agent;
use App\Services\DatabaseBackupService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DataBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(app(DatabaseBackupService::class)->directory());

        parent::tearDown();
    }

    public function test_an_operator_cannot_reach_data_backup(): void
    {
        $operator = User::factory()->create(['role' => 'operator', 'status' => true]);

        $this->actingAs($operator)->get(route('admin.data-backup.index'))->assertForbidden();
        $this->actingAs($operator)->post(route('admin.data-backup.store'))->assertForbidden();
        $this->actingAs($operator)->post(route('admin.data-backup.restore'))->assertForbidden();
    }

    public function test_creating_a_backup_writes_a_portable_dump_and_records_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'A-01',
            'agent_name' => 'Central Agent',
            'commission_2d_percent' => 5,
            'commission_3d_percent' => 0,
            'status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('admin.data-backup.store'))->assertRedirect()->assertSessionHas('status');

        $files = glob(app(DatabaseBackupService::class)->directory().'/wya-backup-*.sql');
        $this->assertCount(1, $files);

        $dump = (string) file_get_contents($files[0]);
        $this->assertStringContainsString(DatabaseBackupService::HEADER, $dump);
        $this->assertStringContainsString('engine=sqlite', $dump);
        $this->assertStringContainsString('Central Agent', $dump);
        $this->assertStringNotContainsString('mysqldump', $dump);

        $this->assertSame(1, ActivityLog::query()->where('action', 'data_backup.created')->count());

        $this->actingAs($admin)
            ->get(route('admin.data-backup.index'))
            ->assertOk()
            ->assertSee(basename($files[0]));
    }

    public function test_a_stored_backup_can_be_downloaded(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $name = app(DatabaseBackupService::class)->create();

        $response = $this->actingAs($admin)->get(route('admin.data-backup.download', $name))->assertOk();

        $this->assertSame('application/sql', $response->headers->get('content-type'));
        $this->assertStringContainsString(DatabaseBackupService::HEADER, $response->streamedContent());
    }

    public function test_a_stored_backup_can_be_removed(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $service = app(DatabaseBackupService::class);
        $name = $service->create();

        $this->actingAs($admin)->delete(route('admin.data-backup.destroy', $name))->assertRedirect()->assertSessionHas('status');

        $this->assertFileDoesNotExist($service->directory().'/'.$name);
        $this->assertSame(1, ActivityLog::query()->where('action', 'data_backup.deleted')->count());
    }

    public function test_path_traversal_in_a_backup_name_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);

        $this->actingAs($admin)->get(route('admin.data-backup.download', '../../users.sqlite'))->assertNotFound();
        $this->actingAs($admin)->delete(route('admin.data-backup.destroy', '../../users.sqlite'))->assertNotFound();
    }

    public function test_restoring_requires_the_confirmation_box(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $service = app(DatabaseBackupService::class);
        $name = $service->create();

        $this->actingAs($admin)
            ->post(route('admin.data-backup.restore'), ['stored' => $name])
            ->assertSessionHasErrors('confirm');

        $this->assertFileExists($service->directory().'/'.$name);
    }

    public function test_restoring_needs_either_an_upload_or_a_stored_file(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);

        $this->actingAs($admin)
            ->post(route('admin.data-backup.restore'), ['confirm' => 'on'])
            ->assertSessionHasErrors('upload');
    }

    public function test_a_file_that_this_application_did_not_create_is_refused(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);

        $this->actingAs($admin)
            ->post(route('admin.data-backup.restore'), [
                'confirm' => 'on',
                'upload' => UploadedFile::fake()->createWithContent('other.sql', "DROP TABLE users;\n"),
            ])
            ->assertSessionHasErrors('restore');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_a_backup_from_another_engine_is_refused(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);

        $dump = "-- ".DatabaseBackupService::HEADER." engine=mysql created=2026-10-12T00:00:00+06:30\nSET FOREIGN_KEY_CHECKS = 0;\n";

        $this->actingAs($admin)
            ->post(route('admin.data-backup.restore'), [
                'confirm' => 'on',
                'upload' => UploadedFile::fake()->createWithContent('mysql.sql', $dump),
            ])
            ->assertSessionHasErrors('restore');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
