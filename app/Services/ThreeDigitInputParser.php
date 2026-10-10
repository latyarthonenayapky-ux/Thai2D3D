<?php

namespace App\Services;

use InvalidArgumentException;

class ThreeDigitInputParser
{
    public function parse(string $input): array
    {
        if ($input === '') {
            throw new InvalidArgumentException(
                'Input cannot be empty.'
            );
        }

        if ($input !== strtoupper($input)) {
            throw new InvalidArgumentException(
                'Lowercase input is not allowed.'
            );
        }

        if (preg_match('/\s/', $input)) {
            throw new InvalidArgumentException(
                'Spaces are not allowed.'
            );
        }

        if (preg_match('/^A(\d+)$/', $input, $match)) {
            $amount = (int) $match[1];
            if ($amount <= 0) {
                throw new InvalidArgumentException(
                    'Amount must be greater than zero.'
                );
            }

            $numbers = array_map(
                fn (int $digit): string => (string) $digit.$digit.$digit,
                range(0, 9)
            );

            return [
                'original_input' => $input,
                'normalized_input' => $input,
                'type' => 'A_3D',
                'code' => 'A',
                'numbers' => $numbers,
                'number_count' => count($numbers),
                'amount' => $amount,
                'total_amount' => $amount * count($numbers),
            ];
        }

        if (! preg_match('/^(\d{3})(\d+)$/', $input, $match)) {
            throw new InvalidArgumentException(
                'Invalid 3D input.'
            );
        }

        $amount = (int) $match[2];
        if ($amount <= 0) {
            throw new InvalidArgumentException(
                'Amount must be greater than zero.'
            );
        }

        return [
            'original_input' => $input,
            'normalized_input' => $input,
            'type' => 'NORMAL_3D',
            'code' => 'NORMAL_3D',
            'numbers' => [$match[1]],
            'number' => $match[1],
            'number_count' => 1,
            'amount' => $amount,
            'total_amount' => $amount,
        ];
    }
}
