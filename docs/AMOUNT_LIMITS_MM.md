# Amount Limit နှင့် Number Limit စနစ် (2D/3D)

ဤ document သည် ရောင်းသွင်းမှုများကို ကာကွယ်/စောင့်ကြည့်သည့် **Amount Limit** နှင့်
**Number Limit** တို့၏ လက်ရှိစည်းမျဉ်းကို တိတိကျကျ ဖော်ပြသည်။
ကိုডအရင်းအမြစ် - [app/Services/AmountLimitPolicy.php](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/AmountLimitPolicy.php)။

## အနှစ်ချုပ် (ဇယား)

| အရာ | 2D | 3D |
| --- | --- | --- |
| Amount Limit ပုံစံ | **Round တစ်ခုလျှင် ကိန်းဂဏန်း တစ်ခုတည်း** (`rounds.amount_limit_all`) - 00 မှ 99 အထိ နံပါတ်တိုင်းအပေါ် တစ်ပြည့်တည်း ကျင့်သုံးသည် | Tier ၃ ဆင့် - Agent / Operator / Round |
| ရေတွက်ပုံ | ထို Round အောက်ရှိ Agent + Operator **အားလုံးပေါင်း**၊ နံပါတ်တစ်ခုချင်းစီအတွက် | Tier အလိုက် - Agent တစ်ဦးချင်း / Operator တစ်ဦးချင်း (သူကိုင်သော Agent အားလုံးပေါင်း) / Round အားလုံးပေါင်း |
| ကျော်လျှင် ဘာဖြစ်မည် | **အမြဲ လက်ခံ**၊ သတိပေးရုံ (အနီရောင် flag) | Mode ပေါ်မူတည် - `warn` = လက်ခံ + flag၊ `block` = ငြင်းပယ် |
| Enforcement နေရာ | `numberBreaches()` (စစ်ဆေးပြသရုံ) | `assertWithinLimits()` (block mode) |

## Amount Limit ဆိုတာ ဘာကို ရေတွက်သလဲ

"used" ဆိုသည်မှာ ထို scope အတွင်း **အသိအမှတ်ပြုပြီး (status = accepted)** ဖြစ်သော
ရောင်းချမှုများ၏ ပေါင်းလဒ်ဖြစ်သည်။ ထို့ကြောင့် -

- void လုပ်လိုက်သော ရောင်းချမှုသည် limit ကို မစားတော့ပါ (အလိုအလျောက် ပြန်လျှော့သည်)။
- bracket ဖြင့် ဖယ်ထားသော (`is_excluded`) နံပါတ်နှင့် rejected ဖြစ်သော နံပါတ်များလည်း မပါ။
- limit ကျော်သွားသော်လည်း `warn` mode ဖြင့် လက်ခံထားသေးရင် ထိုပမာဏသည် used ထဲ
  ဆက်ပါနေသည် - သတိပေးချက်မှာ "ကြိုတင်သဘောတူထားသော credit ceiling ကျော်နေပြီ" ဟူသော
  အဓိပ္ပာယ်ဖြစ်ပြီး စာရင်းဖောက်သည် မဟုတ်ပါ။

Limit တန်ဖိုး `null` (မထည့်ထား) ဆိုသည်မှာ ထို tier ကို **စောင့်ကြည့်ခြင်း မပြု** ဟု အဓိပ္ပာယ်ရ။

## Mode နှစ်မျိုး

`AmountLimitPolicy::normalizeMode()` သည် `block` မဟုတ်လျှင် အမြဲ `warn` သို့ ပြောင်းသည်။
ပုံသေ default မှာ `warn` ဖြစ်သည် (migration များတွင်လည်း `default('warn')`)။

| Mode | ရလဒ် |
| --- | --- |
| `warn` | Limit ကျော်သော ရောင်းချမှုကို လက်ခံပြီး အနီရောင်/သတိပေး banner ဖြင့် ပြသည် |
| `block` | Limit ကျော်မည့် ရောင်းချမှုကို `AmountLimitExceededException` ဖြင့် ငြင်းပယ်သည် |

