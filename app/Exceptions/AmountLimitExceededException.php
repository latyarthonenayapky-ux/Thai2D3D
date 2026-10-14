<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a sale would take an Agent, an Operator, or a whole Round past
 * the Amount Limit the Admin set for that tier. Nothing is written for the
 * blocked sale — the operator sees the remaining headroom instead.
 */
class AmountLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $tier,
        public readonly string $tierLabel,
        public readonly float $limit,
        public readonly float $used,
        public readonly float $incoming,
    ) {
        parent::__construct(sprintf(
            '%s — limit %s, %s already sold, %s remaining, this sale needs %s.',
            $tierLabel,
            number_format($limit, 2),
            number_format($used, 2),
            number_format(max(round($limit - $used, 2), 0.0), 2),
            number_format($incoming, 2),
        ));
    }

    public function headroom(): float
    {
        return max(round($this->limit - $this->used, 2), 0.0);
    }
}
