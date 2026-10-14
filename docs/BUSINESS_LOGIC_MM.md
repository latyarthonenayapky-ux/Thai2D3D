# Business Logic (sale processing, settlement, reports, void, offline sync)

ဤ document သည် code ထဲရှိ business rules များကို အလုပ်လုပ်ပုံအလိုက် ရှင်းပြသည်။
User များအတွက် ရိုက်နည်းများကို သိလိုလျှင် [USER_MANUAL_MM.md](USER_MANUAL_MM.md) §5။

## 1. 2D ရောင်းချမှု တစ်ခွင်၏ ဘဝ ([SaleProcessor](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/SaleProcessor.php))

`process(input, AgentSession, clientUuid, source)` သည် transaction တစ်ခုအတွင်း -

1. **Round and session check** - Round ကို `lockForUpdate` ယူပြီး `status = open` ဖြင့်
   `isPastScheduledClose()` မဟုတ်မှသာ ဆက်သည်။ Close time ကျော်လျှင် cron lag ရှိနေလည်း
   ဘယ်တော့မှ မယူ ([Round.php:84](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Models/Round.php#L84))။
2. **Idempotency** - `client_uuid` ဖြင့် အရင်မှတ်တမ်းရှိလျှင် ပြန်မဖန်တီး။
3. **Ownership** - handler ↔ business ↔ agent ကိုက်ညီမှု၊ AgentRoundSetting ကို ထို handler
   ကပင် ကိုင်ထားရမည် (claim လုပ်ထားမှ)။
4. **Parse** - `SaleInputParser` (uppercase only, space မခွင့်, bracket တစ်ကွက်တည်း) ဖြင့်
   `type`, `code`, `number_argument`, `numbers`, `amount`, `total_amount`။
5. **SaleInput ကို အရင်ဖန်တီး** - ဤအစဉ်အစီကအရေးကြီးသည်: reject ဖြစ်သည့်တိုင်
   audit row ကျန်ရန်။
6. **Generated numbers** - `NumberRuleEngine::expand()` ဖြင့် နံပါတ်စာရင်းထုတ်
   (A/B/F/P/R/W/N/X + custom number lists)။
7. **Number တစ်ခုချင်း** - bracket exclusion → `status = excluded`; HotNumber →
   `rejected / HOT_NUMBER`; `rounds.number_limits` ထဲပါ → `rejected / NUMBER_LIMIT`;
   ကျန်လျှင် `accepted`။
8. **အားလုံး ပယ်ခံလျှင်** SaleInput → `rejected / ALL_NUMBERS_REJECTED`။

2D တွင် Amount Limit ကြောင့် **ဘယ်တော့မှ မပယ်ပါ** - [Amount Limit document](AMOUNT_LIMITS_MM.md)။

## 2. 3D ([ThreeDigitSaleProcessor](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/ThreeDigitSaleProcessor.php))

Two entry points -

- `process()` - Round အောက်က session ထဲ 3D (1–3 လုံး)။ `rounds.number_limit_3d`
  နံပါတ်ရေ ကျော်လျှင် `NUMBER_LIMIT_3D` ဖြင့် ပယ်; မကျော်လျှင် `assertWithinLimits(..., '3d')`
  (block mode တွင် exception)။
- `processForDraw()` - လစဉ် independent `three_digit_draws` အတွက်။ Draw + handler + agent
  lock ယူ၊ business ownership စစ်၊ `draw.status = open` ဖြင့် `open_time … result_time`
  အတွင်းသာ ယူသည်။

## 3. Rule codes ([NumberRuleEngine](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/NumberRuleEngine.php))

| Code | အဓိပ္ပာယ် |
| --- | --- |
| `A` | pairs 00-99 အပြည့် (fixed set) |
| `B`, `F` | argument ပေါ်မူတည်သော sequence/set |
| `P` | parity expansion (`++`, `--`, `+-`, `-+`) |
| `R` | reverse (နံပါတ်ပြောင်းပြန်) |
| `W`, `N`, `X` | Admin configurable number set (`code_rules.rule_config` JSON, admin-scoped) |
| custom list | Admin ဖန်တီးသော number list rule (`is_system = false`) |

`applyExclusions()` သည် `[...]` bracket ထဲမှ ဖယ်ရှားမည့်နံပါတ်/code များကို ကိုင်တွယ်၊
`normalizeNumbers()` သည် leading zero ကို နောက်ဆုံးရွှေ့သည့် ပုံမှန်ပြင်ဆင်မှု ပါဝင်သည်။

## 4. Hot numbers နှင့် number limits

`hot_numbers` (2D) နှင့် `three_digit_hot_numbers`/`three_digit_draw_hot_numbers` (3D) သည်
**မယူတော့သော နံပါတ်များ**; `rounds.number_limits` (JSON) သည် အတိအကျ တားမြစ်နံပါတ်များ။
နှစ်ခုလုံး sale detail ကို `rejected` သိမ်းပြီး ပေါင်းလဒ်ထဲ မထည့် - ထို့ကြောင့် report
တွင် "ဘယ်နံပါတ် ဘယ်နှခွင့် ပယ်ခံရလဲ" ပြန်စစ်နိုင်သည်။

## 5. Settlement ([RoundSettlementCalculator](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/RoundSettlementCalculator.php))

Per Agent တွက်နည်း (2D နှင့် 3D ခွဲတွက်) -

```
stake        = SUM(accepted, not excluded amount)          [Agent × number အလိုက် group]
winning_stake= stake on round.result_2d (သို့ result_3d)
winnings     = winning_stake × payout_{2d|3d}_multiplier   (AdminBusinessSetting; မထည့်=0)
commission   = stake × commission_{2d|3d}_percent / 100    (Agent row)
business_net = stake − winnings − commission
```

`calculate(round, ?operatorId)` သည် operator filter ထည့်နိုင်သဖြင့် operator တစ်ဦးချင်း
settlement ကွဲပြားစွာ ကြည့်နိုင်သည်။ Output ၏ `payout_2d_configured` /
`payout_3d_configured` သည် multiplier မထည့်ရသေးကြောင်း UI ပြရန် ဖြစ်သည် (ပေးချေမှု 0 ဖြစ်နေမည်)။

လစဉ် 3D Draw အတွက် [ThreeDigitDrawSettlementCalculator](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/ThreeDigitDrawSettlementCalculator.php)
သည် `payout_3d_multiplier` နှင့် `three_digit_draw_agent_settings` ကို သုံးပြီး အတူတူပင် တွက်သည်။

## 6. Void နှင့် Restore ([SaleVoidService](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/SaleVoidService.php))

သိမ်းပြီး sale ကို **နေရာတွင် ပြင်မရေးပါ**။ Void သည် detail အားလုံးကို `status = voided`
ပြောင်းပြီး `pre_void_status` နှင့် `pre_void_detail_statuses` (JSON snapshot) ကို သိမ်းသည်။
Total/ledger/settlement/reports အားလုံးသည် `status = accepted` ကို only filter လုပ်သဖြင့်
void လုပ်သည်နှင့် အလိုအလျောက် ကွာကျသွားသည်။ `restore()` သည် snapshot ကို ပြန်ပေးသည်။
Void တိုင်း `voided_at`, `voided_by`, `void_reason` + activity log ကျန်သည်။

## 7. Offline queue နှင့် sync ([OfflineSaleSyncService](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/OfflineSaleSyncService.php))

တစ်ခုတည်းသော intake လမ်းကြောင်း - browser offline tool (`source = web`) နှင့် Agent
mobile upload (`source = phone`, batch ≤ 100)။

- Duplicate (`client_uuid`) သည် existing review/sale ကို ပြန်ပေး (conflict message ဖြင့်)။
- Round/session ပိတ်နေလျှင်၊ Hot/Number-limit conflict ရှိလျှင်၊ amount limit block
  သို့ parse error ဖြစ်လျှင် - **sale ကို မသိမ်းဘဲ** `offline_sync_reviews` (status `pending`)
  ဖန်တီးပြီး `conflict_reason` ထဲ အကြောင်းရင်းရေး။
- Admin က `admin.offline-reviews.pending` မှ `approve()` (exclusion များထိန်း၍ sale ဖန်တီး)
  သို့မဟုတ် `reject()` လုပ်သည်။ Pending review ဆိုသည်မှာ စာရင်းထဲ **မရောက်သေး** ဟု အဓိပ္ပာယ်။

## 8. Reports

- [PeriodSummaryReport](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/PeriodSummaryReport.php) -
  `daily` / `weekly` (Monday start) / `monthly` / `yearly` range ကို `Asia/Yangon` ဖြင့်
  တွက်၊ daily rows ပါ output ပြန်။ ValidationException ဖြင့် ရက်စွဲမမှန်ကို ငြင်း။
- [ThreeDigitDrawPeriodReport](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/ThreeDigitDrawPeriodReport.php) -
  လစဉ် Draw version။
- Export (`/reports/summary/export`) သည် throttle 30/min နှင့် operator/admin နှစ်မျိုးလုံး
  သုံးနိုင် (data scope ကို `PeriodReportController.php:163` တွင် ရွေး)။

## 9. Result ထိန်းသိမ်းမှု

`round_results` (2D/3D result) ပြောင်းတိုင်း `round_result_changes` ထဲ field/old/new +
`changed_by` သိမ်းသည်။ Draw များအတွက် `three_digit_draw_results` + `_changes` အတူတူ။
Result မထည့်ရသေးသည့် Round တွင် winning_stake = 0 ဖြင့် settlement ပြသည် (အဖြေမရသေးဟု နားလည်ရ)။

## 10. Business စနစ်ထည့်သွင်းခြင်း ([InitializeAdminBusiness](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/InitializeAdminBusiness.php))

Admin/Owner account ပထမဆုံးသုံးချိန် - `admin_business_settings` row, `round_schedules`
1-3 (limit များ `null`), global `W/N/X` code rules ကို admin-scoped system rules အဖြစ်
ကူးယူ။ Data backup/import (`thai2d3d:export-business`, `BusinessDataImport`) သည်
SQLite ↔ MySQL လက်လွှဲရန် engine-independent JSON bundle (`WYA_BUSINESS_EXPORT_V1`) ကို
သုံးပြီး secret fields (`mobile_login_code`, `mobile_password`) များကို blank ပြန်လုပ်သည်။

## 11. ပြင်ဆင်သူများအတွက် စည်းမျဉ်းများ

1. Sale တွက်ချက်မှု အားလုံးသည် **accepted + not excluded** rule ကို လိုက်နာရမည်။
   ပုံစံအသစ်ထပ်ရေးလျှင် `is_excluded` ကို မေ့မထားပါ။
2. Sale ကို in-place edit မလုပ်ရ - void/restore သာ။
3. New number limit type ထပ်ထည့်လျှင် reject reason string ကို `OfflineSaleSyncService`
   (`whereIn('reject_reason', ...)`) ထဲပါ ထပ်ဖြည့်ပါ၊ မဟုတ်လျှင် offline sync ထဲ review
   မဖြစ်တော့ပါ။
4. Timezone ကို `Asia/Yangon` မှတစ်ဆင့်သာ သုံးပါ (scheduler၊ reports၊ ledger)။
