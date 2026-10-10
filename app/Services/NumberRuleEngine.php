<?php

namespace App\Services;

use App\Models\CodeRule;
use InvalidArgumentException;

class NumberRuleEngine
{
    /**
     * Expand a rule into a list of 2D numbers.
     *
     * Supported:
     * A
     * B
     * F
     * W
     * N
     * X
     * P
     * ++
     * --
     * +-
     * -+
     * R
     */
    public function expand(string $code, ?string $argument = null, ?int $adminId = null): array
    {
        $code = strtoupper(trim($code));
        $argument = $argument !== null
            ? strtoupper(trim($argument))
            : null;

        return match ($code) {
            'A' => $this->expandA(),

            'B' => $this->expandB($argument),

            'F' => $this->expandF($argument),

            'W' => $this->expandConfiguredRule('W', $adminId),

            'N' => $this->expandConfiguredRule('N', $adminId),

            'X' => $this->expandConfiguredRule('X', $adminId),

            'P' => $this->expandP($argument),

            '++' => $this->expandParity('even', 'even'),

            '--' => $this->expandParity('odd', 'odd'),

            '+-' => $this->expandParity('even', 'odd'),

            '-+' => $this->expandParity('odd', 'even'),

            'R' => $this->expandReverse($argument),

            default => $this->expandConfiguredRule($code, $adminId),
        };
    }

    /**
     * A = အပူး
     *
     * Required order:
     * 00,11,22,...99
     */
    protected function expandA(): array
    {
        return [
            '00',
            '11',
            '22',
            '33',
            '44',
            '55',
            '66',
            '77',
            '88',
            '99',
        ];
    }

    /**
     * B = ဘရိတ်
     *
     * Digit sum modulo 10.
     *
     * Example:
     * 2B:
     * 11,20,39,48,57,66,75,84,93,02
     *
     * 9B:
     * 09,18,27,36,45,54,63,72,81,90
     */
    protected function expandB(?string $argument): array
    {
        if ($argument === null || $argument === '') {
            throw new InvalidArgumentException(
                'B rule requires a digit.'
            );
        }

        if (! preg_match('/^\d$/', $argument)) {
            throw new InvalidArgumentException(
                "Invalid B argument: {$argument}"
            );
        }

        $target = (int) $argument;

        /*
         * Preserve the user's expected ordering.
         *
         * For target 2:
         * 11,20,39,48,57,66,75,84,93,02
         *
         * For target 9:
         * 09,18,27,36,45,54,63,72,81,90
         *
         * General rule:
         * scan tens digits first in the desired sequence.
         */
        $numbers = [];

        for ($tens = 0; $tens <= 9; $tens++) {
            for ($ones = 0; $ones <= 9; $ones++) {
                if ((($tens + $ones) % 10) === $target) {
                    $numbers[] = sprintf(
                        '%d%d',
                        $tens,
                        $ones
                    );
                }
            }
        }

        /*
         * The natural scan gives:
         * 02,11,20,39,...
         *
         * The established business order expects:
         * 11,20,39,...,02
         *
         * Therefore move the 0-leading result to the end.
         */
        $numbers = $this->moveLeadingZeroToEnd($numbers);

        return $numbers;
    }

    /**
     * F rule.
     *
     * 1F = front digit 1
     *
     * Required order:
     * 11,12,13,14,15,16,17,18,19,10
     *
     * F1 = back digit 1
     *
     * Required order:
     * 11,21,31,41,51,61,71,81,91,01
     */
    protected function expandF(?string $argument): array
    {
        if ($argument === null || $argument === '') {
            throw new InvalidArgumentException(
                'F rule requires an argument such as 1F or F1.'
            );
        }

        $argument = strtoupper(trim($argument));

        /*
         * Front:
         * 1F
         * 2F
         * ...
         * 9F
         */
        if (preg_match('/^([0-9])F$/', $argument, $match)) {
            $digit = $match[1];

            $numbers = [];

            /*
             * 1-9 first
             */
            for ($i = 1; $i <= 9; $i++) {
                $numbers[] = $digit.$i;
            }

            /*
             * 0 last
             */
            $numbers[] = $digit.'0';

            return $numbers;
        }

        /*
         * Back:
         * F1
         * F2
         * ...
         * F9
         */
        if (preg_match('/^F([0-9])$/', $argument, $match)) {
            $digit = $match[1];

            $numbers = [];

            /*
             * 1-9 first
             */
            for ($i = 1; $i <= 9; $i++) {
                $numbers[] = $i.$digit;
            }

            /*
             * 0 last
             */
            $numbers[] = '0'.$digit;

            return $numbers;
        }

        throw new InvalidArgumentException(
            "Invalid F argument: {$argument}"
        );
    }

