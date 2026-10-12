<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Agent;
use App\Models\Round;
use App\Models\SaleDetail;
use App\Models\SaleInput;
use App\Models\AgentSession;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Restore runs DDL and VACUUM, so it cannot live inside the transaction that
 * RefreshDatabase opens. This suite uses a temporary file database instead.
 */
class DatabaseBackupRestoreTest extends TestCase
{
    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = storage_path('app/restore-test-'.uniqid('wya', true).'.sqlite');
        config(['database.connections.sqlite.database' => $this->database]);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        File::delete($this->database);
        File::deleteDirectory(app(DatabaseBackupService::class)->directory());
        config(['database.connections.sqlite.database' => ':memory:']);

        parent::tearDown();
    }

    public function test_a_backup_is_replayed_and_a_pre_restore_safety_copy_is_kept(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $agent = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'A-01',
            'agent_name' => 'Restore Probe',
            'phone' => '+95 9 0000 1111',
            'commission_2d_percent' => 5,
            'commission_3d_percent' => 0,
            'status' => 'active',
        ]);
        $round = Round::query()->create([
            'admin_id' => $admin->id,
            'round_date' => '2026-10-12',
            'round_no' => 1,
            'start_time' => '09:00:00',
            'close_time' => '12:00:00',
            'number_limit' => 500,
            'number_limits' => ['17'],
            'status' => 'open',
        ]);
        $session = AgentSession::openSession($agent, $round, $admin->id);
        $input = SaleInput::query()->create([
            'agent_session_id' => $session->id,
            'operator_id' => $admin->id,
            'original_input' => '121500',
            'normalized_input' => '121500',
            'input_type' => 'NORMAL',
            'number_count' => 1,
            'amount' => 500,
            'total_amount' => 500,
            'status' => 'accepted',
        ]);
        $detail = SaleDetail::query()->create([
            'sale_input_id' => $input->id,
            'number' => '12',
            'amount' => 500,
            'status' => 'accepted',
            'is_excluded' => false,
        ]);

        $service = app(DatabaseBackupService::class);
        $backup = $service->create();

        SaleDetail::query()->forceDelete();
        SaleInput::query()->forceDelete();
        AgentSession::query()->forceDelete();
        Round::query()->forceDelete();
        $agent->forceDelete();

        $this->assertSame(0, Agent::query()->count());
        $this->assertSame(0, SaleDetail::query()->count());

        $safety = $service->restore($service->resolve($backup));

        $this->assertDatabaseHas('agents', ['id' => $agent->id, 'agent_name' => 'Restore Probe']);
        $this->assertDatabaseHas('rounds', ['id' => $round->id, 'status' => 'open']);
        $this->assertDatabaseHas('sale_details', ['id' => $detail->id, 'number' => '12', 'amount' => 500]);
        $this->assertNotSame($backup, $safety);
        $this->assertFileExists($service->directory().'/'.$safety);
        $this->assertCount(2, $service->all());
    }

    public function test_restore_records_activity_and_the_controller_flashes_the_safety_copy(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $service = app(DatabaseBackupService::class);
        $backup = $service->create();

        $this->actingAs($admin)
            ->post(route('admin.data-backup.restore'), ['confirm' => 'on', 'stored' => $backup])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertStringContainsString('safety copy was saved as ', (string) session('status'));
        $this->assertSame(1, ActivityLog::query()->where('action', 'data_backup.restored')->count());
        $this->assertCount(2, $service->all());
    }
}
