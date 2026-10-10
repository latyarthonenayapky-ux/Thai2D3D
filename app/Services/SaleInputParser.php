<?php

namespace App\Services;

use App\Models\CodeRule;
use InvalidArgumentException;

class SaleInputParser
{
    public function __construct(
        protected NumberRuleEngine $engine,
        protected LegacySaleInputParser $legacyParser
    ) {}

    /**
     * Parse one complete sale input.
     *
     * Examples:
     *
     * 121000
     * A1000
     * A[2233]1000
     * 12R1000
     * 2B1000
     * 1F1000
     * F11000
     * W1000
     * N1000
     * X1000
     * 1234P1000
     * 1234P[A]1000
     * 1234P[1122]1000
     * 1အပါ1000
     * 1/1000
     * ++1000
     * ++[A]1000
     */
    public function parse(string $input, ?int $adminId = null): array
    {
        $originalInput = $input;

        /*
         * Basic validation.
         *
         * Do not trim before validation: whitespace is invalid input.
         */
        if ($input === '') {
            throw new InvalidArgumentException(
                'Input cannot be empty.'
            );
        }

        /*
         * Uppercase only.
         *
         * Lowercase is rejected rather than silently accepted.
         */
        if ($input !== strtoupper($input)) {
            throw new InvalidArgumentException(
                'Lowercase input is not allowed.'
            );
        }

        /*
         * Normalize.
         */
        $normalized = strtoupper($input);

        $digitIncludes = $this->parseDigitIncludes($originalInput, $normalized);
        if ($digitIncludes !== null) {
            return $digitIncludes;
        }

        /*
         * Spaces are not allowed in other sale-input forms.
         */
        if (preg_match('/\s/', $input)) {
            throw new InvalidArgumentException(
                'Spaces are not allowed.'
            );
        }

        /*
         * NORMAL rule
         *
         * Example:
         *
         * 121000
         * number = 12
         * amount = 1000
         *
         * 1210000
         * number = 12
         * amount = 10000
         *
         * 00500
         * number = 00
         * amount = 500
         */
        if (preg_match('/^\d+$/', $normalized)) {
            return $this->parseNormal(
                $originalInput,
                $normalized
            );
        }

        /*
         * Extract amount and body.
         */
        [$body, $amount] = $this->splitBodyAndAmount(
            $normalized
        );

        if ($body === '') {
            throw new InvalidArgumentException(
                'Invalid input body.'
            );
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException(
                'Amount must be greater than zero.'
            );
        }

        $legacyResult = $this->legacyParser->parse(
            $body,
            $amount,
            $originalInput,
            $normalized
        );

        if ($legacyResult !== null) {
            return $legacyResult;
        }

        /*
         * Extract bracket.
         */
        [$bodyWithoutBracket, $bracketContent] =
            $this->extractBracket($body);

        /*
         * Extract legacy slash exclusion.
         *
         * Kept for compatibility with previously discussed
         * input styles.
         */
        [$mainBody, $slashContent] =
            $this->extractSlashExclusion(
                $bodyWithoutBracket
            );

        /*
         * Detect main rule.
         */
        $rule = $this->detectRule($mainBody, $adminId);

        $code = $rule['code'];
        $argument = $rule['argument'];

        /*
         * NORMAL does not allow bracket because it represents one
         * literal number. Rule-based inputs, including R, may exclude.
         */
        if (
            $bracketContent !== null
            && $code === 'NORMAL'
        ) {
            throw new InvalidArgumentException(
                "{$code} rule does not allow bracket exclusion."
            );
        }

        /*
         * Generate base numbers.
         */
        if ($code === 'NORMAL') {
            $numbers = [$argument];
        } else {
            $numbers = $this->engine->expand(
                $code,
                $argument,
                $adminId
            );
        }

        $generatedNumbers = $this->engine->normalizeNumbers($numbers);

        /*
         * Collect exclusions.
         */
        $exclusionTokens = [];

        if ($bracketContent !== null) {
            $exclusionTokens = array_merge(
                $exclusionTokens,
                $this->parseBracketTokens(
                    $bracketContent,
                    $adminId
                )
            );
        }

        if ($slashContent !== null) {
            $exclusionTokens = array_merge(
                $exclusionTokens,
                $this->parseSlashTokens(
                    $slashContent
                )
            );
        }

        /*
         * Apply exclusions.
         */
        $excludedNumbers = [];

        if (! empty($exclusionTokens)) {
            $before = $numbers;

            $numbers = $this->engine->applyExclusions(
                $numbers,
                $exclusionTokens,
                $adminId
            );

            $excludedNumbers = array_values(
                array_diff($before, $numbers)
            );
        }

        /*
         * No numbers remaining.
         */
        if (empty($numbers)) {
            throw new InvalidArgumentException(
                'All numbers were excluded.'
            );
        }

        /*
         * Final normalization.
         */
        $numbers = $this->engine->normalizeNumbers(
            $numbers
        );

        $excludedNumbers = $this->engine->normalizeNumbers(
            $excludedNumbers
        );

        $numberCount = count($numbers);

        $totalAmount = $numberCount * $amount;

        return [
            'original_input' => $originalInput,
            'normalized_input' => $normalized,

            'type' => $code,
            'code' => $code,
            'number_argument' => $argument,

            'numbers' => $numbers,
            'generated_numbers' => $generatedNumbers,
            'excluded_numbers' => $excludedNumbers,

            'amount' => $amount,

            /*
             * Keep both names for compatibility.
             */
            'count' => $numberCount,
            'number_count' => $numberCount,

            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Parse NORMAL numeric input.
     */
    protected function parseNormal(
        string $originalInput,
        string $normalized
    ): array {
        if (strlen($normalized) < 3) {
            throw new InvalidArgumentException(
                'Normal input requires at least three digits.'
            );
        }

        $number = substr(
            $normalized,
            0,
            2
        );

        $amountString = substr(
            $normalized,
            2
        );

        if ($amountString === '') {
            throw new InvalidArgumentException(
                'Normal input requires an amount.'
            );
        }

        $amount = $this->parseAmount(
            $amountString
        );

        $numbers = [$number];

        $numberCount = 1;

        return [
            'original_input' => $originalInput,
            'normalized_input' => $normalized,

            'type' => 'NORMAL',
            'code' => 'NORMAL',
            'number_argument' => $number,

            'numbers' => $numbers,
            'generated_numbers' => $numbers,
            'excluded_numbers' => [],

            'amount' => $amount,

            'count' => $numberCount,
            'number_count' => $numberCount,

            'total_amount' => $amount,
        ];
    }

    protected function parseDigitIncludes(string $originalInput, string $normalized): ?array
    {
        $matched = preg_match(
            '/^([0-9])[ \t]*အပါ[ \t]*(?:=|\/)?[ \t]*([0-9]+)$/u',
            $normalized,
            $match
        ) === 1 || preg_match('/^([0-9])\/([0-9]+)$/', $normalized, $match) === 1;

        if (! $matched) {
            return null;
        }

        $digit = $match[1];
        $amount = $this->parseAmount($match[2]);
        $numbers = $this->engine->normalizeNumbers(array_merge(
            $this->engine->expand('F', $digit.'F'),
            $this->engine->expand('F', 'F'.$digit)
        ));
        $numberCount = count($numbers);

        return [
            'original_input' => $originalInput,
            'normalized_input' => $normalized,
            'type' => 'INCLUDES',
            'code' => 'INCLUDES',
            'number_argument' => $digit,
            'numbers' => $numbers,
            'generated_numbers' => $numbers,
            'excluded_numbers' => [],
            'amount' => $amount,
            'count' => $numberCount,
            'number_count' => $numberCount,
            'total_amount' => $numberCount * $amount,
        ];
    }

    /**
     * Split rule body from amount.
     *
     * Supported:
     *
     * A1000
     * 2B1000
     * 1F1000
     * F11000
     * 1234P1000
     * ++1000
     * A[2233]1000
     * ++[A]1000
     *
     * Explicit '=' is also supported:
     *
     * A=1000
     * 1F=1000
     * F1=1000
     */
    protected function splitBodyAndAmount(
        string $input
    ): array {
        /*
         * Explicit equals syntax.
         *
         * Example:
         *
         * A=1000
         * F1=1000
         */
        if (str_contains($input, '=')) {
            if (substr_count($input, '=') !== 1) {
                throw new InvalidArgumentException(
                    'Invalid amount separator.'
                );
            }

            [$body, $amountString] =
                explode('=', $input, 2);

            $body = trim($body);
            $amountString = trim($amountString);

            if ($body === '') {
                throw new InvalidArgumentException(
                    'Rule body cannot be empty.'
                );
            }

            $amount = $this->parseAmount(
                $amountString
            );

            return [
                $body,
                $amount,
            ];
        }

        /*
         * ---------------------------------------------------------
         * IMPORTANT: F1 special case
         * ---------------------------------------------------------
         *
         * F11000
         *
         * must be interpreted as:
         *
         * body   = F1
         * amount = 1000
         *
         * The generic trailing-number regex below cannot safely
         * distinguish this because both F1 and 11000 are digits.
         *
         * Therefore handle F + one digit + amount first.
         */
        if (
            preg_match(
                '/^(F\d)(\d+)$/',
                $input,
                $match
            )
        ) {
            $body = $match[1];
            $amountString = $match[2];

            $amount = $this->parseAmount(
                $amountString
            );

            return [
                $body,
                $amount,
            ];
        }

        /*
         * ---------------------------------------------------------
         * Generic coded-rule input
         * ---------------------------------------------------------
         *
         * Examples:
         *
         * A1000
         * 2B1000
         * 1F1000
         * 1234P1000
         * ++1000
         * A[2233]1000
         * ++[A]1000
         */
        if (
            ! preg_match(
                '/^(.+?)(\d+)$/',
                $input,
                $match
            )
        ) {
            throw new InvalidArgumentException(
                'Amount is missing.'
            );
        }

        $body = trim($match[1]);
        $amountString = $match[2];

        if ($body === '') {
            throw new InvalidArgumentException(
                'Rule body cannot be empty.'
            );
        }

        $amount = $this->parseAmount(
            $amountString
        );

        return [
            $body,
            $amount,
        ];
    }

    protected function parseAmount(
        string $amountString
    ): int {
        if (
            $amountString === ''
            || ! preg_match('/^\d+$/', $amountString)
        ) {
            throw new InvalidArgumentException(
                'Invalid amount.'
            );
        }

        $amount = (int) $amountString;

        if ($amount <= 0) {
            throw new InvalidArgumentException(
                'Amount must be greater than zero.'
            );
        }

        return $amount;
    }

    /**
     * Extract one bracket:
     *
     * A[2233]
     * ++[A]
     * 1234P[A]
     */
    protected function extractBracket(
        string $body
    ): array {
        $openCount = substr_count(
            $body,
            '['
        );

        $closeCount = substr_count(
            $body,
            ']'
        );

        if ($openCount !== $closeCount) {
            throw new InvalidArgumentException(
                'Invalid bracket syntax.'
            );
        }

        if ($openCount > 1) {
            throw new InvalidArgumentException(
                'Only one bracket block is allowed.'
            );
        }

        if ($openCount === 0) {
            return [
                $body,
                null,
            ];
        }

        if (
            ! preg_match(
                '/^(.*?)\[([^\]]*)\]$/',
                $body,
                $match
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid bracket syntax.'
            );
        }

        $mainBody = $match[1];
        $bracketContent = $match[2];

        if ($bracketContent === '') {
            throw new InvalidArgumentException(
                'Bracket cannot be empty.'
            );
        }

        return [
            $mainBody,
            $bracketContent,
        ];
    }

    /**
     * Legacy slash exclusion.
     *
     * Example:
     *
     * 77*88*99*44*55*00/33
     */
    protected function extractSlashExclusion(
        string $body
    ): array {
        if (! str_contains($body, '/')) {
            return [
                $body,
                null,
            ];
        }

        if (substr_count($body, '/') !== 1) {
            throw new InvalidArgumentException(
                'Invalid slash exclusion syntax.'
            );
        }

        [$mainBody, $slashContent] =
            explode('/', $body, 2);

        if (
            $mainBody === ''
            || $slashContent === ''
        ) {
            throw new InvalidArgumentException(
                'Invalid slash exclusion syntax.'
            );
        }

        return [
            $mainBody,
            $slashContent,
        ];
    }

    /**
     * Detect main rule.
     */
    protected function detectRule(
        string $body,
        ?int $adminId = null
    ): array {
        $body = strtoupper(trim($body));

        /*
         * A
         */
        if ($body === 'A') {
            return [
                'code' => 'A',
                'argument' => null,
            ];
        }

        /*
         * B
         *
         * 2B
         * 9B
         */
        if (
            preg_match(
                '/^(\d)B$/',
                $body,
                $match
            )
        ) {
            return [
                'code' => 'B',
                'argument' => $match[1],
            ];
        }

        /*
         * F front
         *
         * 1F
         */
        if (
            preg_match(
                '/^(\d)F$/',
                $body,
                $match
            )
        ) {
            return [
                'code' => 'F',
                'argument' => $match[1].'F',
            ];
        }

        /*
         * F back
         *
         * F1
         */
        if (
            preg_match(
                '/^F(\d)$/',
                $body,
                $match
            )
        ) {
            return [
                'code' => 'F',
                'argument' => 'F'.$match[1],
            ];
        }

        /*
         * W
         */
        if ($body === 'W') {
            return [
                'code' => 'W',
                'argument' => null,
            ];
        }

        /*
         * N
         */
        if ($body === 'N') {
            return [
                'code' => 'N',
                'argument' => null,
            ];
        }

        /*
         * X
         */
        if ($body === 'X') {
            return [
                'code' => 'X',
                'argument' => null,
            ];
        }

        /*
         * P
         *
         * 1234P
         */
        if (
            preg_match(
                '/^(\d+)P$/',
                $body,
                $match
            )
        ) {
            return [
                'code' => 'P',
                'argument' => $match[1],
            ];
        }

        /*
         * Parity
         */
        if (
            in_array(
                $body,
                [
                    '++',
                    '--',
                    '+-',
                    '-+',
                ],
                true
            )
        ) {
            return [
                'code' => $body,
                'argument' => null,
            ];
        }

        /*
         * R
         *
         * 12R
         */
        if (
            preg_match(
                '/^(\d{2})R$/',
                $body,
                $match
            )
        ) {
            return [
                'code' => 'R',
                'argument' => $match[1],
            ];
        }

        if (
            preg_match('/^[A-Z]$/', $body)
            && ! in_array($body, ['A', 'B', 'F', 'N', 'P', 'R', 'W', 'X'], true)
            && $this->hasActiveConfiguredRule($body, $adminId)
        ) {
            return [
                'code' => $body,
                'argument' => null,
            ];
        }

        /*
         * Legacy multiple-R form.
         *
         * Example previously discussed:
         *
         * 30*85R
         *
         * This is intentionally rejected here because the
         * locked current business rule defines R as:
         *
         * 12R = 12 + 21
         *
         * If multiple R bases are required later, it can be
         * implemented explicitly without changing the current
         * syntax.
         */
        if (str_ends_with($body, 'R')) {
            throw new InvalidArgumentException(
                'R rule requires exactly one 2-digit number.'
            );
        }

        throw new InvalidArgumentException(
            "Unknown rule: {$body}"
        );
    }

    /**
     * Parse bracket content.
     *
     * Examples:
     *
     * [2233]
     * [A]
     * [N]
     * [W]
     * [A2233]
     * [1122]
     * [++]
     * [1F]
     * [F1]
     * [2B]
     * [1234P]
     *
     * Direct numbers are read in 2-digit pairs.
     */
    protected function parseBracketTokens(
        string $content,
        ?int $adminId = null
    ): array {
        $content = strtoupper(trim($content));

        if ($content === '') {
            throw new InvalidArgumentException(
                'Bracket cannot be empty.'
            );
        }

        /*
         * If the whole content is digits:
         *
         * 2233 => 22,33
         * 1122 => 11,22
         */
        if (preg_match('/^\d+$/', $content)) {
            if (strlen($content) % 2 !== 0) {
                throw new InvalidArgumentException(
                    'Bracket number list must contain pairs of digits.'
                );
            }

            $tokens = [];

            for (
                $i = 0;
                $i < strlen($content);
                $i += 2
            ) {
                $tokens[] = substr(
                    $content,
                    $i,
                    2
                );
            }

            return $tokens;
        }

        /*
         * Parse symbolic tokens sequentially.
         */
        $tokens = [];

        $position = 0;
        $length = strlen($content);

        while ($position < $length) {
            /*
             * ++
             */
            if (
                substr($content, $position, 2)
                === '++'
            ) {
                $tokens[] = '++';
                $position += 2;

                continue;
            }

            /*
             * --
             */
            if (
                substr($content, $position, 2)
                === '--'
            ) {
                $tokens[] = '--';
                $position += 2;

                continue;
            }

            /*
             * +-
             */
            if (
                substr($content, $position, 2)
                === '+-'
            ) {
                $tokens[] = '+-';
                $position += 2;

                continue;
            }

            /*
             * -+
             */
            if (
                substr($content, $position, 2)
                === '-+'
            ) {
                $tokens[] = '-+';
                $position += 2;

                continue;
            }

            /*
             * A
             */
            if (
                substr($content, $position, 1)
                === 'A'
            ) {
                $tokens[] = 'A';
                $position++;

                continue;
            }

            /*
             * W
             */
            if (
                substr($content, $position, 1)
                === 'W'
            ) {
                $tokens[] = 'W';
                $position++;

                continue;
            }

            /*
             * N
             */
            if (
                substr($content, $position, 1)
                === 'N'
            ) {
                $tokens[] = 'N';
                $position++;

                continue;
            }

            /*
             * X
             */
            if (
                substr($content, $position, 1)
                === 'X'
            ) {
                $tokens[] = 'X';
                $position++;

                continue;
            }

            if (
                preg_match('/^[A-Z]$/', substr($content, $position, 1))
                && ! in_array(substr($content, $position, 1), ['A', 'B', 'F', 'N', 'P', 'R', 'W', 'X'], true)
                && $this->hasActiveConfiguredRule(substr($content, $position, 1), $adminId)
            ) {
                $tokens[] = substr($content, $position, 1);
                $position++;

                continue;
            }

            /*
             * 1F / 2F / ... 9F
             */
            if (
                $position + 1 < $length
                && preg_match(
                    '/^\dF$/',
                    substr($content, $position, 2)
                )
            ) {
                $tokens[] = substr(
                    $content,
                    $position,
                    2
                );

                $position += 2;

                continue;
            }

            /*
             * F1 / F2 / ... F9
             */
            if (
                $position + 1 < $length
                && preg_match(
                    '/^F\d$/',
                    substr($content, $position, 2)
                )
            ) {
                $tokens[] = substr(
                    $content,
                    $position,
                    2
                );

                $position += 2;

                continue;
            }

            /*
             * 2B / 9B
             */
            if (
                $position + 1 < $length
                && preg_match(
                    '/^\dB$/',
                    substr($content, $position, 2)
                )
            ) {
                $tokens[] = substr(
                    $content,
                    $position,
                    2
                );

                $position += 2;

                continue;
            }

            /*
             * P:
             *
             * 1234P
             *
             * Since P is variable-length, it must be at
             * the end of the remaining token stream.
             */
            if (
                preg_match(
                    '/^(\d+)P$/',
                    substr($content, $position),
                    $match
                )
            ) {
                $tokens[] = $match[1].'P';
                $position = $length;

                continue;
            }

            /*
             * Direct number.
             *
             * A direct number inside a mixed bracket:
             *
             * A2233
             *
             * means:
             * A + 22 + 33
             */
            if (
                $position + 1 < $length
                && preg_match(
                    '/^\d{2}$/',
                    substr($content, $position, 2)
                )
            ) {
                $tokens[] = substr(
                    $content,
                    $position,
                    2
                );

                $position += 2;

                continue;
            }

            throw new InvalidArgumentException(
                'Invalid bracket token near: '
                .substr($content, $position)
            );
        }

        return $tokens;
    }

    protected function hasActiveConfiguredRule(string $code, ?int $adminId): bool
    {
        $query = CodeRule::query()
            ->where('code', $code)
            ->where('rule_type', 'number_set')
            ->when(
                $adminId === null,
                fn ($query) => $query->whereNull('admin_id'),
                fn ($query) => $query->where(function ($query) use ($adminId): void {
                    $query->where('admin_id', $adminId)->orWhereNull('admin_id');
                })
            )
            ->orderByRaw('admin_id IS NULL');
        $rule = $query->first();

        return $rule?->is_active ?? false;
    }

    /**
     * Parse slash exclusion.
     *
     * Legacy examples:
     *
     * /33
     * /2233
     */
    protected function parseSlashTokens(
        string $content
    ): array {
        $content = strtoupper(trim($content));

        if ($content === '') {
            throw new InvalidArgumentException(
                'Slash exclusion cannot be empty.'
            );
        }

        /*
         * Direct number list:
         *
         * /2233 => 22,33
         */
        if (preg_match('/^\d+$/', $content)) {
            if (strlen($content) % 2 !== 0) {
                throw new InvalidArgumentException(
                    'Slash number list must contain pairs of digits.'
                );
            }

            $tokens = [];

            for (
                $i = 0;
                $i < strlen($content);
                $i += 2
            ) {
                $tokens[] = substr(
                    $content,
                    $i,
                    2
                );
            }

            return $tokens;
        }

        /*
         * For symbolic slash exclusions, reuse bracket parser.
         */
        return $this->parseBracketTokens(
            $content
        );
    }
}
