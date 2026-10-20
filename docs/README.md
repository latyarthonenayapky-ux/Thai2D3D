# Documentation index

ဤ folder ထဲရှိ စာရွက်စာတမ်းများကို **အသုံးပြုသူ / install လုပ်သူ / developer / vendor**
အလိုက် ခွဲထားသည်။ မေးခွန်းတစ်ခုချင်းစီအတွက် ဘယ်ဖိုင်ကို ဖတ်ရမည် အောက်ဇယားတွင် ရှိသည်။

## ဘာလိုက်သလဲ ရှာပုံ

| မေးခွန်း | ဖိုင် |
| --- | --- |
| App ကို ဘယ်လိုသုံးမလဲ (login, sale entry, result, settlement) | [USER_MANUAL_MM.md](USER_MANUAL_MM.md) |
| VPS ပေါ် တပ်ဆင်/ပြွင့်နည်း (nginx, MySQL, HTTPS, cron, DNS) | [VPS_SETUP_MM.md](VPS_SETUP_MM.md) |
| Go-live မလုပ်မီ စစ်ရန်အချက်များ | [production-deployment.md](production-deployment.md) |
| Agent မိုဘိုင်း app + `/api/v1` auto-upload API | [mobile-agent-sales.md](mobile-agent-sales.md) |
| Round အလိုအလျောက် ဖွင့်/ပိတ်ခြင်း (scheduler) | [round-scheduling.md](round-scheduling.md) |
| Desktop licence (လက်မှတ်ရေးထိုး သုံးခွင့်) နှင့် vendor signer | [LICENCE_MM.md](LICENCE_MM.md) |
| Amount Limit / Number Limit စည်းမျဉ်း (2D, 3D) | [AMOUNT_LIMITS_MM.md](AMOUNT_LIMITS_MM.md) |
| Agent cash ledger (deposit/credit/repayment/withdrawal) | [AGENT_CASH_LEDGER_MM.md](AGENT_CASH_LEDGER_MM.md) |
| Role များ၊ middleware၊ ဘယ်သူ ဘာဝင်နိုင်သလဲ | [ROLES_AND_ACCESS_MM.md](ROLES_AND_ACCESS_MM.md) |
| Database ဇယားများနှင့် relation များ | [DATA_MODEL_MM.md](DATA_MODEL_MM.md) |
| Sale processing / settlement / reports / void ၏ သဘောတရား | [BUSINESS_LOGIC_MM.md](BUSINESS_LOGIC_MM.md) |
| Locale (English/မြန်မာ) နှင့် language key audit | [LOCALIZATION_MM.md](LOCALIZATION_MM.md) |
| Local development, tests, CI | [DEVELOPMENT_MM.md](DEVELOPMENT_MM.md) |
| Release folder များ၊ sync/smoke scripts, APK နှင့် desktop build | [RELEASE_BUILD_MM.md](RELEASE_BUILD_MM.md) |
| ပြဿနာ ဖြစ်လျှင် (413, HTTPS ပျောက်, SmartScreen, ...) | [TROUBLESHOOTING_MM.md](TROUBLESHOOTING_MM.md) |
| Help drawer assistant (web သက်သက်၊ docs မှဖြေသည်) | [HELP_ASSISTANT_MM.md](HELP_ASSISTANT_MM.md) |
| အရင် launch သုံးသပ်ချက် (2026-10-10) | [LAUNCH_REVIEW_MM_2026-10-10.md](LAUNCH_REVIEW_MM_2026-10-10.md) |

## ထပ်မံဖတ်ရန် (folder အလိုက်)

- [../README.md](../README.md) — Project အနှစ်ချုပ်၊ Desktop package များ၊ VPS လက်ရှိအခြေအနေ
- [../PRODUCT.md](../PRODUCT.md) — Product ရည်ရွယ်ချက်၊ brand commitments
- [../desktop/README.md](../desktop/README.md) — Electron desktop app build နှင့် local data
- [../mobile/README.md](../mobile/README.md) — Flutter Agent app နှင့် APK build

## Document များအတွက် စံနှုန်း

- သုံးစွဲသူ မေးခွန်းကို ဦးစားပေးဖြေသည်။ ကိုက်ညီမှု (code နှင့် document) အရေးကြီးသည် -
  ပြောင်းလဲမှုတိုင်း document ကိုပါ ပြင်ဆင်ပါ။
- အရေအတွက်/တန်ဖိုးများ (port, limit, migration name) ကို ကိုက်ညီစွာ ဖော်ပြပါ -
  code ထဲမှ တိုက်ရိုက်စစ်ပြီးမှ ရေးပါ။
- Credential (password, token, PAT) တန်ဖိုးများကို ဘယ်တော့မှ မရေးပါ။ နာမည်သာ ပေးပါ။