## 2D - Round တစ်ခုတည်းသော cap

2D Amount Limit သည် `rounds.amount_limit_all` တစ်ခုတည်း ဖြစ်ပြီး နံပါတ် 00-99
အားလုံးအပေါ် တစ်ပြည့်တည်း ကျင့်သုံးသည်။ အကြောင်းရင်းမှာ 2D တွင် နံပါတ်အလိုက်
cap ချထားရန် မလိုအပ်တော့သောကြောင့် ဖြစ်သည်။

- **ဘယ်တော့မှ block မလုပ်ပါ**။ `SaleProcessor` သည် 2D အတွက် `assertWithinLimits()` ကို
  မခေါ်ပါ။ ကျော်လွန်သော နံပါတ်များကို `numberBreaches()` ဖြင့် စုစည်း၍ Dashboard နှင့်
  Admin sales sheet တွင် အနီရောင် ပြသသည်
  ([app/Services/AmountLimitPolicy.php:107](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/AmountLimitPolicy.php#L107))။
- Output row တွင် `number` (00-99 padded)၊ `limit`၊ `sold`၊ `excess` ပါဝင်ပြီး `excess`
  အများဆုံးအရင်၊ ပြီးလျှင် နံပါတ်စဉ်အရ စီသည်။
- SQLite တွင် `HAVING` နှင့် bound float နှိုင်းယှဉ်ခြင်း is text အဖြစ် ယှဉ်သွားနိုင်သောကြောင့်
  PHP ဘက်တွင် ယှဉ်သည် (00-99 အထက် row အများဆုံး 100 ခုသာရှိ)။

**သမိုင်းကြောင်း**: ယခင်က နံပါတ်အလိုက် cap ကို `round_number_amount_limits` table ထဲ
သိမ်းထားခဲ့သည်။ ထို table ကို `2026_10_14_000002_drop_round_number_amount_limits_table.php`
ဖြင့် ဖျက်ပြီး၊ per-Agent 2D box (`agent_round_settings.amount_limit`) သည် UI ထဲ data
မဖျက်ဘဲ ထားရှိသော်လည်း **enforcement မရှိတော့ပါ**။

## 3D - Tier ၃ ဆင့်

| Tier | Limit သိမ်းထားသည့် နေရာ | ဘယ်သူ့ပိုက်ဆံကို ရေတွက်သလဲ |
| --- | --- | --- |
| Agent | `agent_round_settings.amount_limit_3d` (+ `amount_limit_mode`) | ထို Agent ၏ 3D accepted ရောင်းချမှု |
| Operator | `operator_round_settings.amount_limit_3d` (+ mode) | ထို Operator ရိုက်သွင်းသော 3D အားလုံး (Agent မခွဲ) |
| Round | `rounds.amount_limit_all_3d` (+ `amount_limit_all_mode`) | Round အတွင်း Agent + Operator အားလုံးပေါင်း |

Mode ကို tier တစ်ခုချင်းအလိုက် သိမ်းထားသည်။ `block` tier တစ်ခုတည်းရှိလျှင်ပင် ထို
ရောင်းချမှု ငြင်းပယ်ခံရမည်။ 3D ရောင်းသွင်းချိန် call order -

1. `rounds.number_limit_3d` (နံပါတ်ရေတွက် cap) ကျော်မကောင်း စစ်သည် - ကျော်လျှင်
   `NUMBER_LIMIT_3D` ဖြင့် reject။
2. မကျော်လျှင်သာ `assertWithinLimits(..., '3d')` ခေါ်သည် - `block` tier တွင်
   `AmountLimitExceededException` ထွက်နိုင်
   ([app/Services/ThreeDigitSaleProcessor.php:97](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/ThreeDigitSaleProcessor.php#L97))။

လစဉ် 3D Draw (Round မဟုတ်သော `three_digit_draws`) မှာမူ tier ၃ ဆင့် မသုံးဘဲ
`three_digit_draw_agent_settings.amount_limit` (Agent တစ်ဦးချင်း) ကိုသာ သုံးသည်။

## Admin screen များနှင့် route အမည်များ

| လုပ်ရပ် | Route name |
| --- | --- |
| Round 2D + 3D round-wide cap နှင့် mode | `admin.settings.rounds.amount-limits` |
| Agent အလိုက် 3D limit + mode | `admin.settings.rounds.agents.amount-limit-3d` |
| Operator အလိုက် limits (2D box မပါ) | `admin.settings.rounds.operators.amount-limits` |
| Agent အလိုက် per-number cap ရှိခဲ့သော နေရာ (2D box ဖြည့်ပါကသာ သိမ်း) | `admin.settings.rounds.agents.amount-limit` |

2D box ကို form ထဲ မပါသေးသော screen များမှ submit လုပ်လျှင် သိမ်းထားသော
`amount_limit` တန်ဖိုးကို မထိဘဲ ထားပေးသည်
([app/Http/Controllers/Admin/SettingsController.php:384](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Http/Controllers/Admin/SettingsController.php#L384))။
Save လုပ်ပြီးတိုင်း message သည် mode ၏ အဓိပ္ပာယ်ကိုပါ ပြန်ပြသည်
("A sale that passes this limit is still accepted and flagged in red" /
"A sale that would pass this limit is refused")။

## Number Limit (ကွဲပြားခြားနားသော အရာ)

Amount Limit နှင့် ရောမထားရန် - Number Limit သည် ငွေပမာဏ မဟုတ်ပါ။

| အရာ | ကော်လံ | သဘောတရား |
| --- | --- | --- |
| 2D နံပါတ်တား | `rounds.number_limits` (JSON array) | ထိုစာရင်းထဲပါ နံပါတ်ကို `rejected / NUMBER_LIMIT` ဖြင့် ပယ် |
| 2D Hot number | `hot_numbers` (round + number) | `rejected / HOT_NUMBER` ဖြင့် ပယ် |
| 3D နံပါတ်ရေ cap | `rounds.number_limit_3d`, `three_digit_draws.number_limit` | Accepted နံပါတ်အရေအတွက် ပိုမများစေရန် limit ချ |
| 3D Hot number | `three_digit_hot_numbers` / `three_digit_draw_hot_numbers` | `rejected / HOT_NUMBER_3D` |

Schedule template များ (`round_schedules`) ရှိ `number_limit` / `number_limit_3d` တို့သည်
Round ဖွင့်သည့်အခါ ကူးယူခံရသည်။

## စမ်းသပ်မှုများ

```sh
php artisan test --filter=AmountLimitEnforcementTest
php artisan test --filter=AdminSettingsTest
```

- `tests/Feature/AmountLimitEnforcementTest.php` - warn mode လက်ခံ + flag၊ block mode
  ငြင်းပယ်၊ 2D သည် block မဖြစ်၊ void ဖြင့် used လျော့သွားခြင်း
- `tests/Feature/AdminSettingsTest.php` - limit save/update behavior

## ပြင်ဆင်သူများအတွက် သတိပေးချက်

1. 2D ကို `block` ပြောင်းမည်ဆိုလျှင် **ရောင်းချမှု ဆုံးရှုံးနိုင်သော** business behavior
   ပြောင်းလဲခြင်း ဖြစ်သည်။ လိုအပ်ချက်ကို owner ထံ အရင်အတည်ပြုပါ။
2. `numberBreaches()` သည် `agent_sessions.round_id` ဖြင့် join ရသည်။ Session မရှိသော
   ရောင်းချမှု ထွက်ပေါ်လာလျှင် cap ရေတွက်မှုထဲ မပါဝင်တော့ပါ။
3. Tier သစ် တစ်ခု ထပ်ထည့်လျှင် `tiers()`၊ `breaches()`၊ UI နှင့် tests လေးခုလုံး
   ပြင်ရမည်။
