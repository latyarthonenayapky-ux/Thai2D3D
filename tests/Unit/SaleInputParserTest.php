<?php

namespace Tests\Unit;

use App\Services\SaleInputParser;
use Database\Seeders\CodeRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SaleInputParserTest extends TestCase
{
    use RefreshDatabase;

    protected SaleInputParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CodeRuleSeeder::class);

        $this->parser = app(SaleInputParser::class);
    }

    /*
    |--------------------------------------------------------------------------
    | NORMAL
    |--------------------------------------------------------------------------
    */

    public function test_normal_input(): void
    {
        $result = $this->parser->parse('121000');

        $this->assertSame('12', $result['numbers'][0]);
        $this->assertSame(1000, $result['amount']);
        $this->assertSame(1000, $result['total_amount']);
        $this->assertSame(1, $result['number_count']);
    }

    public function test_normal_with_long_amount(): void
    {
        $result = $this->parser->parse('1210000');

        $this->assertSame('12', $result['numbers'][0]);
        $this->assertSame(10000, $result['amount']);
    }

    public function test_normal_preserves_00(): void
    {
        $result = $this->parser->parse('00500');

        $this->assertSame('00', $result['numbers'][0]);
        $this->assertSame(500, $result['amount']);
    }

    /*
    |--------------------------------------------------------------------------
    | A
    |--------------------------------------------------------------------------
    */

    public function test_a_rule(): void
    {
        $result = $this->parser->parse('A1000');

        $this->assertSame(
            [
                '00', '11', '22', '33', '44',
                '55', '66', '77', '88', '99',
            ],
            $result['numbers']
        );

        $this->assertSame(10, $result['number_count']);
        $this->assertSame(10000, $result['total_amount']);
    }

    public function test_a_rule_with_exclusion(): void
    {
        $result = $this->parser->parse('A[2233]1000');

        $this->assertSame(
            [
                '00', '11', '44', '55',
                '66', '77', '88', '99',
            ],
            $result['numbers']
        );

        $this->assertSame(8, $result['number_count']);
        $this->assertSame(8000, $result['total_amount']);
    }

    /*
    |--------------------------------------------------------------------------
    | R
    |--------------------------------------------------------------------------
    */

    public function test_reverse_rule(): void
    {
        $result = $this->parser->parse('12R1000');

        $this->assertSame(
            ['12', '21'],
            $result['numbers']
        );

        $this->assertSame(2, $result['number_count']);
        $this->assertSame(2000, $result['total_amount']);
    }

    public function test_reverse_same_digit_is_not_duplicate(): void
    {
        $result = $this->parser->parse('11R1000');

        $this->assertSame(
            ['11'],
            $result['numbers']
        );

        $this->assertSame(1, $result['number_count']);
        $this->assertSame(1000, $result['total_amount']);
    }

    public function test_reverse_can_exclude_a_number(): void
    {
        $result = $this->parser->parse('12R[21]1000');

        $this->assertSame(['12'], $result['numbers']);
        $this->assertSame(['21'], $result['excluded_numbers']);
        $this->assertSame(['12', '21'], $result['generated_numbers']);
    }

    /*
    |--------------------------------------------------------------------------
    | B
    |--------------------------------------------------------------------------
    */

    public function test_b_rule(): void
    {
        $result = $this->parser->parse('2B1000');

        $this->assertCount(10, $result['numbers']);

        $this->assertContains('11', $result['numbers']);
        $this->assertContains('20', $result['numbers']);
        $this->assertContains('39', $result['numbers']);
        $this->assertContains('48', $result['numbers']);
        $this->assertContains('57', $result['numbers']);
        $this->assertContains('66', $result['numbers']);
        $this->assertContains('75', $result['numbers']);
        $this->assertContains('84', $result['numbers']);
        $this->assertContains('93', $result['numbers']);
        $this->assertContains('02', $result['numbers']);
    }

    /*
    |--------------------------------------------------------------------------
    | F
    |--------------------------------------------------------------------------
    */

    public function test_front_f_rule(): void
    {
        $result = $this->parser->parse('1F1000');

        $this->assertSame(
            [
                '11', '12', '13', '14', '15',
                '16', '17', '18', '19', '10',
            ],
            $result['numbers']
        );

        $this->assertSame(10, $result['number_count']);
    }

    public function test_back_f_rule(): void
    {
        $result = $this->parser->parse('F11000');

        $this->assertSame(
            [
                '11', '21', '31', '41', '51',
                '61', '71', '81', '91', '01',
            ],
            $result['numbers']
        );

        $this->assertSame(10, $result['number_count']);
    }

    /*
    |--------------------------------------------------------------------------
    | W
    |--------------------------------------------------------------------------
    */

    public function test_w_rule(): void
    {
        $result = $this->parser->parse('W1000');

        $this->assertSame(
            [
                '05', '16', '27', '38', '49',
                '94', '83', '72', '61', '50',
            ],
            $result['numbers']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | N
    |--------------------------------------------------------------------------
    */

    public function test_n_rule(): void
    {
        $result = $this->parser->parse('N1000');

        $this->assertSame(
            [
                '07', '18', '24', '35', '42',
                '53', '69', '70', '81', '96',
            ],
            $result['numbers']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | X
    |--------------------------------------------------------------------------
    */

    public function test_x_rule(): void
    {
        $result = $this->parser->parse('X1000');

        $this->assertSame(
            [
                '12', '23', '34', '45', '56',
                '67', '78', '89', '90', '10',
                '09', '98', '87', '76', '65',
                '54', '43', '32', '21',
            ],
            $result['numbers']
        );

        $this->assertSame(19, $result['number_count']);
    }

    /*
    |--------------------------------------------------------------------------
    | P
    |--------------------------------------------------------------------------
    */

    public function test_p_rule(): void
    {
        $result = $this->parser->parse('1234P1000');

        $this->assertSame(
            [
                '11', '12', '13', '14',
                '21', '22', '23', '24',
                '31', '32', '33', '34',
                '41', '42', '43', '44',
            ],
            $result['numbers']
        );

        $this->assertSame(16, $result['number_count']);
        $this->assertSame(16000, $result['total_amount']);
    }

    public function test_p_rule_exclude_a(): void
    {
        $result = $this->parser->parse('1234P[A]1000');

        $this->assertSame(
            [
                '12', '13', '14',
                '21', '23', '24',
                '31', '32', '34',
                '41', '42', '43',
            ],
            $result['numbers']
        );

        $this->assertSame(12, $result['number_count']);
        $this->assertSame(12000, $result['total_amount']);
    }

    /*
    |--------------------------------------------------------------------------
    | PARITY
    |--------------------------------------------------------------------------
    */

    public function test_even_even_rule(): void
    {
        $result = $this->parser->parse('++1000');

        $this->assertSame(25, $result['number_count']);

        $this->assertContains('00', $result['numbers']);
        $this->assertContains('24', $result['numbers']);
        $this->assertContains('88', $result['numbers']);

        $this->assertNotContains('11', $result['numbers']);
        $this->assertNotContains('12', $result['numbers']);
    }

    public function test_odd_odd_rule(): void
    {
        $result = $this->parser->parse('--1000');

        $this->assertSame(25, $result['number_count']);

        $this->assertContains('11', $result['numbers']);
        $this->assertContains('99', $result['numbers']);
        $this->assertNotContains('12', $result['numbers']);
    }

    public function test_even_odd_rule(): void
    {
        $result = $this->parser->parse('+-1000');

        $this->assertSame(25, $result['number_count']);

        $this->assertContains('01', $result['numbers']);
        $this->assertContains('23', $result['numbers']);
        $this->assertNotContains('12', $result['numbers']);
    }

    public function test_odd_even_rule(): void
    {
        $result = $this->parser->parse('-+1000');

        $this->assertSame(25, $result['number_count']);

        $this->assertContains('10', $result['numbers']);
        $this->assertContains('32', $result['numbers']);
        $this->assertNotContains('23', $result['numbers']);
    }

    /*
    |--------------------------------------------------------------------------
    | BRACKET
    |--------------------------------------------------------------------------
    */

    public function test_parity_can_exclude_a(): void
    {
        $result = $this->parser->parse('++[A]1000');

        $this->assertSame(20, $result['number_count']);

        $this->assertNotContains('00', $result['numbers']);
        $this->assertNotContains('22', $result['numbers']);
        $this->assertNotContains('44', $result['numbers']);
        $this->assertNotContains('66', $result['numbers']);
        $this->assertNotContains('88', $result['numbers']);
    }

    public function test_parity_can_exclude_n(): void
    {
        $result = $this->parser->parse('++[N]1000');

        $this->assertSame(23, $result['number_count']);

        $this->assertNotContains('18', $result['numbers']);
        $this->assertNotContains('24', $result['numbers']);
        $this->assertNotContains('42', $result['numbers']);
        $this->assertNotContains('70', $result['numbers']);
    }

    public function test_f_can_exclude_direct_number(): void
    {
        $result = $this->parser->parse('1F[11]1000');

        $this->assertSame(9, $result['number_count']);
        $this->assertNotContains('11', $result['numbers']);
    }

    public function test_b_can_exclude_direct_number(): void
    {
        $result = $this->parser->parse('2B[11]1000');

        $this->assertSame(9, $result['number_count']);
        $this->assertNotContains('11', $result['numbers']);
    }

    /*
    |--------------------------------------------------------------------------
    | ALL EXCLUDED
    |--------------------------------------------------------------------------
    */

    public function test_all_numbers_excluded_is_error(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->parser->parse('A[A]1000');
    }

    /*
    |--------------------------------------------------------------------------
    | INVALID INPUT
    |--------------------------------------------------------------------------
    */

    public function test_empty_input_is_error(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->parser->parse('');
    }

    public function test_space_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->parser->parse('12 1000');
    }

    public function test_normal_requires_at_least_three_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->parser->parse('12');
    }

    public function test_invalid_b_rule_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->parser->parse('10B1000');
    }

    public function test_invalid_f_rule_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->parser->parse('AF1000');
    }

    public function test_b_rule_can_exclude_a_direct_number(): void
    {
        $result = $this->parser->parse('2B[11]1000');

        $this->assertNotContains('11', $result['numbers']);
        $this->assertContains('11', $result['generated_numbers']);
        $this->assertSame(['11'], $result['excluded_numbers']);
    }

    public function test_excluded_generated_numbers_remain_available_for_audit(): void
    {
        $result = $this->parser->parse('A[2233]1000');

        $this->assertCount(10, $result['generated_numbers']);
        $this->assertCount(8, $result['numbers']);
        $this->assertSame(['22', '33'], $result['excluded_numbers']);
    }

    public function test_leading_and_trailing_whitespace_are_rejected(): void
    {
        foreach ([' 121000', "121000\n"] as $input) {
            try {
                $this->parser->parse($input);
                $this->fail('Whitespace should be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_legacy_star_list_and_multi_reverse_are_supported(): void
    {
        $list = $this->parser->parse('77*88*99*44*55*00/33=2000');
        $reverse = $this->parser->parse('30*85R1500');

        $this->assertSame('LEGACY', $list['type']);
        $this->assertSame(
            ['77', '88', '99', '44', '55', '00'],
            $list['numbers']
        );
        $this->assertSame(['33'], $list['excluded_numbers']);
        $this->assertSame(
            ['30', '03', '85', '58'],
            $reverse['numbers']
        );
        $this->assertSame('LEGACY_R', $reverse['type']);
    }

    public function test_parser_allows_repeated_sale_inputs(): void
    {
        $first = $this->parser->parse('121000');
        $second = $this->parser->parse('121000');

        $this->assertSame($first['numbers'], $second['numbers']);
        $this->assertSame($first['amount'], $second['amount']);
    }
}
