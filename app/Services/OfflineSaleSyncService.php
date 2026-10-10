<?php

namespace App\Services;

use App\Models\AgentSession;
use App\Models\OfflineSyncReview;
use App\Models\SaleDetail;
use App\Models\SaleInput;
use App\Models\ThreeDigitSaleDetail;
use App\Models\ThreeDigitSaleInput;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class OfflineSaleSyncService
{
    public function __construct(
        protected SaleProcessor $twoDigitProcessor,
        protected ThreeDigitSaleProcessor $threeDigitProcessor,
        protected SaleInputParser $twoDigitParser,
        protected ThreeDigitInputParser $threeDigitParser,
    ) {}

    public function sync(User $operator, array $record): array
    {
        return DB::transaction(function () use ($operator, $record): array {
            $clientUuid = $record['client_uuid'];
            $saleType = $record['sale_type'];
            $session = AgentSession::query()
                ->with(['round', 'agent'])
                ->whereKey($record['session_id'])
                ->where('operator_id', $operator->id)
                ->first();

            if (
                ! $operator->isOperator()
                || ! $operator->status
                || ! $session
                || (int) $operator->admin_id !== (int) $session->round->admin_id
                || (int) $operator->admin_id !== (int) $session->agent->admin_id
            ) {
                return ['client_uuid' => $clientUuid, 'status' => 'error', 'message' => 'This Agent session is not available to your account.'];
            }

            $assignmentExists = $session->round->agentRoundSettings()
                ->where('agent_id', $session->agent_id)
                ->where('operator_id', $operator->id)
                ->exists();
            if (! $assignmentExists) {
                return ['client_uuid' => $clientUuid, 'status' => 'error', 'message' => 'This Agent is no longer assigned to your account.'];
            }

            $existingReview = OfflineSyncReview::query()
                ->where('operator_id', $operator->id)
                ->where('client_uuid', $clientUuid)
                ->first();
            if ($existingReview) {
                return [
                    'client_uuid' => $clientUuid,
                    'status' => $existingReview->status === 'pending' ? 'pending_review' : 'reviewed',
                    'message' => $existingReview->conflict_reason,
                ];
            }

            $existingSale = $saleType === '2d'
                ? SaleInput::query()->where('operator_id', $operator->id)->where('client_uuid', $clientUuid)->first()
                : ThreeDigitSaleInput::query()->where('operator_id', $operator->id)->where('client_uuid', $clientUuid)->first();
            if ($existingSale) {
                return ['client_uuid' => $clientUuid, 'status' => 'synced', 'message' => 'Already synchronized.'];
            }

            $round = $session->round;
            if ($round->status !== 'open' || $session->status !== 'open') {
                return $this->createReview(
                    $operator,
                    $session,
                    $record,
                    'The Round or Agent session closed before this offline sale synchronized.'
                );
            }

            try {
                if ($saleType === '2d') {
                    $sale = $this->twoDigitProcessor->process($record['input'], $session, $clientUuid);
                    $conflict = $sale->details()
                        ->whereIn('reject_reason', ['HOT_NUMBER', 'NUMBER_LIMIT'])
                        ->exists();
                    if ($conflict) {
                        $reason = $sale->details()->where('reject_reason', 'HOT_NUMBER')->exists()
                            ? 'One or more numbers became Hot Numbers before synchronization.'
                            : 'One or more 2D values are blocked by this Round’s Number Limit.';
                        $sale->delete();

                        return $this->createReview($operator, $session, $record, $reason);
                    }
                } else {
                    $sale = $this->threeDigitProcessor->process($record['input'], $session, $clientUuid);
                    $conflict = $sale->details()
                        ->whereIn('reject_reason', ['HOT_NUMBER_3D', 'NUMBER_LIMIT_3D'])
                        ->exists();
                    if ($conflict) {
                        $reason = $sale->details()->where('reject_reason', 'HOT_NUMBER_3D')->exists()
                            ? 'One or more numbers became 3D Hot Numbers before synchronization.'
                            : 'The Round-wide 3D Number Limit would be exceeded.';
                        $sale->delete();

                        return $this->createReview($operator, $session, $record, $reason);
                    }
                }
            } catch (InvalidArgumentException $exception) {
                return [
                    'client_uuid' => $clientUuid,
                    'status' => 'invalid',
                    'message' => $exception->getMessage(),
                ];
            } catch (RuntimeException $exception) {
                if (str_contains($exception->getMessage(), 'Round is not open')
                    || str_contains($exception->getMessage(), 'session is not open')) {
                    return $this->createReview($operator, $session, $record, $exception->getMessage());
                }

                return ['client_uuid' => $clientUuid, 'status' => 'error', 'message' => $exception->getMessage()];
            }

            return ['client_uuid' => $clientUuid, 'status' => 'synced', 'message' => 'Sale synchronized.'];
        });
    }

    public function approve(OfflineSyncReview $review, User $admin): void
    {
        DB::transaction(function () use ($review, $admin): void {
            $locked = OfflineSyncReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->round->admin_id !== (int) $admin->id) {
                abort(404);
            }
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['review' => 'This offline sale has already been reviewed.']);
            }

            $session = $locked->agentSession()->with(['agent', 'round'])->firstOrFail();
            if ($locked->sale_type === '2d') {
                $parsed = $this->twoDigitParser->parse($locked->original_input, $admin->id);
                $sale = SaleInput::query()->create([
                    'agent_session_id' => $session->id,
                    'operator_id' => $locked->operator_id,
                    'client_uuid' => $locked->client_uuid,
                    'original_input' => $parsed['original_input'],
                    'normalized_input' => $parsed['normalized_input'],
                    'input_type' => $parsed['type'],
                    'code' => $parsed['code'],
                    'number_argument' => $parsed['number_argument'] ?? null,
                    'number_count' => $parsed['number_count'],
                    'amount' => $parsed['amount'],
                    'total_amount' => $parsed['total_amount'],
                    'status' => 'accepted',
                ]);
                foreach ($parsed['generated_numbers'] as $number) {
                    $excluded = in_array($number, $parsed['excluded_numbers'], true);
                    SaleDetail::query()->create([
                        'sale_input_id' => $sale->id,
                        'number' => $number,
                        'amount' => $parsed['amount'],
                        'is_excluded' => $excluded,
                        'status' => $excluded ? 'excluded' : 'accepted',
                        'reject_reason' => null,
                    ]);
                }
                $locked->sale_input_id = $sale->id;
            } else {
                $parsed = $this->threeDigitParser->parse($locked->original_input);
                $sale = ThreeDigitSaleInput::query()->create([
                    'agent_session_id' => $session->id,
                    'operator_id' => $locked->operator_id,
                    'client_uuid' => $locked->client_uuid,
                    'original_input' => $parsed['original_input'],
                    'normalized_input' => $parsed['normalized_input'],
                    'input_type' => $parsed['type'],
                    'code' => $parsed['code'],
                    'number_count' => $parsed['number_count'],
                    'amount' => $parsed['amount'],
                    'total_amount' => $parsed['total_amount'],
                    'status' => 'accepted',
                ]);
                foreach ($parsed['numbers'] as $number) {
                    ThreeDigitSaleDetail::query()->create([
                        'three_digit_sale_input_id' => $sale->id,
                        'number' => $number,
                        'amount' => $parsed['amount'],
                        'status' => 'accepted',
                    ]);
                }
                $locked->three_digit_sale_input_id = $sale->id;
            }

            $locked->forceFill([
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();
        });
    }

    public function reject(OfflineSyncReview $review, User $admin): void
    {
        DB::transaction(function () use ($review, $admin): void {
            $locked = OfflineSyncReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->round->admin_id !== (int) $admin->id) {
                abort(404);
            }
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['review' => 'This offline sale has already been reviewed.']);
            }
            $locked->forceFill([
                'status' => 'rejected',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();
        });
    }

    private function createReview(User $operator, AgentSession $session, array $record, string $reason): array
    {
        OfflineSyncReview::query()->create([
            'client_uuid' => $record['client_uuid'],
            'sale_type' => $record['sale_type'],
            'operator_id' => $operator->id,
            'agent_session_id' => $session->id,
            'round_id' => $session->round_id,
            'agent_id' => $session->agent_id,
            'original_input' => $record['input'],
            'recorded_at' => $record['recorded_at'] ?? null,
            'status' => 'pending',
            'conflict_reason' => $reason,
        ]);

        return ['client_uuid' => $record['client_uuid'], 'status' => 'pending_review', 'message' => $reason];
    }
}
