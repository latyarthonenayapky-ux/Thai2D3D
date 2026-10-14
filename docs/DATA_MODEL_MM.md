# Data Model (database ဇယား ကိုးကားချက်)

Desktop app သည် **SQLite**၊ VPS သည် **MySQL/MariaDB** သုံးသည်။ Schema အားလုံးသည်
`database/migrations/` မှ လာသည် - ဤ document သည် ရည်ညွှန်းချက်သာ ဖြစ်ပြီး migration
များက အမှန်တကယ် authority ဖြစ်သည်။

## 1. Core entity များ

```
User (owner | admin | temp_admin | operator)
  └── Agent (admin_id)                     ── AgentRoundSetting ── Round
        ├── AgentSession (round, operator)      ├── RoundSchedule (template)
        │     ├── SaleInput → SaleDetail        ├── RoundResult → RoundResultChange
        │     └── ThreeDigitSaleInput → Detail  ├── HotNumber / ThreeDigitHotNumber
        ├── AgentAccessToken (mobile)           ├── OperatorRoundSetting
        └── AgentLedgerEntry                    └── CodeRule (W/N/X + custom)
AdminBusinessSetting (payout multipliers)   ThreeDigitDraw ── Result / HotNumber / AgentSetting
MobileAppPackage (per admin APK)            ActivityLog, UserPreference, OfflineSyncReview
```

## 2. Table အလိုက် အဓိက columns

### users
| Column | အဓိပ္ပာယ် |
| --- | --- |
| `role` (string 20, default `operator`) | `owner`, `admin`, `temp_admin`, `operator` |
| `status` (bool) | false = account ပိတ် (login မရ) |
| `expires_at` (nullable timestamp) | temp account ၏ သက်တမ်း; cron ပိတ်သည် |
| `admin_id` (nullable FK) | operator/temp account သည် မည်သူ့ လုပ်ငန်းအောက်တွင် ရှိလဲ |

### agents
`agent_code` (unique), `agent_name`, `admin_id` (business owner), `phone`,
`status enum(active, inactive)`, `commission_2d_percent` decimal(5,2),
`commission_3d_percent`, `mobile_login_code` (unique, nullable), `mobile_password`
(bcrypt hash - **plaintext မသိမ်း**), timestamps။
`Agent::hasMobileAccess()` သည် login code + password နှစ်ခုလုံးရှိမှ true။

### rounds (2D Round)
| Column | အဓိပ္ပာယ် |
| --- | --- |
| `round_date`, `round_no` | unique `(admin_id, round_date, round_no)` |
| `start_time` (nullable), `close_time` | scheduler သုံးသည်; close_time မရှိလျှင် Round မဖွင့် |
| `status enum(open, closed)`, `opened_at` | ဘယ်အချိန် ရောင်းခွင့်ရှိ |
| `manual_reopen`, `manually_closed` | Admin လက်လုပ် ဖွင့်/ပိတ် flags - scheduler က လေးစားသည် |
| `number_limit`, `number_limit_3d` | နံပါတ်ရေ caps |
| `number_limits` (JSON) | **တားမြစ်ထားသော 2D နံပါတ်များ** (`rejected / NUMBER_LIMIT`) |
| `amount_limit_all`, `amount_limit_all_mode` | 2D - Round တစ်ခုတည်းသော cap (00-99 အားလုံး, warn-only) |
| `amount_limit_all_3d`, `amount_limit_all_mode` | 3D - Round-wide cap + mode |

### agent_sessions
`round_id`, `agent_id`, `operator_id`, `session_code` (unique, ပုံစံ `YYMMDD-CODE-NN`),
`status enum(open, closed)`, `opened_at`, `closed_at`။ Agent တစ်ဦးလျှင် Round တစ်ခုတွင်
**session တစ်ခုတည်း** open ဖြစ်နိုင်သည် (`openSession()` တွင် `lockForUpdate` ဖြင့် သိမ်း)။
Operator က `operator.agent-sessions.claim` ဖြင့် claim လုပ်ပြီး AgentRoundSetting
`operator_id` ကို အသုံးပြုသည်။

