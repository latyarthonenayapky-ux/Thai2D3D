# Agent Cash Ledger (ရုံးနှင့် Agent အကြား ပိုက်ဆံစာရင်း)

Ledger သည် ရုံးနှင့် Agent တစ်ဦးချင်းစီအကြား **တကယ် လက်ပြောင်းဖြစ်ခဲ့သော ငွေ**
များကို ရေးသားသည့် စာအုပ်ဖြစ်သည်။ Settlement က Round တစ်ခုချင်းစီအတွက် Agent အကြွေး/
အကျိုးအမြတ်ကို တွက်ချက်ပေးသော်လည်း "ဒီ Agent ရဲ့ ပိုက်ဆံ ဘယ်လောက် ကျွန်တော်တို့ လက်ထဲမှာ
ရှိနေလဲ" ဆိုသည့် မေးခွန်းကို ဖြေရန် notebook ဖြင့်သာ ဖြေနိုင်ခဲ့သည် - ထိုလိုအပ်ချက်မှ ပေါ်လာသည်
([database/migrations/2026_10_14_000003_create_agent_ledger_entries_table.php](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/database/migrations/2026_10_14_000003_create_agent_ledger_entries_table.php))။

အဓိက ကုဒ် - [app/Services/AgentLedger.php](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/AgentLedger.php),
[app/Models/AgentLedgerEntry.php](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Models/AgentLedgerEntry.php),
[app/Http/Controllers/Admin/AgentLedgerController.php](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Http/Controllers/Admin/AgentLedgerController.php)

## 4 မျိုးသော entry အမျိုးအစား

| Type | ဦးတည်ချက် | အဓိပ္ပာယ် |
| --- | --- | --- |
| `deposit` | ရုံးထဲ ဝင် | Agent က ရုံးကို ငွေအပ်သည် (collateral / ရှေ့ငွေ) |
| `credit` | ရုံးထဲက ထွက် | ရုံးက Agent ကို float/ငွေချေးပေးသည် |
| `repayment` | ရုံးထဲ ဝင် | Agent က ချေးထားသည်ကို ပြန်ဆပ်သည် |
| `withdrawal` | ရုံးထဲက ထွက် | Agent က သူ့ငွေကို ပြန်ထုတ်သည် |

`AgentLedgerEntry::MONEY_IN = ['deposit', 'repayment']` - ကျန်နှစ်မျိုးသည် ငွေထွက်။
Amount ကို **အမြဲ positive** သိမ်းသည်; လက္ခဏာ (sign) ကို type မှ တွက်ထုတ်သည်။
Balance အပေါင်း/အနှုတ် အဓိပ္ပာယ် -

- **positive** = ရုံးသည် Agent ၏ ငွေကို ကိုင်ထားသည်။
- **negative** = Agent သည် ပြန်မဆပ်ဘဲ ထုတ်ယူထားသည် (ရုံးထံ ချေးနေသည်)။

## Balance ကို ဘယ်တော့မှ မသိမ်းပါ

`agent_ledger_entries` row များကိုပေါင်းပြီးမှ balance ရသည်။ ထို့ကြောင့် row တစ်ခု
ဖျက်လိုက်လျှင် (မှားရေးထားသဖြင့်) stale figure ကျန်ရစ် chance မရှိ - အမှားပြင်လျှင်
balance ချက်ချင်း မှန်သွားသည်။ Running balance သည် cash book ရေးသည့် အစဉ်
(`occurs_on`, ပြီးလျှင် `id`) အတိုင်း စုဆည်းတွက်ချက်သည်။

## Admin screen လုပ်ဆောင်ချက်များ

| Route name | လုပ်ဆောင်ချက် |
| --- | --- |
| `admin.ledger.index` | Agent စာရင်း + ခေတ္တ balance များ (per page 25)၊ search (`q` - agent_name / agent_code / phone)၊ portfolio အနှစ်ချုပ် |
| `admin.ledger.show` | Agent တစ်ဦးချင်း statement - opening balance, rows, running balance, in/out/net/closing; `from`/`to` (Y-m-d) filter |
| `admin.ledger.store` | Entry အသစ်ထည့် (`type`, `amount > 0` max 999999999.99, `occurs_on`, `note` optional 255) |
| `admin.ledger.destroy` | Entry ဖျက် |

Access rule - **Admin (`role = admin` / `temp_admin`) သာ**。Agent သည် ဤ area သို့
ဝင်ခွင့်မရှိ။ `owner` သည် admin area shell ကို ဝင်နိုင်သော်လည်း ledger တွင် **403** ရသည်
([AgentLedgerController.php:24](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Http/Controllers/Admin/AgentLedgerController.php#L24))။
အခြား Agent များ၏ ledger ကို 404 ဖြင့် ကာကွယ်ထားသည် (business isolation)။
Date filter မပါလျှင် opening = 0 (စာအုပ်အစမှစတင်)။ `today` ကို `Asia/Yangon` ဖြင့် ပေးသည်။

## Audit trail

Entry တစ်ခုထည့်တိုင်း / ဖျက်တိုင်း `ActivityLog` ထဲ သီးသန့်ရေးသည် -

- `agent_ledger.recorded` → `agent_id`, `type`, `amount`, `occurs_on`
- `agent_ledger.deleted` → `agent_id`, `entry_id`, `type`, `amount`

Route-level `RecordAdminActivity` middleware သည် request body ကို မမြင်ရသောကြောင့်
ပမာဏနှင့် အမျိုးအစားကို controller ထဲ သီးသန့် ရေးထားခြင်း ဖြစ်သည်။ ဖျက်လိုက်သော entry ၏
အချက်အလက်သည် activity log ထဲ ကျန်ရစ်သည်။

## SQL အသုံးပြုပုံ (maintainer notes)

- `balances()` - page တစ်ခုလုံး၏ Agent များအတွက် aggregate တစ် query တည်းဖြင့် ရယူသည်။
- `portfolio()` - လုပ်ငန်းတစ်ခုလုံး၏ cash held figure။
- Signed SQL case (`CASE WHEN type IN (...) THEN amount ELSE -amount END`) သည်
  `AgentLedgerEntry::MONEY_IN` class constant မှ တည်ဆောက်ပြီး **request data မှ လုံးဝမတည်ဆောက်**-
  injection ကာကွယ်ချက်။ Type အသစ်ထည့်လျှင် `TYPES` နှင့် `MONEY_IN` နှစ်ခုလုံး ပြင်ရမည်။
- `recorded_by` FK သည် `restrictOnDelete` - user တစ်ဦးကို ဖျက်ရန် မတိုင်မီ သူ့ ledger
  entries များကို အရင် care လုပ်ရမည်။

## စမ်းသပ်မှု

```sh
php artisan test --filter=AgentCashLedgerTest
```

[tests/Feature/AgentCashLedgerTest.php](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/tests/Feature/AgentCashLedgerTest.php)
တွင် sign behavior, opening/running/closing totals, date filter, business isolation,
403 (non-admin) နှင့် activity log entries အပါအဝင် စမ်းသပ်ထားသည်။

## ဆက်စပ်ဖတ်ရန်

- [Roles and Access](ROLES_AND_ACCESS_MM.md) - owner vs admin 403 ကွာခြားချက်
- [Data Model](DATA_MODEL_MM.md) - ဇယားများနှင့် columns
- [Business Logic](BUSINESS_LOGIC_MM.md) - settlement နှင့် ledger ၏ ခြားနားချက်