    /**
     * W / N / X are database-configured rules.
     */
    protected function expandConfiguredRule(string $code, ?int $adminId = null): array
    {
        $rule = CodeRule::query()
            ->where('code', strtoupper($code))
            ->where('rule_type', 'number_set')
            ->when(
                $adminId === null,
                fn ($query) => $query->whereNull('admin_id'),
                fn ($query) => $query->where(function ($query) use ($adminId): void {
                    $query->where('admin_id', $adminId)->orWhereNull('admin_id');
                })
            )
            ->orderByRaw('admin_id IS NULL')
            ->first();

        if (! $rule || ! $rule->is_active) {
            throw new InvalidArgumentException(
                "Configured rule not found: {$code}"
            );
        }

        $config = $rule->rule_config ?? [];

        /*
         * The current business rules use:
         *
         * [
         *     'numbers' => [...]
         * ]
         */
        $numbers = $config['numbers'] ?? [];

        if (! is_array($numbers)) {
            throw new InvalidArgumentException(
                "Invalid configuration for rule: {$code}"
            );
        }

        return $this->normalizeNumbers($numbers);
    }

    /**
     * P = ပတ်သီး
     *
     * Example:
     *
     * 1234P
     *
     * produces:
     *
     * 11,12,13,14,
     * 21,22,23,24,
     * 31,32,33,34,
     * 41,42,43,44
     *
     * Total = 16
     */
    protected function expandP(?string $argument): array
    {
        if ($argument === null || $argument === '') {
            throw new InvalidArgumentException(
                'P rule requires digit list such as 1234P.'
            );
        }

        $argument = strtoupper(trim($argument));

        /*
         * Accept:
         *
         * 1234
         * 12
         * 123
         * etc.
         */
        if (! preg_match('/^\d+$/', $argument)) {
            throw new InvalidArgumentException(
                "Invalid P argument: {$argument}"
            );
        }

        /*
         * Keep digit order but remove duplicate digits.
         */
        $digits = [];

        foreach (str_split($argument) as $digit) {
            if (! in_array($digit, $digits, true)) {
                $digits[] = $digit;
            }
        }

        if (count($digits) < 1) {
            throw new InvalidArgumentException(
                'P rule requires at least one digit.'
            );
        }

        $numbers = [];

        foreach ($digits as $first) {
            foreach ($digits as $second) {
                $numbers[] = $first.$second;
            }
        }

        return $this->normalizeNumbers($numbers);
    }

    /**
     * Parity rules.
     *
     * ++ = even/even
     * -- = odd/odd
     * +- = even/odd
     * -+ = odd/even
     */
    protected function expandParity(
        string $frontParity,
        string $backParity
    ): array {
        $numbers = [];

        for ($front = 0; $front <= 9; $front++) {
            for ($back = 0; $back <= 9; $back++) {
                $frontIsEven = ($front % 2 === 0);
                $backIsEven = ($back % 2 === 0);

                $frontMatches = $frontParity === 'even'
                    ? $frontIsEven
                    : ! $frontIsEven;

                $backMatches = $backParity === 'even'
                    ? $backIsEven
                    : ! $backIsEven;

                if ($frontMatches && $backMatches) {
                    $numbers[] = $front.$back;
                }
            }
        }

        return $this->normalizeNumbers($numbers);
    }

    /**
     * R = reverse
     *
     * 12R = 12,21
     * 11R = 11 only
     *
     * NORMAL/R bracket is handled by parser.
     */
    protected function expandReverse(?string $argument): array
    {
        if ($argument === null || $argument === '') {
            throw new InvalidArgumentException(
                'R rule requires a 2-digit number.'
            );
        }

        $argument = strtoupper(trim($argument));

        if (! preg_match('/^\d{2}$/', $argument)) {
            throw new InvalidArgumentException(
                "Invalid R argument: {$argument}"
            );
        }

        $reverse = $argument[1].$argument[0];

        /*
         * Same number after reversing:
         * 11 -> only 11
         */
        if ($argument === $reverse) {
            return [$argument];
        }

        return [
            $argument,
            $reverse,
        ];
    }

