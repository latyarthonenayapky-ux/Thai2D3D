<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\SaleDetail;
use App\Models\SaleInput;
use App\Models\ThreeDigitSaleDetail;
use App\Models\ThreeDigitSaleInput;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A saved sale can never be edited in place, so corrections are modelled as a
 * void: every detail leaves the 'accepted' state, which makes all existing
 * totals, ledgers, settlement and reports drop it without further changes.
 */
class SaleVoidService
{
    public function void(SaleInput|ThreeDigitSaleInput $sale, User $actor, string $reason): SaleInput|ThreeDigitSaleInput
    {
        return DB::transaction(function () use ($sale, $actor, $reason) {
            $locked = $sale->newQuery()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === 'voided') {
                return $locked;
            }

            $previousStatus = $locked->status;
            $snapshot = $locked->details()->pluck('status', 'id')->all();

            $locked->details()->update(['status' => 'voided']);

            $locked->forceFill([
                'status' => 'voided',
                'pre_void_status' => $previousStatus,
                'pre_void_detail_statuses' => $snapshot,
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
            ])->save();

            ActivityLog::record('sale.void', $locked, [
                'input' => $locked->original_input,
                'amount' => number_format((float) $locked->total_amount, 2, '.', ''),
                'reason' => $reason,
                'agent_session_id' => $locked->agent_session_id,
            ], $actor);

            return $locked;
        });
    }

    public function restore(SaleInput|ThreeDigitSaleInput $sale, User $actor): SaleInput|ThreeDigitSaleInput
    {
        return DB::transaction(function () use ($sale, $actor) {
            $locked = $sale->newQuery()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'voided') {
                return $locked;
            }

            $snapshot = $locked->pre_void_detail_statuses ?? [];

            $locked->details()->each(function (SaleDetail|ThreeDigitSaleDetail $detail) use ($snapshot): void {
                $detail->forceFill([
                    'status' => $snapshot[$detail->getKey()] ?? $this->guessStatus($detail),
                ])->save();
            });

            $locked->forceFill([
                'status' => $locked->pre_void_status ?: 'accepted',
                'pre_void_status' => null,
                'pre_void_detail_statuses' => null,
                'voided_at' => null,
                'voided_by' => null,
                'void_reason' => null,
            ])->save();

            ActivityLog::record('sale.restore', $locked, [
                'input' => $locked->original_input,
                'agent_session_id' => $locked->agent_session_id,
            ], $actor);

            return $locked;
        });
    }

    private function guessStatus(SaleDetail|ThreeDigitSaleDetail $detail): string
    {
        if ($detail->reject_reason !== null) {
            return 'rejected';
        }

        return $detail->is_excluded ? 'excluded' : 'accepted';
    }
}
