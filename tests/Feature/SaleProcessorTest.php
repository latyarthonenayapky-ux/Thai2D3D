<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentRoundSetting;
use App\Models\AgentSession;
use App\Models\HotNumber;
use App\Models\Round;
use App\Models\SaleDetail;
use App\Models\SaleInput;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use App\Services\SaleProcessor;
use Database\Seeders\CodeRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected SaleProcessor $processor;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CodeRuleSeeder::class);
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => true,
        ]);
        app(InitializeAdminBusiness::class)->initialize($this->admin);
        $this->processor = app(SaleProcessor::class);
    }

    public function test_number_limit_blocks_configured_2d_values_across_agents_in_a_round(): void
    {
        $round = $this->createRound(2);
        $round->update(['number_limits' => ['34']]);
        $firstOperator = $this->createOperator();
        $secondOperator = $this->createOperator();
        $firstSession = $this->createSession($round, $firstOperator, 'A001');
        $secondSession = $this->createSession($round, $secondOperator, 'A002');

        $accepted = $this->processor->process('121000', $firstSession);
        $blocked = $this->processor->process('341000', $secondSession);

        $this->assertSame('accepted', $accepted->status);
        $this->assertSame('rejected', $blocked->status);
        $this->assertSame(
            1,
            SaleDetail::query()
                ->where('status', 'accepted')
                ->where('is_excluded', false)
                ->count()
        );
        $this->assertDatabaseHas('sale_details', [
            'sale_input_id' => $blocked->id,
            'number' => '34',
            'status' => 'rejected',
            'reject_reason' => 'NUMBER_LIMIT',
        ]);
    }

    public function test_digit_includes_sale_saves_each_expanded_number_at_the_entered_amount(): void
    {
        $round = $this->createRound(2);
        $operator = $this->createOperator();
        $session = $this->createSession($round, $operator, 'INCLUDES');

        $result = $this->processor->process('1/1000', $session);

        $this->assertSame('accepted', $result->status);
        $this->assertSame(19, $result->number_count);
        $this->assertSame(1000.0, (float) $result->amount);
        $this->assertSame(19000.0, (float) $result->total_amount);
        $this->assertSame(19, SaleDetail::query()->where('status', 'accepted')->count());
        $this->assertSame(
            1000.0,
            (float) SaleDetail::query()->where('number', '01')->value('amount')
        );
    }

    public function test_sales_are_rejected_when_the_round_is_closed_even_if_the_session_is_open(): void
    {
        $round = $this->createRound(10);
        $operator = $this->createOperator();
        $session = $this->createSession($round, $operator, 'CLOSED');
        $round->update(['status' => 'closed']);

        try {
            $this->processor->process('121000', $session);
            $this->fail('Expected closed rounds to reject sale processing.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Round is not open.', $exception->getMessage());
        }

        $this->assertSame(0, SaleInput::query()->count());
    }

    public function test_excluded_numbers_are_audited_and_not_counted(): void
    {
        $round = $this->createRound(8);
        $operator = $this->createOperator();
        $session = $this->createSession($round, $operator, 'EXCLUDE');

        $result = $this->processor->process('A[2233]1000', $session);

        $this->assertSame('accepted', $result->status);
        $this->assertSame(8, $result->number_count);
        $this->assertSame(
            8,
            SaleDetail::query()
                ->where('status', 'accepted')
                ->where('is_excluded', false)
                ->count()
        );
        $this->assertSame(
            2,
            SaleDetail::query()
                ->where('status', 'excluded')
                ->where('is_excluded', true)
                ->count()
        );
    }

    public function test_exclusion_has_priority_over_hot_number_and_hot_does_not_use_limit(): void
    {
        $round = $this->createRound(7);
        $operator = $this->createOperator();
        $session = $this->createSession($round, $operator, 'PRIORITY');
        HotNumber::create(['round_id' => $round->id, 'number' => '00']);
        HotNumber::create(['round_id' => $round->id, 'number' => '22']);

        $result = $this->processor->process('A[2233]1000', $session);

        $this->assertSame('accepted', $result->status);
        $this->assertSame(7, SaleDetail::query()->where('status', 'accepted')->count());
        $this->assertSame(1, SaleDetail::query()
            ->where('number', '00')
            ->where('reject_reason', 'HOT_NUMBER')
            ->count());
        $this->assertSame(2, SaleDetail::query()->where('status', 'excluded')->count());
        $this->assertSame(0, SaleDetail::query()
            ->where('number', '22')
            ->where('status', 'rejected')
            ->count());
    }

    public function test_hot_number_rejection_does_not_block_other_numbers(): void
    {
        $round = $this->createRound(1);
        $operator = $this->createOperator();
        $session = $this->createSession($round, $operator, 'HOT');
        HotNumber::create(['round_id' => $round->id, 'number' => '22']);

        $hotResult = $this->processor->process('221000', $session);
        $acceptedResult = $this->processor->process('121000', $session);

        $this->assertSame('ALL_NUMBERS_REJECTED', $hotResult->reject_reason);
        $this->assertSame('accepted', $acceptedResult->status);
    }

    public function test_legacy_2d_number_limit_does_not_cap_sales_count(): void
    {
        $round = $this->createRound(7);
        $operator = $this->createOperator();
        $session = $this->createSession($round, $operator, 'LIMIT');

        $result = $this->processor->process('A[2233]1000', $session);

        $this->assertSame('accepted', $result->status);
        $this->assertSame(8, SaleDetail::query()->where('status', 'accepted')->count());
        $this->assertSame(2, SaleDetail::query()->where('status', 'excluded')->count());
    }

    public function test_amount_limit_is_monitoring_only(): void
    {
        $round = $this->createRound(1);
        $operator = $this->createOperator();
        $session = $this->createSession($round, $operator, 'AMOUNT');

        AgentRoundSetting::query()
            ->where('agent_id', $session->agent_id)
            ->where('round_id', $round->id)
            ->update(['amount_limit' => 100]);

        $result = $this->processor->process('1210000', $session);

        $this->assertSame('accepted', $result->status);
        $this->assertSame(10000.0, (float) $result->amount);
        $this->assertSame(1, $round->fresh()->number_limit);
    }

    public function test_sale_input_is_rejected_when_the_session_operator_does_not_own_the_round_assignment(): void
    {
        $round = $this->createRound(10);
        $owner = $this->createOperator();
        $otherOperator = $this->createOperator();
        $session = $this->createSession($round, $owner, 'ASSIGNMENT');
        AgentRoundSetting::query()
            ->where('agent_id', $session->agent_id)
            ->where('round_id', $round->id)
            ->update(['operator_id' => $otherOperator->id]);

        try {
            $this->processor->process('121000', $session);
            $this->fail('A session must not process sales for another Operator’s Agent assignment.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'This account does not own the Agent assignment for this Round.',
                $exception->getMessage()
            );
        }

        $this->assertSame(0, SaleInput::query()->count());
    }

    public function test_duplicate_inputs_are_saved_as_separate_records(): void
    {
        $round = $this->createRound(2);
        $operator = $this->createOperator();
        $session = $this->createSession($round, $operator, 'DUPLICATE');

        $first = $this->processor->process('121000', $session);
        $second = $this->processor->process('121000', $session);

        $this->assertSame('accepted', $first->status);
        $this->assertSame('accepted', $second->status);
        $this->assertSame(2, SaleInput::query()->count());
        $this->assertSame(2, SaleDetail::query()->where('status', 'accepted')->count());
    }

    protected function createRound(int $numberLimit): Round
    {
        return Round::create([
            'admin_id' => $this->admin->id,
            'round_date' => now()->toDateString(),
            'round_no' => random_int(1, 9999),
            'start_time' => '00:00:00',
            'close_time' => '23:59:59',
            'number_limit' => $numberLimit,
            'status' => 'open',
        ]);
    }

    protected function createSession(
        Round $round,
        User $operator,
        string $code
    ): AgentSession {
        $agent = Agent::create([
            'admin_id' => $this->admin->id,
            'agent_code' => $code,
            'agent_name' => $code,
        ]);

        AgentRoundSetting::query()->create([
            'agent_id' => $agent->id,
            'round_id' => $round->id,
            'operator_id' => $operator->id,
        ]);

        return AgentSession::create([
            'round_id' => $round->id,
            'agent_id' => $agent->id,
            'operator_id' => $operator->id,
            'session_code' => "TEST-{$code}",
            'status' => 'open',
            'opened_at' => now(),
        ]);
    }

    protected function createOperator(): User
    {
        return User::factory()->create([
            'role' => 'operator',
            'admin_id' => $this->admin->id,
            'status' => true,
        ]);
    }
}
