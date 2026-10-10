<?php

namespace Tests\Unit;

use App\Services\LegacySaleInputParser;
use App\Services\NumberRuleEngine;
use App\Services\SaleInputParser;
use PHPUnit\Framework\TestCase;

class DigitIncludesParserTest extends TestCase
{
    public function test_includes_input_expands_front_and_back_and_deduplicates(): void
    {
        $engine = new NumberRuleEngine();
        $parser = new SaleInputParser($engine, new LegacySaleInputParser($engine));
        $expected = [
            '11', '12', '13', '14', '15',
            '16', '17', '18', '19', '10',
            '21', '31', '41', '51', '61',
            '71', '81', '91', '01',
        ];

        foreach (['1အပါ1000', '1 အပါ 1000', '1/1000'] as $input) {
            $result = $parser->parse($input);

            self::assertSame($expected, $result['numbers']);
            self::assertSame(19, $result['number_count']);
            self::assertSame(1000, $result['amount']);
            self::assertSame(19000, $result['total_amount']);
            self::assertCount(19, array_unique($result['numbers']));
        }
    }
}
