<?php

namespace App\Services;

use InvalidArgumentException;

class LegacySaleInputParser
{
    public function __construct(
        protected NumberRuleEngine $engine
    ) {}

    public function parse(
        string $body,
        int $amount,
        string $originalInput,
        string $normalizedInput
    ): ?array {
        if (substr_count($body, '/') > 1) {
            throw new InvalidArgumentException(
                'Invalid legacy exclusion syntax.'
            );
        }

        [$numberList, $exclusionList] = array_pad(
            explode('/', $body, 2),
            2,
            null
        );

        $isReverseList = preg_match(
            '/^\d{2}(?:\*\d{2})+R$/',
            $numberList
        ) === 1;
        $isNumberList = preg_match(
            '/^\d{2}(?:\*\d{2})+$/',
            $numberList
        ) === 1;

        if (! $isReverseList && ! $isNumberList) {
            return null;
        }

        if ($exclusionList === '') {
            throw new InvalidArgumentException(
                'Legacy exclusion cannot be empty.'
            );
        }

        $type = $isReverseList ? 'LEGACY_R' : 'LEGACY';
        $numberList = $isReverseList
            ? substr($numberList, 0, -1)
            : $numberList;

        $generatedNumbers = [];
        foreach (explode('*', $numberList) as $number) {
            $generatedNumbers[] = $number;

            if ($isReverseList) {
                $reverse = $number[1].$number[0];
                if ($reverse !== $number) {
                    $generatedNumbers[] = $reverse;
                }
            }
        }

        $generatedNumbers = $this->engine->normalizeNumbers(
            $generatedNumbers
        );

        $excludedNumbers = $exclusionList === null
            ? []
            : $this->parseNumberPairs($exclusionList);

        $numbers = array_values(array_diff(
            $generatedNumbers,
            $excludedNumbers
        ));

        if ($numbers === []) {
            throw new InvalidArgumentException(
                'All numbers were excluded.'
            );
        }

        return [
            'original_input' => $originalInput,
            'normalized_input' => $normalizedInput,
            'type' => $type,
            'code' => $type,
            'number_argument' => $numberList,
            'numbers' => $numbers,
            'generated_numbers' => $generatedNumbers,
            'excluded_numbers' => $excludedNumbers,
            'amount' => $amount,
            'count' => count($numbers),
            'number_count' => count($numbers),
            'total_amount' => count($numbers) * $amount,
        ];
    }

    protected function parseNumberPairs(string $numbers): array
    {
        if (! preg_match('/^(?:\d{2})+$/', $numbers)) {
            throw new InvalidArgumentException(
                'Legacy exclusions must be pairs of digits.'
            );
        }

        $pairs = str_split($numbers, 2);

        return $this->engine->normalizeNumbers($pairs);
    }
}
