# Help drawer assistant (အသုံးပြုမှု လမ်းညွှန် AI အကူအညီ)

အက်ပ်အောက်ညာထောင့်မှ (?) ခလုတ်ကို နှိပ်ပါက မေးခွန်းမေးနိုင်သည့် drawer တစ်ခု ပေါ်လာသည်။
အဖြေများသည် ဤ folder ထဲရှိ လမ်းညွှန်စာတမ်းများ (`docs/*.md`) မှ တိုက်ရိုက်ရသည်။
**Web (VPS) install တွင်သာ ပေါ်သည်** - desktop copy များသည် offline ဖြစ်သည့်အတွက် ဤ drawer ကို
လုံးဝ မပြဘဲ `/help/assistant` route ကိုလည်း ပိတ်ထားသည် (licence သက်တမ်းကုန်နေလျှင် 423၊
ပုံမှန် desktop တွင် 404)။

## ဘာလုပ်ပေးနိုင်သလဲ

- လုပ်နည်းမေးခြင်း - "agent password မေ့နေသည် ဘယ်လိုပြန်ရမလဲ"၊ "round close settlement ဘယ်လိုလုပ်မလဲ"
  စသဖြင့် မြန်မာ/အင်္ဂလိပ် နှစ်ဘာသာလုံး နားလည်သည်။
- စာမျက်နှာပေါ်က အမှားကို ထောက်ပြခြင်း - အနီရောင် error box တစ်ခု ပေါ်နေလျှင် drawer ထဲရှိ
  "Explain this error" ခလုတ်ဖြင့် ထိုစာသားကို တိုက်ရိုက်မေးနိုင်သည်။
- ကိုက်ညီသော လမ်းညွှန်အပိုင်းခေါင်းစဉ်များကို အောက်တွင် ပြသည် (references)။ မေးခွန်းနှင့် မကိုက်လျှင်
  "လမ်းညွှန်ထဲ မတွေ့ရပါ" ဟု ရေးပြီး ခန့်မှန်းအဖြေ မပေးပါ။

## Model (optional)

API key ထည့်ထားပါက လမ်းညွှန်မှ အပိုင်းများကို ပေးပြီး အတိုချုပ်အဖြေ ပြင်သင်ထုတ်စေသည်။
Key မထည့်ထားလျှင် (သို့) model call မအောင်မြင်လျှင် (timeout, 4xx/5xx) drawer သည်
လမ်းညွှန် စာပိုင်းတစ်ပိုင်းကို တိုက်ရိုက်ပြသည်။ ဆိုလိုသည်မှာ key မရှိလည်း အလုပ်လုပ်သည်။

`/var/www/thai2d3d/.env` ထဲတွင် ထည့်ပါ (တန်ဖိုးများကို repo တွင် ဘယ်တော့မှ မသိမ်းပါ) -

```
HELP_LLM_PROVIDER=anthropic
HELP_LLM_API_KEY=<vendor ကိုယ်တိုင်ရသော key>
HELP_LLM_MODEL=<model id>
HELP_LLM_DAILY_LIMIT=40
```

- `HELP_LLM_PROVIDER` - `anthropic` (default) သို့မဟုတ် `openai`။
- `HELP_LLM_MODEL` - အကြမ်းမဖော်ထားပါ။ Provider account ထဲမှ model id ကို ကိုယ်တိုင်ရေးထည့်ပါ။
  Key နှင့် model နှစ်ခုလုံးရှိမှသာ model layer သုံးသည်။
- `HELP_LLM_DAILY_LIMIT` - အကောင့်တစ်ခုချင်း ရက်ချင်း model call အရေအတွက် ကန့်သတ်ချက်။
  `0` ဆိုလျှင် မကန့်သတ်။ ကျော်လျှင် guide mode သို့ ပြန်သွားသည်။
- `HELP_LLM_BASE_URL`, `HELP_LLM_TIMEOUT` (default 8 စက္ကန့်), `HELP_LLM_MAX_TOKENS` (default 700),
  `HELP_QUESTION_LIMIT` (default 400 စာလုံး), `HELP_CONTEXT_SECTIONS` (default 4) တို့လည်း ရှိသည်။
- ပြောင်းလဲပြီးနောက် `php artisan config:clear` (သို့) `config:cache` ပြန် run ပါ။

## ကန့်သတ်ချက်နှင့် လုံခြုံရေး

- Route မှာ `POST /help/assistant`၊ login ဝင်ထားသူသာ သုံးနိုင်၊ throttle = တစ်မိနစ်လျှင် 20 ခေါက်
  (`help-ask.` limiter prefix)။