### sale_inputs (2D) / three_digit_sale_inputs (3D)
| Column | အဓိပ္ပာယ် |
| --- | --- |
| `agent_session_id`, `operator_id` | မည်သူ ဘယ် session ထဲ ရိုက်သလဲ |
| `client_uuid` | **idempotency key** - unique `(operator_id, client_uuid)`; duplicate upload တွင် sale မထပ်ဖြစ် |
| `source` (string 12, default `web`) | `web` = Sale Entry/browser offline tool, `phone` = Agent mobile app auto-upload |
| `original_input`, `normalized_input`, `input_type`, `code`, `number_argument` | Parser ၏ input audit trail |
| `number_count`, `amount`, `total_amount` | တစ်ခွင်လျှင် နံပါတ်ရေ / တစ်နံပါတ်ပိုက်ဆံ / စုစုပေါင်း |
| `status enum(accepted, rejected, pending, voided)`, `reject_reason` | ရလဒ်အခြေအနေနှင့် ပယ်ခံရခြင်း အကြောင်းရင်း |
| `voided_at`, `voided_by`, `void_reason`, `pre_void_status`, `pre_void_detail_statuses` (JSON) | Void/restore snapshot |

### sale_details (2D) / three_digit_sale_details (3D)
`sale_input_id`, `number` (2 လုံး/3 လုံး), `amount`, `is_excluded` (bool),
`status enum(accepted, rejected, excluded, voided)`, `reject_reason`
(`HOT_NUMBER`, `NUMBER_LIMIT`, `HOT_NUMBER_3D`, `NUMBER_LIMIT_3D`, `ALL_NUMBERS_REJECTED`)။
**Settlement/reports သည် `status = accepted AND is_excluded = false` ကိုသာ ရေတွက်သည်**
([RoundSettlementCalculator.php:36](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/RoundSettlementCalculator.php#L36))။

### agent_round_settings (Agent × Round)
unique `(agent_id, round_id)`; `operator_id` (claim), `number_limit`,
`amount_limit` (2D - **enforcement မရှိတော့**, data သက်သက်), `amount_limit_3d`,
`amount_limit_mode`။ ၂D/3D limit များအကြောင်း
[Amount Limit document](AMOUNT_LIMITS_MM.md)။

### operator_round_settings (Operator × Round)
unique `(round_id, operator_id)`; `amount_limit`, `amount_limit_3d`, `amount_limit_mode`
- operator တစ်ဦး၏ စုစုပေါင်း 3D ကန့်သတ်ချက်။

### round_schedules (Admin ၏ template)
unique `(admin_id, round_no)`; `start_time` (nullable), `close_time` (required to create
Rounds), `number_limit`, `number_limit_3d`။ `InitializeAdminBusiness` သည် ပထမအကြိမ်
round_no 1-3 စုစည်းပေးသည်။

### round_results / round_result_changes
`round_id` + `admin_id` (unique), `result_2d` (2), `result_3d` (3), `updated_by`;
changes table သည် field/old_value/new_value ဖြင့် ဘယ်သူပြောင်းလဲမှ audit ထားသည်။

### three_digit_draws (လစဉ် 3D, Round မဟုတ်)
unique `(admin_id, draw_date)`; `open_time` (default `00:00:00`), `result_time`
(default `15:30:00`), `status enum(open, closed)`, `opened_at`, `closed_at`,
`number_limit`။ ထိန်းသိမ်းသူ - `SyncThreeDigitDraws` (လ၏ 1 ရက်နှင့် 16 ရက်)။
ဆက်စပ် - `three_digit_draw_agent_settings` (Agent × Draw, `amount_limit`, `handler_id`),
`three_digit_draw_results` (+ `_changes`), `three_digit_draw_hot_numbers`။

### hot_numbers / three_digit_hot_numbers
unique `(round_id, number)` - ထို Round မှာ ငွေမယူတော့သော နံပါတ်များ (block)။

### code_rules (2D rule code များ)
`code` (20), `admin_id` (null = global system), unique `(admin_id, code)`; `name`,
`rule_type`, `rule_config` (JSON), `allow_bracket`, `is_system`, `is_active`,
`sort_order`။ `W`, `N`, `X` သည် admin-configurable number_set များဖြစ်ပြီး
`is_system = true` ဖြင့် စတင်ဖန်တီး၊ Admin က ပြင်နိုင်
([NumberRuleEngine.php:41](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/NumberRuleEngine.php#L41))။

### agent_access_tokens (mobile sign-in)
`agent_id`, `token_hash` (SHA-256, unique - **plaintext မသိမ်း**), `device_name`,
`last_used_at` (တစ်မိနစ်တစ်ခါသာ update), `expires_at`, `revoked_at`။

### agent_ledger_entries (cash book)
`admin_id`, `agent_id`, `type enum(deposit, credit, repayment, withdrawal)`,
`amount` decimal(15,2) positive, `occurs_on` (date), `note`, `recorded_by`
(`restrictOnDelete`)။ Indexes - `(agent_id, occurs_on)`, `(admin_id, occurs_on)`။
အသေးစိတ် - [Agent Cash Ledger](AGENT_CASH_LEDGER_MM.md)။

### admin_business_settings
unique `admin_id`; `payout_2d_multiplier`, `payout_3d_multiplier` (nullable decimal 14,2)။
မထည့်ထားလျှင် settlement ရှိ winnings = 0 ဖြင့် `payout_.._configured = false` ပြသည်။

### offline_sync_reviews
`client_uuid`, `sale_type enum(2d, 3d)`, `operator_id`, `agent_session_id`, `source`,
`status enum(pending, approved, rejected)` - စာရင်းထဲ မရောက်ခင် Admin စစ်ဆေးအတည်ပြုရသည့်
queue။

### mobile_app_packages
unique `admin_id`; `stored_path`, `file_name`, `version`, `size_bytes`, `published_at` -
Agent များ download လုပ်ရန် Admin ထုတ်ဝေထားသော APK (တစ်လုပ်ငန်း = တစ်package)။

### activity_logs
`user_id` (nullable, nullOnDelete), `action`, `subject_type` + `subject_id` (polymorphic),
`meta` JSON, `ip` (45), `created_at`။ Index `(action, created_at)`။

### user_preferences
unique `(user_id, key)`; `value` (255)။ `locale`, display, sale-entry, notifications,
security အကြောင်း [Localization](LOCALIZATION_MM.md)။

### licences / licence_state (Desktop only)
`licences` - `licence_id` (64, index), `email`, `plan` (64), `max_admins`, `max_agents`,
`issued_at`, `expires_at`, `licence_string` (text - vendor ၏ လက်မှတ်ရေးထိုး string အပြည့်),
`signature_ok`, `installed_at`, `revoked_reason`။ `licence_state` - row တစ်ခုတည်း
`active_licence_id` (FK → `licences.id`, nullOnDelete) နှင့် `last_seen_at`
(naive clock ၂၄ နာရီထက် ပိုနောက်ကျွေ့လျှင် `clock_rollback`)။ အကြောင်းအရာ
[Licence](LICENCE_MM.md)။ VPS install တွင် ဇယားနှစ်ခုလုံး အသုံးမဝင်ပါ။

### Laravel သုံးစွဲသော framework tables
`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`,
`password_reset_tokens` - app logic က မထိ။

## 3. ပုံမှန် သိမ်းဆည်းရေး (convention)

- ပိုက်ဆံ - `decimal(12,2)`/`decimal(15,2)`, float မသုံး။ တွက်ချက်ရာတွင်
  `round($x, 2)` သုံးသည်။
- FK - `admin_id` ရှိသမျှ ဇယားသည် business isolation ၏ အဓိက သော့ချက်။
- Timezone - scheduler/reports `Asia/Yangon` ဖြင့် သုံး (`now('Asia/Yangon')`)။
- Enum ကျယ်လုပ်လိုက်လျှင် migration ၏ `down()` တွင် data migration ပါပေးရ
  (`2026_10_12_000000_add_sale_void_support.php` ကို ကြည့်)။

## 4. Schema ပြောင်းတဲ့အခါ

1. `php artisan make:migration` - `down()` မှန်/ကြမ်းတမ်းမှု စစ်ပါ။
2. `BusinessDataSchema` (`app/Support/BusinessDataSchema.php`) ထဲ table စာရင်း၊
   scope/match/remap/blank ကို update လုပ်ပါ - export/import သည် FK-safe order ပေါ်
   မူတည်သည်။
3. `php artisan migrate --force` (VPS) နှင့် `php artisan test`။
