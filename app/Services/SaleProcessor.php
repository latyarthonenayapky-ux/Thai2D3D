<?php

namespace App\Services;

use App\Models\AgentRoundSetting;
use App\Models\AgentSession;
use App\Models\HotNumber;
use App\Models\Round;
use App\Models\SaleDetail;
use App\Models\SaleInput;
use Illuminate\Support\Facades\DB;

class SaleProcessor
{
    public function __construct(
        protected SaleInputParser $parser
    ) {}

    /**
     * Process one operator input.
     *
     * Number Limit:
     * - Blocks configured 00–99 values for the current Round.
     * - Shared by all Agents in the same Round.
     *
     * Amount Limit:
     * - Not used as a rejection condition here.
     * - Admin can monitor amounts separately.
     *
     * Hot Number:
     * - Rejects matching numbers.
     *
     * Exclusion:
     * - Explicitly excluded numbers are saved as excluded rather than rejected.
     */
    public function process(string $input, AgentSession $session, ?string $clientUuid = null): SaleInput
    {
        return DB::transaction(function () use ($input, $session, $clientUuid) {
            $round = Round::query()
                ->whereKey($session->round_id)
                ->lockForUpdate()
                ->first();

            /*
             * ============================================================
             * 1. ROUND AND SESSION CHECK
             * ============================================================
             */

            if (! $round || $round->status !== 'open') {
                throw new \RuntimeException(
                    'Round is not open.'
                );
            }

            $session = AgentSession::query()
                ->lockForUpdate()
                ->find($session->id);

            if (! $session) {
                throw new \RuntimeException(
                    'Agent session no longer exists.'
                );
            }

            if ($clientUuid !== null) {
                $existing = SaleInput::query()
                    ->where('operator_id', $session->operator_id)
                    ->where('agent_session_id', $session->id)
                    ->where('client_uuid', $clientUuid)
                    ->first();
                if ($existing) {
                    return $existing->load('details');
                }
            }

            if ($session->status !== 'open') {
                throw new \RuntimeException(
                    'Agent session is not open.'
                );
            }

            $handler = $session->operator;
            $agent = $session->agent;
            $belongsToBusiness = $handler?->isAdmin()
                ? (int) $handler->id === (int) $round->admin_id
                : $handler?->isOperator() && (int) $handler->admin_id === (int) $round->admin_id;
            if (
                ! $handler
                || ! $handler->status
                || ! $belongsToBusiness
                || ! $agent
                || (int) $agent->admin_id !== (int) $round->admin_id
            ) {
                throw new \RuntimeException('This Agent session does not belong to the handler’s business.');
            }

            $isRoundOperator = AgentRoundSetting::query()
                ->where('agent_id', $session->agent_id)
                ->where('round_id', $round->id)
                ->where('operator_id', $session->operator_id)
                ->exists();

            if (! $isRoundOperator) {
                throw new \RuntimeException('This account does not own the Agent assignment for this Round.');
            }

            /*
             * ============================================================
             * 2. PARSE INPUT
             * ============================================================
             */

            $parsed = $this->parser->parse(
                $input,
                (int) $round->admin_id
            );

            /*
             * ============================================================
             * 3. BASIC PARSED VALUES
             * ============================================================
             */

            $numbers = $parsed['numbers'] ?? [];
            $generatedNumbers = $parsed['generated_numbers'] ?? $numbers;

            $excludedNumbers = $parsed['excluded_numbers'] ?? [];

            $numberCount = (int) (
                $parsed['number_count']
                ?? count($numbers)
            );

            $amount = (float) (
                $parsed['amount']
                ?? 0
            );

            $totalAmount = (float) (
                $parsed['total_amount']
                ?? ($amount * $numberCount)
            );

            /*
             * ============================================================
             * 4. CREATE SALE INPUT
             * ============================================================
             *
             * We create SaleInput before processing the details so that
             * rejected inputs are also stored for audit/history.
             */

            $saleInput = SaleInput::create([
                'agent_session_id' => $session->id,
                'operator_id' => $session->operator_id,
                'client_uuid' => $clientUuid,

                'original_input' => $parsed['original_input'] ?? $input,

                'normalized_input' => $parsed['normalized_input']
                    ?? strtoupper(trim($input)),

                'input_type' => $parsed['type'] ?? null,

                'code' => $parsed['code'] ?? null,

                'number_argument' => $parsed['number_argument'] ?? null,

                'number_count' => $numberCount,

                'amount' => $amount,

                'total_amount' => $totalAmount,

                'status' => 'accepted',

                'reject_reason' => null,
            ]);

            $hotNumbers = HotNumber::query()
                ->where('round_id', $round->id)
                ->pluck('number')
                ->map(fn ($number) => str_pad(
                    (string) $number,
                    2,
                    '0',
                    STR_PAD_LEFT
                ))
                ->unique()
                ->values()
                ->all();
            $hotNumbers = array_fill_keys($hotNumbers, true);
            $numberLimits = array_fill_keys($round->number_limits ?? [], true);

            /*
             * ============================================================
             * 9. PROCESS GENERATED NUMBERS
             * ============================================================
             */

            $acceptedCount = 0;

            $rejectedCount = 0;

            $excludedCount = 0;

            $acceptedAmount = 0;

            foreach ($generatedNumbers as $number) {

                /*
                 * Always normalize number to two digits.
                 */

                $number = str_pad(
                    (string) $number,
                    2,
                    '0',
                    STR_PAD_LEFT
                );

                /*
                 * --------------------------------------------------------
                 * 9.1 EXCLUSION CHECK
                 * --------------------------------------------------------
                 *
                 * Exclusion has priority over Hot Number.
                 *
                 * Example:
                 *
                 * A[2233]1000
                 *
                 * 22 and 33 are excluded.
                 */

                $isExcluded = in_array(
                    $number,
                    $excludedNumbers,
                    true
                );

                if ($isExcluded) {

                    $excludedCount++;

                    /*
                     * Excluded numbers are stored for history/audit.
                     */

                    SaleDetail::create([
                        'sale_input_id' => $saleInput->id,

                        'number' => $number,

                        'amount' => $amount,

                        'is_excluded' => true,

                        'status' => 'excluded',

                        'reject_reason' => null,
                    ]);

                    continue;
                }

                /*
                 * --------------------------------------------------------
                 * 9.2 HOT NUMBER CHECK
                 * --------------------------------------------------------
                 */

                if (isset($hotNumbers[$number])) {

                    $rejectedCount++;

                    SaleDetail::create([
                        'sale_input_id' => $saleInput->id,

                        'number' => $number,

                        'amount' => $amount,

                        'is_excluded' => false,

                        'status' => 'rejected',

                        'reject_reason' => 'HOT_NUMBER',
                    ]);

                    continue;
                }

                if (isset($numberLimits[$number])) {
                    $rejectedCount++;

                    SaleDetail::create([
                        'sale_input_id' => $saleInput->id,
                        'number' => $number,
                        'amount' => $amount,
                        'is_excluded' => false,
                        'status' => 'rejected',
                        'reject_reason' => 'NUMBER_LIMIT',
                    ]);

                    continue;
                }

                /*
                 * --------------------------------------------------------
                 * 9.3 ACCEPTED NUMBER
                 * --------------------------------------------------------
                 */

                $acceptedCount++;

                $acceptedAmount += $amount;

                SaleDetail::create([
                    'sale_input_id' => $saleInput->id,

                    'number' => $number,

                    'amount' => $amount,

                    'is_excluded' => false,

                    'status' => 'accepted',

                    'reject_reason' => null,
                ]);
            }

            /*
             * ============================================================
             * 10. FINAL STATUS
             * ============================================================
             *
             * IMPORTANT:
             *
             * Excluded numbers are not rejected.
             *
             * Example:
             *
             * A[2233]1000
             *
             * Parser:
             *
             * number_count = 8
             *
             * Details:
             *
             * 00 accepted
             * 11 accepted
             * 22 excluded
             * 33 excluded
             * 44 accepted
             * 55 accepted
             * 66 accepted
             * 77 accepted
             * 88 accepted
             * 99 accepted
             *
             * Therefore:
             *
             * acceptedCount = 8
             * excludedCount = 2
             *
             * In the current parser design, number_count is already
             * the actual generated number count, so the details normally
             * contain the generated numbers after exclusion processing.
             */

            /*
             * Actual non-excluded numbers processed.
             */

            $actualNumberCount = count($generatedNumbers) - $excludedCount;

            /*
             * ------------------------------------------------------------
             * 10.1 ALL NUMBERS REJECTED
             * ------------------------------------------------------------
             *
             * Example:
             *
             * 221000
             *
             * 22 is Hot Number.
             *
             * acceptedCount = 0
             *
             * => ALL_NUMBERS_REJECTED
             */

            if (
                $actualNumberCount > 0
                && $acceptedCount === 0
            ) {

                $saleInput->update([
                    'status' => 'rejected',

                    'reject_reason' => 'ALL_NUMBERS_REJECTED',
                ]);

                return $saleInput->fresh();
            }

            /*
             * ------------------------------------------------------------
             * 10.2 PARTIAL / NORMAL ACCEPTANCE
             * ------------------------------------------------------------
             *
             * If at least one number is accepted, SaleInput is accepted.
             *
             * Individual rejected numbers remain in SaleDetail.
             */

            $saleInput->update([
                'status' => 'accepted',

                'reject_reason' => null,
            ]);

            return $saleInput->fresh();
        });
    }
}
