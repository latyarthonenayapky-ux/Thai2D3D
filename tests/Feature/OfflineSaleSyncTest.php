<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentSession;
use App\Models\OfflineSyncReview;
use App\Models\Round;
use App\Models\SaleInput;
use App\Models\ThreeDigitHotNumber;
use App\Models\ThreeDigitSaleInput;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineSaleSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_is_idempotent_for_accepted_offline_sales(): void
    {
        [$admin, $operator, $round, $session] = $this->createBusiness();
        $record = $this->record($session, '2d', '121000');

        $this->actingAs($operator)
            ->postJson(route('operator.offline-sync.store'), ['records' => [$record]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced');

        $this->postJson(route('operator.offline-sync.store'), ['records' => [$record]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced')
            ->assertJsonPath('results.0.message', 'Already synchronized.');

        $this->assertSame(1, SaleInput::query()->where('client_uuid', $record['client_uuid'])->count());
        $this->assertSame(0, OfflineSyncReview::query()->count());
    }

    public function test_hot_number_conflict_is_saved_for_admin_review_and_approval_is_audited(): void
    {
        [$admin, $operator, $round, $session] = $this->createBusiness();
        $round->update(['number_limit_3d' => 100]);
        ThreeDigitHotNumber::query()->create(['round_id' => $round->id, 'number' => '123']);
        $record = $this->record($session, '3d', '123500');

        $this->actingAs($operator)
            ->postJson(route('operator.offline-sync.store'), ['records' => [$record]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'pending_review');

        $review = OfflineSyncReview::query()->where('client_uuid', $record['client_uuid'])->firstOrFail();
        $this->assertSame('pending', $review->status);
        $this->assertStringContainsString('Hot Numbers', $review->conflict_reason);
        $this->assertSame(0, ThreeDigitSaleInput::query()->count());

        $this->actingAs($admin)
            ->get(route('admin.offline-reviews.index'))
            ->assertOk()
            ->assertSee('123500')
            ->assertSee('3D Hot Numbers');
        $this->getJson(route('admin.offline-reviews.pending'))
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->patch(route('admin.offline-reviews.update', $review), ['action' => 'approve'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $review->refresh();
        $this->assertSame('approved', $review->status);
        $this->assertSame($admin->id, $review->reviewed_by);
        $this->assertNotNull($review->reviewed_at);
        $this->assertNotNull($review->three_digit_sale_input_id);
        $this->assertDatabaseHas('three_digit_sale_details', [
            'three_digit_sale_input_id' => $review->three_digit_sale_input_id,
            'number' => '123',
            'status' => 'accepted',
        ]);
    }

    public function test_closed_round_conflict_can_be_rejected_and_review_remains_in_history(): void
    {
        [$admin, $operator, $round, $session] = $this->createBusiness();
        $round->update(['status' => 'closed']);
        $record = $this->record($session, '2d', '121000');

        $this->actingAs($operator)
            ->postJson(route('operator.offline-sync.store'), ['records' => [$record]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'pending_review');

        $review = OfflineSyncReview::query()->where('client_uuid', $record['client_uuid'])->firstOrFail();
        $this->actingAs($admin)
            ->patch(route('admin.offline-reviews.update', $review), ['action' => 'reject'])
            ->assertRedirect();

        $review->refresh();
        $this->assertSame('rejected', $review->status);
        $this->assertSame($admin->id, $review->reviewed_by);
        $this->assertSame(0, SaleInput::query()->count());
        $this->assertSame(1, OfflineSyncReview::query()->count());
    }

    public function test_invalid_and_unassigned_offline_sales_are_reported_without_creating_reviews(): void
    {
        [$admin, $operator, $round, $session] = $this->createBusiness();
        $invalid = $this->record($session, '3d', 'a1000');

        $this->actingAs($operator)
            ->postJson(route('operator.offline-sync.store'), ['records' => [$invalid]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'invalid');

        $otherOperator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);
        $unauthorized = $this->record($session, '2d', '121000');
        $this->actingAs($otherOperator)
            ->postJson(route('operator.offline-sync.store'), ['records' => [$unauthorized]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'error');

        $this->assertSame(0, OfflineSyncReview::query()->count());
        $this->assertSame(0, SaleInput::query()->count());
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
            'agent_code' => 'OFF-01',
            'agent_name' => 'Offline agent',
        ]);
        $round = Round::query()->create([
            'admin_id' => $admin->id,
            'round_date' => '2026-10-07',
            'round_no' => 1,
            'start_time' => '07:00:00',
            'close_time' => '09:30:00',
            'number_limit' => 100,
            'number_limit_3d' => 100,
            'status' => 'open',
        ]);
        $session = AgentSession::openSession($agent, $round, $operator->id);

        return [$admin, $operator, $round, $session];
    }

    private function record(AgentSession $session, string $saleType, string $input): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'session_id' => $session->id,
            'sale_type' => $saleType,
            'input' => $input,
            'recorded_at' => now()->toIso8601String(),
        ];
    }
}
