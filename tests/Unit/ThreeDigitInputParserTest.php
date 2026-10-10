<?php

namespace Tests\Unit;

use App\Services\ThreeDigitInputParser;
use InvalidArgumentException;
use Tests\TestCase;

class ThreeDigitInputParserTest extends TestCase
{
    public function test_parses_normal_three_digit_input(): void
    {
        $result = app(ThreeDigitInputParser::class)->parse('123500');

        $this->assertSame('123', $result['number']);
        $this->assertSame(500, $result['amount']);
        $this->assertSame(1, $result['number_count']);
        $this->assertSame(500, $result['total_amount']);
    }

    public function test_rejects_lowercase_and_whitespace(): void
    {
        $parser = app(ThreeDigitInputParser::class);

        foreach (['a23500', '123 500', "123\n500"] as $input) {
            try {
                $parser->parse($input);
                $this->fail("Expected {$input} to be rejected.");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_a_rule_expands_to_all_ten_triples(): void
    {
        $result = app(ThreeDigitInputParser::class)->parse('A1000');

        $this->assertSame(['000', '111', '222', '333', '444', '555', '666', '777', '888', '999'], $result['numbers']);
        $this->assertSame(10, $result['number_count']);
        $this->assertSame(10000, $result['total_amount']);
    }
}
