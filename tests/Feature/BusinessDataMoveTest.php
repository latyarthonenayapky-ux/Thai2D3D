<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use App\Services\AgentLedger;
use App\Services\BusinessDataExport;
use App\Services\BusinessDataImport;
use App\Services\InitializeAdminBusiness;
use App\Support\BusinessDataSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BusinessDataMoveTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (glob(storage_path('app/imports/wya-import-*.json')) ?: [] as $stale) {
            @unlink($stale);
        }

        parent::tearDown();
    }

    public function test_the_export_only_carries_one_business(): void
    {
        $mine = $this->admin('mine@example.test');
        $theirs = $this->admin('theirs@example.test');

        $this->agent($mine, 'kh001');
        $this->agent($theirs, 'ky002');

        $bundle = app(BusinessDataExport::class)->build((int) $mine->id);

        $this->assertSame(['kh001'], array_column($bundle['tables']['agents'], 'agent_code'));
        $this->assertSame($mine->id, (int) $bundle['business']['id']);
        $this->assertSame('mine@example.test', $bundle['business']['email']);
        $this->assertArrayNotHasKey('agent_access_tokens', $bundle['tables']);
    }

    public function test_a_dry_run_reports_the_plan_and_writes_nothing(): void
    {
        $admin = $this->admin();
        $this->business($admin);

        $bundle = app(BusinessDataExport::class)->build((int) $admin->id);
        $this->wipe($admin);
        $wiped = $this->counts();

        $report = app(BusinessDataImport::class)->run($bundle, (int) $admin->id, true);

        $this->assertFalse($report['applied']);
        $this->assertTrue($report['dry_run']);
        $this->assertGreaterThan(0, $report['totals']['new']);
        $this->assertSame($wiped, $this->counts());
    }

    public function test_a_business_moves_with_every_foreign_key_re_keyed(): void
    {
        $admin = $this->admin();
        $this->business($admin);
        $totals = $this->totals();

        $bundle = app(BusinessDataExport::class)->build((int) $admin->id);
        $this->wipe($admin);
        $this->assertSame(0.0, $this->totals()['sale_amount']);

        $report = app(BusinessDataImport::class)->run($bundle, (int) $admin->id);

        $this->assertSame([], $report['conflicts']);
        $this->assertTrue($report['applied']);
        $this->assertSame(0, $report['totals']['orphan']);
        $this->assertSame(0, $report['totals']['skipped']);

        // Every imported row hangs off this Admin again, and the money adds up.
        $this->assertSame($this->totals(), $totals);
        $this->assertSame([], $this->rowsOutsideBusiness($admin));

        $session = DB::table('agent_sessions')->sole();
        $this->assertSame((int) DB::table('rounds')->sole()->id, (int) $session->round_id);
        $this->assertSame((int) DB::table('agents')->sole()->id, (int) $session->agent_id);
        $this->assertSame((int) DB::table('users')->where('role', 'operator')->sole()->id, (int) $session->operator_id);

        $sale = DB::table('sale_inputs')->sole();
        $this->assertSame((int) $session->id, (int) $sale->agent_session_id);
        $this->assertSame(2, DB::table('sale_details')->where('sale_input_id', $sale->id)->count());

        $this->assertSame('14', DB::table('round_results')->sole()->result_2d);
        $this->assertSame('432', DB::table('three_digit_draw_results')->sole()->result);
        $this->assertSame(30000.0, app(AgentLedger::class)->balance(Agent::query()->sole()));
    }

    public function test_importing_the_same_bundle_twice_adds_nothing(): void
    {
        $admin = $this->admin();
        $this->business($admin);

        $export = app(BusinessDataExport::class);
        $import = app(BusinessDataImport::class);
        $bundle = $export->build((int) $admin->id);

        $this->wipe($admin);

        $first = $import->run($bundle, (int) $admin->id);
        $after = $this->counts();

        $second = $import->run($bundle, (int) $admin->id);

        $this->assertGreaterThan(0, $first['totals']['new']);
        $this->assertSame(0, $second['totals']['new']);
        $this->assertSame($after, $this->counts());
        $this->assertGreaterThan(0, $second['totals']['matched']);
    }

    public function test_a_code_that_already_belongs_to_another_business_refuses_the_whole_run(): void
    {
        $source = $this->admin('source@example.test');
        $this->business($source);

        $bundle = app(BusinessDataExport::class)->build((int) $source->id);

        // The bundle leaves for a hosted server where a different business has
        // already taken that Agent code.
        $this->wipe($source);
        $stranger = $this->admin('stranger@example.test');
        $this->agent($stranger, 'kh001');

        $target = $this->admin('target@example.test');

        $before = $this->counts();
        $report = app(BusinessDataImport::class)->run($bundle, (int) $target->id);

        $this->assertFalse($report['applied']);
        $this->assertNotEmpty($report['conflicts']);
        $this->assertSame('Agents', $report['conflicts'][0]['table']);
        $this->assertSame('kh001', $report['conflicts'][0]['key']);
        $this->assertGreaterThan(0, $report['totals']['orphan'], 'the rows under it are reported too');
        $this->assertSame($before, $this->counts());

        // The stranger keeps its own Agent and nothing was attached to it.
        $this->assertSame(1, DB::table('agents')->where('admin_id', $stranger->id)->count());
        $this->assertSame(0, DB::table('agents')->where('admin_id', $target->id)->count());
    }

    public function test_the_admin_can_download_and_upload_a_bundle_from_the_backup_page(): void
    {
        $admin = $this->admin();
        $this->business($admin);

        $this->get(route('admin.business-move.export'))->assertRedirect(route('login'));

        $operator = User::factory()->create(['role' => 'operator', 'admin_id' => $admin->id, 'status' => true]);
        $this->actingAs($operator)->get(route('admin.business-move.export'))->assertForbidden();

        $this->actingAs($admin);

        $response = $this->get(route('admin.business-move.export'));
        $response->assertOk()->assertHeader('Content-Type', 'application/json');
        $this->assertStringStartsWith('attachment; filename="wya-business-', (string) $response->headers->get('Content-Disposition'));

        $json = $response->getContent();
        $this->assertStringContainsString('WYA_BUSINESS_EXPORT_V1', $json);

        $this->wipe($admin);
        $this->assertSame(0.0, $this->totals()['sale_amount']);

        $this->post(route('admin.business-move.import.preview'), [
            'bundle' => UploadedFile::fake()->createWithContent('business.json', $json),
        ])->assertOk()->assertSee('2D sales', false);

        $this->assertSame(0.0, $this->totals()['sale_amount'], 'the preview must not write');

        $token = session('business_import_token');
        $this->assertFileExists(storage_path('app/imports/'.$token));

        $this->post(route('admin.business-move.import.apply'), ['token' => $token, 'confirm' => 'on'])
            ->assertRedirect(route('admin.data-backup.index'))
            ->assertSessionHas('status');

        $this->assertFileDoesNotExist(storage_path('app/imports/'.$token));
        $this->assertGreaterThan(0, $this->totals()['sale_amount']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'business_import']);
    }

    public function test_a_forged_or_expired_token_is_refused(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->post(route('admin.business-move.import.apply'), [
            'token' => 'wya-import-20261014-010101-abcdef.json',
            'confirm' => 'on',
        ])->assertSessionHasErrors('move');

        $this->assertDatabaseMissing('activity_logs', ['action' => 'business_import']);
    }

    /**
     * A business that touches every part of the bundle: limits, a 2D sale with
     * two numbers, a result, a 3D draw sale and the cash ledger.
     */
    private function business(User $admin): void
    {
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => true,
            'admin_id' => $admin->id,
            'email' => 'op'.$admin->id.'@example.test',
        ]);

        $agent = $this->agent($admin, 'kh001');

        $roundId = DB::table('rounds')->insertGetId([
            'admin_id' => $admin->id,
            'round_date' => '2026-10-14',
            'round_no' => 1,
            'start_time' => '09:00:00',
            'close_time' => '15:00:00',
            'status' => 'closed',
            'number_limit' => 500,
            'amount_limit_all' => 100000,
            'manual_reopen' => false,
            'opened_at' => '2026-10-14 09:00:00',
            'created_at' => '2026-10-14 08:55:00',
            'updated_at' => '2026-10-14 15:10:00',
        ]);

        DB::table('hot_numbers')->insert([
            'round_id' => $roundId,
            'number' => '17',
            'created_at' => '2026-10-14 08:56:00',
            'updated_at' => '2026-10-14 08:56:00',
        ]);

        DB::table('agent_round_settings')->insert([
            'agent_id' => $agent->id,
            'round_id' => $roundId,
            'operator_id' => $operator->id,
            'number_limit' => 40,
            'amount_limit' => 25000,
            'amount_limit_mode' => 'warn',
            'created_at' => '2026-10-14 08:56:00',
            'updated_at' => '2026-10-14 08:56:00',
        ]);

        DB::table('operator_round_settings')->insert([
            'round_id' => $roundId,
            'operator_id' => $operator->id,
            'amount_limit' => 90000,
            'created_at' => '2026-10-14 08:56:00',
            'updated_at' => '2026-10-14 08:56:00',
        ]);

        $sessionId = DB::table('agent_sessions')->insertGetId([
            'round_id' => $roundId,
            'agent_id' => $agent->id,
            'operator_id' => $operator->id,
            'session_code' => '261014-WYA-01',
            'status' => 'closed',
            'opened_at' => '2026-10-14 09:01:00',
            'closed_at' => '2026-10-14 14:59:00',
            'created_at' => '2026-10-14 09:01:00',
            'updated_at' => '2026-10-14 14:59:00',
        ]);

        $saleId = DB::table('sale_inputs')->insertGetId([
            'agent_session_id' => $sessionId,
            'operator_id' => $operator->id,
            'original_input' => '17=1500 28=2000',
            'normalized_input' => '17=1500 28=2000',
            'input_type' => 'number_set',
            'code' => null,
            'number_argument' => '17,28',
            'number_count' => 2,
            'amount' => 1750,
            'total_amount' => 3500,
            'status' => 'accepted',
            'source' => 'web',
            'created_at' => '2026-10-14 10:00:00',
            'updated_at' => '2026-10-14 10:00:00',
        ]);

        foreach ([['17', 1500], ['28', 2000]] as [$number, $amount]) {
            DB::table('sale_details')->insert([
                'sale_input_id' => $saleId,
                'number' => $number,
                'amount' => $amount,
                'is_excluded' => false,
                'status' => 'accepted',
                'created_at' => '2026-10-14 10:00:00',
                'updated_at' => '2026-10-14 10:00:00',
            ]);
        }

        $resultId = DB::table('round_results')->insertGetId([
            'admin_id' => $admin->id,
            'round_id' => $roundId,
            'result_2d' => '14',
            'result_3d' => null,
            'updated_by' => $admin->id,
            'created_at' => '2026-10-14 15:31:00',
            'updated_at' => '2026-10-14 15:31:00',
        ]);

        DB::table('round_result_changes')->insert([
            'round_result_id' => $resultId,
            'changed_by' => $admin->id,
            'field' => 'result_2d',
            'old_value' => null,
            'new_value' => '14',
            'created_at' => '2026-10-14 15:31:00',
            'updated_at' => '2026-10-14 15:31:00',
        ]);

        $drawId = DB::table('three_digit_draws')->insertGetId([
            'admin_id' => $admin->id,
            'draw_date' => '2026-10-14',
            'open_time' => '09:00:00',
            'result_time' => '15:30:00',
            'number_limit' => 200,
            'status' => 'closed',
            'created_at' => '2026-10-14 08:50:00',
            'updated_at' => '2026-10-14 15:35:00',
        ]);

        $drawSettingId = DB::table('three_digit_draw_agent_settings')->insertGetId([
            'three_digit_draw_id' => $drawId,
            'agent_id' => $agent->id,
            'handler_id' => $operator->id,
            'amount_limit' => 50000,
            'created_at' => '2026-10-14 08:58:00',
            'updated_at' => '2026-10-14 08:58:00',
        ]);

        $drawSaleId = DB::table('three_digit_sale_inputs')->insertGetId([
            'agent_session_id' => null,
            'three_digit_draw_id' => $drawId,
            'agent_id' => $agent->id,
            'draw_agent_setting_id' => $drawSettingId,
            'operator_id' => $operator->id,
            'original_input' => 'A=123=500',
            'normalized_input' => '123=500',
            'input_type' => 'number_set',
            'code' => 'A',
            'number_count' => 1,
            'amount' => 500,
            'total_amount' => 500,
            'status' => 'accepted',
            'source' => 'web',
            'created_at' => '2026-10-14 11:00:00',
            'updated_at' => '2026-10-14 11:00:00',
        ]);

        DB::table('three_digit_sale_details')->insert([
            'three_digit_sale_input_id' => $drawSaleId,
            'number' => '123',
            'amount' => 500,
            'status' => 'accepted',
            'created_at' => '2026-10-14 11:00:00',
            'updated_at' => '2026-10-14 11:00:00',
        ]);

        $drawResultId = DB::table('three_digit_draw_results')->insertGetId([
            'three_digit_draw_id' => $drawId,
            'admin_id' => $admin->id,
            'result' => '432',
            'updated_by' => $admin->id,
            'created_at' => '2026-10-14 15:35:00',
            'updated_at' => '2026-10-14 15:35:00',
        ]);

        DB::table('three_digit_draw_result_changes')->insert([
            'three_digit_draw_result_id' => $drawResultId,
            'changed_by' => $admin->id,
            'old_value' => null,
            'new_value' => '432',
            'created_at' => '2026-10-14 15:35:00',
            'updated_at' => '2026-10-14 15:35:00',
        ]);

        DB::table('agent_ledger_entries')->insert([
            'admin_id' => $admin->id,
            'agent_id' => $agent->id,
            'type' => 'deposit',
            'amount' => 30000,
            'occurs_on' => '2026-10-14',
            'note' => 'Float for the week',
            'recorded_by' => $admin->id,
            'created_at' => '2026-10-14 09:30:00',
            'updated_at' => '2026-10-14 09:30:00',
        ]);
    }

    private function admin(string $email = 'owner@example.test'): User
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => true,
            'email' => $email,
        ]);

        app(InitializeAdminBusiness::class)->initialize($admin);

        return $admin;
    }

    private function agent(User $admin, string $code): Agent
    {
        return Agent::query()->create([
            'agent_code' => $code,
            'agent_name' => 'Sample Agent',
            'admin_id' => $admin->id,
            'phone' => '09xxxxxxxx',
            'status' => 'active',
        ]);
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $tables = array_keys(BusinessDataSchema::tables());
        $counts = [];

        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    /** @return array{sale_amount: float, sale_numbers: int} */
    private function totals(): array
    {
        return [
            'sale_amount' => (float) DB::table('sale_inputs')->sum('total_amount')
                + (float) DB::table('three_digit_sale_inputs')->sum('total_amount'),
            'sale_numbers' => DB::table('sale_details')->count() + DB::table('three_digit_sale_details')->count(),
        ];
    }

    /** Rows that do not hang off the given Admin - the bug this tool must never ship. */
    private function rowsOutsideBusiness(User $admin): array
    {
        $stray = [];

        foreach (BusinessDataSchema::tables() as $table => $spec) {
            if ($spec['owner'] === null || ! in_array($spec['owner'], $this->columns($table), true)) {
                continue;
            }

            if ($table === 'users') {
                $found = DB::table('users')
                    ->where('role', 'operator')
                    ->where('admin_id', '!=', $admin->id)
                    ->pluck('id')
                    ->all();
            } else {
                $found = DB::table($table)->where($spec['owner'], '!=', $admin->id)->pluck('id')->all();
            }

            if ($found !== []) {
                $stray[$table] = $found;
            }
        }

        return $stray;
    }

    /** @return array<int, string> */
    private function columns(string $table): array
    {
        return Schema::getColumnListing($table);
    }

    /** Leave only the Admin account itself, like a fresh install on the VPS. */
    private function wipe(User $admin): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        foreach (array_reverse(array_keys(BusinessDataSchema::tables())) as $table) {
            if ($table === 'users') {
                DB::table('users')->where('id', '!=', $admin->id)->delete();

                continue;
            }

            DB::table($table)->delete();
        }

        DB::table('users')->where('id', $admin->id)->update(['admin_id' => null]);

        DB::statement('PRAGMA foreign_keys = ON');
    }
}
