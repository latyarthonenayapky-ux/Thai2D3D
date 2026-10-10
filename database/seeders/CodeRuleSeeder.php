<?php

namespace Database\Seeders;

use App\Models\CodeRule;
use Illuminate\Database\Seeder;

class CodeRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'code' => 'A',
                'name' => 'အပူး',
                'description' => 'တူညီသော digit နှစ်လုံး',
                'rule_type' => 'number_set',
                'rule_config' => [
                    'numbers' => [
                        '00', '11', '22', '33', '44',
                        '55', '66', '77', '88', '99',
                    ],
                ],
                'allow_bracket' => true,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 10,
            ],

            [
                'code' => 'R',
                'name' => 'Reverse',
                'description' => 'ရှေ့နောက်ပြောင်းပြန်',
                'rule_type' => 'reverse',
                'rule_config' => [],
                'allow_bracket' => false,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 20,
            ],

            [
                'code' => 'B',
                'name' => 'ဘရိတ်',
                'description' => 'Digit နှစ်လုံးပေါင်းပြီး နောက်ဆုံး digit ကိုယူခြင်း',
                'rule_type' => 'digit_sum',
                'rule_config' => [
                    'modulo' => 10,
                ],
                'allow_bracket' => true,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 30,
            ],

            [
                'code' => 'F',
                'name' => 'ထိပ် / နောက်ပိတ်',
                'description' => 'ရှေ့ digit သို့မဟုတ် နောက် digit ကိုရွေးခြင်း',
                'rule_type' => 'digit_position',
                'rule_config' => [
                    'positions' => [
                        'front' => true,
                        'back' => true,
                    ],
                ],
                'allow_bracket' => true,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 40,
            ],

            [
                'code' => 'W',
                'name' => 'ပါဝါ',
                'description' => 'ပါဝါဂဏန်း 10 ကွက်',
                'rule_type' => 'number_set',
                'rule_config' => [
                    'numbers' => [
                        '05', '16', '27', '38', '49',
                        '94', '83', '72', '61', '50',
                    ],
                ],
                'allow_bracket' => true,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 50,
            ],

            [
                'code' => 'N',
                'name' => 'နက္ခတ်',
                'description' => 'နက္ခတ်ဂဏန်း 10 ကွက်',
                'rule_type' => 'number_set',
                'rule_config' => [
                    'numbers' => [
                        '07', '18', '24', '35', '42',
                        '53', '69', '70', '81', '96',
                    ],
                ],
                'allow_bracket' => true,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 60,
            ],

            [
                'code' => 'X',
                'name' => 'ညီအကို',
                'description' => 'သတ်မှတ်ထားသော အစဉ်လိုက် / ပြောင်းပြန် ဂဏန်းများ',
                'rule_type' => 'number_set',
                'rule_config' => [
                    'numbers' => [
                        '12', '23', '34', '45', '56',
                        '67', '78', '89', '90', '10',
                        '09', '98', '87', '76', '65',
                        '54', '43', '32', '21',
                    ],
                ],
                'allow_bracket' => true,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 70,
            ],

            [
                'code' => 'P',
                'name' => 'ပတ်သီး',
                'description' => 'သတ်မှတ်ထားသော digit များဖြင့် 2D combination',
                'rule_type' => 'digit_combination',
                'rule_config' => [
                    'digits' => ['1', '2', '3', '4'],
                    'include_doubles' => true,
                ],
                'allow_bracket' => true,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 80,
            ],

            [
                'code' => '++',
                'name' => 'ရှေ့စုံ နောက်စုံ',
                'description' => 'ရှေ့ digit စုံ၊ နောက် digit စုံ',
                'rule_type' => 'parity',
                'rule_config' => [
                    'front' => 'even',
                    'back' => 'even',
                ],
                'allow_bracket' => true,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 90,
            ],

            [
                'code' => '--',
                'name' => 'ရှေ့မ နောက်မ',
                'description' => 'ရှေ့ digit မ၊ နောက် digit မ',
                'rule_type' => 'parity',
                'rule_config' => [
                    'front' => 'odd',
                    'back' => 'odd',
                ],
                'allow_bracket' => true,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 100,
            ],

            [
                'code' => '+-',
                'name' => 'ရှေ့စုံ နောက်မ',
                'description' => 'ရှေ့ digit စုံ၊ နောက် digit မ',
                'rule_type' => 'parity',
                'rule_config' => [
                    'front' => 'even',
                    'back' => 'odd',
                ],
                'allow_bracket' => true,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 110,
            ],

            [
                'code' => '-+',
                'name' => 'ရှေ့မ နောက်စုံ',
                'description' => 'ရှေ့ digit မ၊ နောက် digit စုံ',
                'rule_type' => 'parity',
                'rule_config' => [
                    'front' => 'odd',
                    'back' => 'even',
                ],
                'allow_bracket' => true,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 120,
            ],
        ];

        foreach ($rules as $rule) {
            CodeRule::updateOrCreate(
                ['code' => $rule['code'], 'admin_id' => null],
                $rule
            );
        }
    }
}
