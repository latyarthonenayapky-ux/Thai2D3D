<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentSession;
use App\Models\Round;
use App\Models\SaleInput;
use App\Models\ThreeDigitDraw;
use App\Models\ThreeDigitSaleInput;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleBatchStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_saves_multiple_2d_inputs_in_one_batch_request(): void
    {
        [$admin, $operator, $round, $agent, $session] = $this->createBusiness();
        $uuid = '11111111-1111-4111-8111-111111111111';

        $response = $this->actingAs($operator)
            ->postJson(route('operator.sales.store-batch', $session), [
                'sale_type' => '2d',
                'inputs' => [
                    ['input' => '121000', 'client_uuid' => $uuid],
                    ['input' => '341000'],
                    ['input' => 'a1000'],
                ],
            ]);

        $response->assertOk();
        $statuses = collect($response->json('results'))->pluck('status')->all();
        $this->assertSame(['saved', 'saved', 'invalid'], $statuses);
        $this->assertSame($uuid, $response->json('results.0.client_uuid'));
        $this->assertNotEmpty($response->json('results.0.details'));

        $this->assertDatabaseHas('sale_inputs', [
            'agent_session_id' => $session->id,
            'operator_id' => $operator->id,
            'original_input' => '121000',
            'client_uuid' => $uuid,
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('sale_inputs', [
            'agent_session_id' => $session->id,
            'original_input' => '341000',
            'status' => 'accepted',
        ]);
        $this->assertSame(2, SaleInput::query()->where('agent_session_id', $session->id)->count());
    }

    public function test_batch_save_is_idempotent_for_repeated_client_uuid(): void
    {
        [$admin, $operator, $round, $agent, $session] = $this->createBusiness();
        $uuid = '22222222-2222-4222-8222-222222222222';
        $payload = [
            'sale_type' => '2d',
            'inputs' => [['input' => '121000', 'client_uuid' => $uuid]],
        ];

        $this->actingAs($operator)->postJson(route('operator.sales.store-batch', $session), $payload)
            ->assertOk()
            ->assertJsonPath('results.0.status', 'saved');
        $this->actingAs($operator)->postJson(route('operator.sales.store-batch', $session), $payload)
            ->assertOk()
            ->assertJsonPath('results.0.status', 'saved');

        $this->assertSame(1, SaleInput::query()->where('agent_session_id', $session->id)->count());
    }

    public function test_batch_save_rejects_invalid_payloads(): void
    {
        [$admin, $operator, $round, $agent, $session] = $this->createBusiness();

        $this->actingAs($operator)
            ->postJson(route('operator.sales.store-batch', $session), ['inputs' => []])
            ->assertStatus(422);

        $inputs = [];
        for ($index = 0; $index < 201; $index++) {
            $inputs[] = ['input' => '121000'];
        }

        $this->actingAs($operator)
            ->postJson(route('operator.sales.store-batch', $session), [
                'sale_type' => '2d',
                'inputs' => $inputs,
            ])
            ->assertStatus(422);

        $this->assertSame(0, SaleInput::query()->count());
    }

    public function test_closed_round_rejects_batch_before_processing(): void
    {
        [$admin, $operator, $round, $agent, $session] = $this->createBusiness();
        $session->update(['status' => 'closed']);

        $this->actingAs($operator)
            ->postJson(route('operator.sales.store-batch', $session), [
                'sale_type' => '2d',
                'inputs' => [['input' => '121000']],
            ])
            ->assertStatus(409);

        $this->assertSame(0, SaleInput::query()->count());
    }

    public function test_other_business_operator_cannot_use_batch_on_foreign_session(): void
    {
        [$admin, $operator, $round, $agent, $session] = $this->createBusiness();
        $otherAdmin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($otherAdmin);
        $otherOperator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $otherAdmin->id,
            'status' => true,
        ]);

        $this->actingAs($otherOperator)
            ->postJson(route('operator.sales.store-batch', $session), [
                'sale_type' => '2d',
                'inputs' => [['input' => '121000']],
            ])
            ->assertNotFound();

        $this->assertSame(0, SaleInput::query()->count());
    }

    public function test_operator_saves_multiple_3d_inputs_in_one_batch_request(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00', 'Asia/Yangon'));

        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($admin);
        $operator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);
        $agent = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'D3-B1',
            'agent_name' => 'Batch three digit agent',
            'commission_3d_percent' => '10.00',
        ]);
        $draw = ThreeDigitDraw::query()->create([
            'admin_id' => $admin->id,
            'draw_date' => '2026-10-16',
            'open_time' => '00:00:00',
            'result_time' => '15:30:00',
            'number_limit' => 100,
            'status' => 'open',
        ]);

        $this->actingAs($operator)
            ->post(route('three-digit.sales.claim', [$draw, $agent]))
            ->assertRedirect();

        $uuid = '33333333-3333-4333-8333-333333333333';
        $response = $this->actingAs($operator)
            ->postJson(route('three-digit.sales.store-batch', [$draw, $agent]), [
                'inputs' => [
                    ['input' => '123500', 'client_uuid' => $uuid],
                    ['input' => 'a1000'],
                    ['input' => '456500'],
                ],
            ]);

        $response->assertOk();
        $this->assertSame(
            ['saved', 'invalid', 'saved'],
            collect($response->json('results'))->pluck('status')->all()
        );
        $this->assertNotEmpty($response->json('results.0.details'));

        $this->assertDatabaseHas('three_digit_sale_inputs', [
            'three_digit_draw_id' => $draw->id,
            'agent_id' => $agent->id,
            'operator_id' => $operator->id,
            'original_input' => '123500',
            'client_uuid' => $uuid,
            'status' => 'accepted',
        ]);
        $this->assertSame(2, ThreeDigitSaleInput::query()->count());

        Carbon::setTestNow();
    }

    private function createBusiness(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($admin);
        $operator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);
        $agent = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'B1-01',
            'agent_name' => 'Batch agent',
            'commission_2d_percent' => '0.00',
            'commission_3d_percent' => '0.00',
        ]);
        $round = Round::query()->create([
            'admin_id' => $admin->id,
            'round_date' => '2026-10-07',
            'round_no' => 1,
            'start_time' => '07:00:00',
            'close_time' => '09:30:00',
            'manual_reopen' => true,
            'number_limit' => 500,
            'number_limit_3d' => 100,
            'status' => 'open',
        ]);
        $session = AgentSession::openSession($agent, $round, $operator->id);

        return [$admin, $operator, $round, $agent, $session];
    }
}