    /**
     * Apply exclusions to a generated number list.
     *
     * Example:
     *
     * A[2233]
     *
     * removes 22 and 33.
     */
    public function applyExclusions(
        array $numbers,
        array $exclusions,
        ?int $adminId = null
    ): array {
        $numbers = $this->normalizeNumbers($numbers);

        if (empty($exclusions)) {
            return $numbers;
        }

        $excludedNumbers = [];

        foreach ($exclusions as $exclusion) {
            if (is_string($exclusion)) {
                $exclusion = strtoupper(trim($exclusion));
            }

            /*
             * Direct 2D number
             */
            if (
                is_string($exclusion)
                && preg_match('/^\d{2}$/', $exclusion)
            ) {
                $excludedNumbers[] = $exclusion;

                continue;
            }

            /*
             * A
             */
            if ($exclusion === 'A') {
                $excludedNumbers = array_merge(
                    $excludedNumbers,
                    $this->expandA()
                );

                continue;
            }

            /*
             * W / N / X
             */
            if (in_array($exclusion, ['W', 'N', 'X'], true)) {
                $excludedNumbers = array_merge(
                    $excludedNumbers,
                    $this->expandConfiguredRule($exclusion, $adminId)
                );

                continue;
            }

            /*
             * Parity codes
             */
            if ($exclusion === '++') {
                $excludedNumbers = array_merge(
                    $excludedNumbers,
                    $this->expandParity('even', 'even')
                );

                continue;
            }

            if ($exclusion === '--') {
                $excludedNumbers = array_merge(
                    $excludedNumbers,
                    $this->expandParity('odd', 'odd')
                );

                continue;
            }

            if ($exclusion === '+-') {
                $excludedNumbers = array_merge(
                    $excludedNumbers,
                    $this->expandParity('even', 'odd')
                );

                continue;
            }

            if ($exclusion === '-+') {
                $excludedNumbers = array_merge(
                    $excludedNumbers,
                    $this->expandParity('odd', 'even')
                );

                continue;
            }

            /*
             * B:
             *
             * 2B
             * 9B
             */
            if (
                is_string($exclusion)
                && preg_match('/^\dB$/', $exclusion)
            ) {
                $excludedNumbers = array_merge(
                    $excludedNumbers,
                    $this->expandB($exclusion[0])
                );

                continue;
            }

            /*
             * F:
             *
             * 1F
             * F1
             */
            if (
                is_string($exclusion)
                && (
                    preg_match('/^\dF$/', $exclusion)
                    || preg_match('/^F\d$/', $exclusion)
                )
            ) {
                $excludedNumbers = array_merge(
                    $excludedNumbers,
                    $this->expandF($exclusion)
                );

                continue;
            }

            /*
             * P:
             *
             * 1234P
             */
            if (
                is_string($exclusion)
                && preg_match('/^(\d+)P$/', $exclusion, $match)
            ) {
                $excludedNumbers = array_merge(
                    $excludedNumbers,
                    $this->expandP($match[1])
                );

                continue;
            }

            if (
                is_string($exclusion)
                && preg_match('/^[A-Z]$/', $exclusion)
                && ! in_array($exclusion, ['A', 'B', 'F', 'N', 'P', 'R', 'W', 'X'], true)
            ) {
                $excludedNumbers = array_merge(
                    $excludedNumbers,
                    $this->expandConfiguredRule($exclusion, $adminId)
                );

                continue;
            }

            throw new InvalidArgumentException(
                "Invalid exclusion rule: {$exclusion}"
            );
        }

        $excludedNumbers = $this->normalizeNumbers(
            $excludedNumbers
        );

        /*
         * array_diff preserves the original base-rule order.
         */
        $result = array_values(
            array_diff($numbers, $excludedNumbers)
        );

        return $result;
    }

    /**
     * Normalize 2D number list.
     *
     * - uppercase
     * - trim
     * - two digits
     * - remove duplicates
     * - preserve first occurrence order
     */
    public function normalizeNumbers(array $numbers): array
    {
        $result = [];

        foreach ($numbers as $number) {
            if (! is_string($number) && ! is_numeric($number)) {
                continue;
            }

            $number = trim((string) $number);

            /*
             * Convert numeric single digit to 0x.
             */
            if (preg_match('/^\d$/', $number)) {
                $number = '0'.$number;
            }

            if (! preg_match('/^\d{2}$/', $number)) {
                throw new InvalidArgumentException(
                    "Invalid 2D number: {$number}"
                );
            }

            if (! in_array($number, $result, true)) {
                $result[] = $number;
            }
        }

        return $result;
    }

    /**
     * Move a leading-zero number to the end.
     *
     * Example:
     *
     * 02,11,20,39,...
     *
     * becomes:
     *
     * 11,20,39,...,02
     */
    protected function moveLeadingZeroToEnd(array $numbers): array
    {
        $zeroLeading = [];
        $others = [];

        foreach ($numbers as $number) {
            if (str_starts_with($number, '0')) {
                $zeroLeading[] = $number;
            } else {
                $others[] = $number;
            }
        }

        return array_merge($others, $zeroLeading);
    }
}