- API key ကို browser သို့ ဘယ်တော့မှ မပို့ပါ - server တွင်သာ သုံးသည်။ Log ထဲ response status တစ်ခုတည်း၊
  key သို့မဟုတ် အသုံးပြုသူစာသား မထည့်ပါ။
- Model ထံပို့သည်မှာ မေးခွန်းနှင့် လမ်းညွှန်စာပိုင်းများသာ - ရောင်းစာရင်း၊ ကျပ်ငွေ၊ agent data တွေ မပါ။
- Drawer ထဲ အဖြေကို `textContent` ဖြင့်သာ ပြသည် (HTML အဖြစ် မရေးတော့ဘူး)၊ XSS မဖြစ်အောင်။
- System prompt တွင် "ပေးထားသော လမ်းညွှန်အတိုင်းသာ ဖြေပါ၊ path/ဈေးနှုန်း/ဂဏန်း အတုမဖန်ပါနှင့်၊
  လမ်းညွှန်ထဲ မပါလျှင် မတွေ့ရကြောင်း ပြန်ပြောပါ" ဟု ပိတ်ထားသည်။

## ဘယ်ဖိုင်တွေ ပါဝင်သလဲ

| အပိုင်း | ဖိုင် |
| --- | --- |
| Guide keyword index (မြန်မာ bigram + ASCII word) | [../app/Services/Assistant/DocIndex.php](../app/Services/Assistant/DocIndex.php) |
| အဖြေစုစည်းခြင်း၊ daily budget၊ prompt | [../app/Services/Assistant/HelpAssistant.php](../app/Services/Assistant/HelpAssistant.php) |
| Model call (anthropic/openai) + fallback | [../app/Services/Assistant/LlmClient.php](../app/Services/Assistant/LlmClient.php) |
| Route/controller | [../app/Http/Controllers/HelpAssistantController.php](../app/Http/Controllers/HelpAssistantController.php), [../routes/web.php](../routes/web.php) |
| Config (docs allowlist + model settings) | [../config/help.php](../config/help.php) |
| Drawer UI | [../resources/views/layouts/app.blade.php](../resources/views/layouts/app.blade.php) |
| Tests | [../tests/Feature/HelpAssistantTest.php](../tests/Feature/HelpAssistantTest.php) |

Index လုပ်ထားသော ဖိုင်များကို `config/help.php` တွင် စာရင်းပြုစုထားသည် (အသုံးပြုသူဆိုင်ရာ ဖိုင်များသာ -
`DEVELOPMENT_MM.md`, `RELEASE_BUILD_MM.md`, `VPS_SETUP_MM.md` စသည်တို့ မပါ)။ ထို့ကြောင့် **docs folder ကို
VPS bundle ထဲ မပျောက်စေရ** - `scripts/vps-bundle.sh` ၏ exclude စာရင်းတွင် `docs` မပါကြောင်း သေချာပါစေ။

### အဖြေရှာပုံ (ranking)

မြန်မာစာတွင် စကားလုံးခွဲခြမ်းစနစ် မရှိသဖြင့် ASCII word များနှင့် မြန်မာ syllable run + bigram များကို
သုံးသည်။ ရလဒ်များကို term တစ်ခုချင်း၏ ရှားပါးမှု (inverse document frequency) နှင့် အလျားအလိုက်
အစားထိုး တွက်ချက်ပြီး အခေါင်းစဉ် (heading) ထဲတွင် ပါလျှင် နှစ်ဆတိုးသည်။
စကားလုံးတစ်ခုကို အကြိမ်ရေနှင့် မတွက်ပါ - လျှောက်ဖြူများ (ဥပမာ "…နိုင်ပါသည်") ပါသော အပိုင်းကြီးများက
မေးခွန်းကို တကယ်ဖြေသည့် အပိုင်းထက် အမှားဖြင့် အမီရှင်နိုင်သောကြောင့် ဖြစ်သည်။
စကားလုံးနှစ်လုံးပါ မြန်မာ bigram (syllable တစ်ခု၏ အပိုင်းအစ) ကိုလည်း ၀.၄ ဖြင့် လျှော့တွက်သည်။
မေးခွန်းနှင့် လုံးဝ မကိုက်လျှင် အနီးစပ်ဆုံးကို မဆွဲပြဘဲ "လမ်းညွှန်ထဲ မတွေ့ရပါ" ဟုသာ ပြန်သည်။

## ပြောင်းလဲမှုတစ်ခုချင်းစီအတွက်

- UI string အသစ်ထည့်လျှင် `lang/en/app.php` နှင့် `lang/my/app.php` နှစ်ခုလုံးတွင် ထည့်ပြီး
  `desktop/runtime/php-win/php.exe scripts/audit-language-keys.php` run ပါ။
- Test: `desktop/runtime/php-win/php.exe artisan test --filter=HelpAssistantTest`။
