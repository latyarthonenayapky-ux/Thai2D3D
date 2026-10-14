# Localization (English / မြန်မာ)

App UI သည် နှစ်ဘာသာ ရှိသည် - `en` (default) နှင့် `my` (မြန်မာ)။
`config/app.php` တွင် `APP_LOCALE` (default `en`) နှင့် `APP_FALLBACK_LOCALE` (`en`)။

## ဘယ်လို အလုပ်လုပ်သလဲ

1. ဇယား `user_preferences` သည် unique `(user_id, key)` ဖြင့် `locale` key ကို သိမ်းသည်။
2. `ApplyUserPreferences` middleware သည် login ပြီးသုံးသူအတွက်
   `app()->setLocale($user->locale())` ခေါ်သည်
   ([app/Http/Middleware/ApplyUserPreferences.php:21](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Http/Middleware/ApplyUserPreferences.php#L21))။
3. Supported locales သည် `UserPreference::supportedLocales()` တွင်တည်း -
   `en => English`, `my => မြန်မာ` ([app/Models/UserPreference.php:34](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Models/UserPreference.php#L34))။
   ထိုစာရင်းမပါ locale ကို `isSupportedLocale()` ငြင်းပယ်သည်။
4. ပြောင်းနည်း - nav ထဲ language switcher ([resources/views/partials/nav-language.blade.php](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/resources/views/partials/nav-language.blade.php))
   သို့မဟုတ် Settings မျက်နှာ ([settings/index.blade.php:462](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/resources/views/admin/settings/index.blade.php#L462))။
   နှစ်ခုလုံး `POST /preferences/locale` (`preferences.locale`, throttle 60/min) ကို သုံးသည်။
5. Views/template များသည် `__('app.<key>')` ကို သုံးကြသည်။ Placeholder ပါ key များ
   (`__('app.ledger.recorded', [...)]`) လည်းရှိ။

## Key ဖိုင်များနှင့် parity rule

| ဖိုင် | လိုင်း |
| --- | --- |
| [lang/en/app.php](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/lang/en/app.php) | ~1000 |
| [lang/my/app.php](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/lang/my/app.php) | ~1000 |

**Rule: key structure နှစ်ဖိုင်လုံး တူညီရမည်** - `my` ထဲ missing မရှိရ၊ `en` ထဲမရှိသော
extra key လည်း မရှိရ။ စစ်ရန် (PHP runtime သုံး) -

```sh
php scripts/audit-language-keys.php
```

ထို script ၏ output -

- `keys en=N my=N` - flat key အရေအတွက် (nested array များကို dot-path ဖြင့် ချဲ့တွက်)
- `missing in my=` / `extra in my=` - parity error များ
- `identical without burmese=` - ယာယီ **မြန်မာမဘာသာပြန်ရသေး** key များ (Myanmar unicode
  `\x{1000}-\x{109F}` မပါသေး) - ရည်ရွယ်ချက်ရှိရှိ English ထားသင့်လျှင် ချန်ထားနိုင်
- `suspect mojibake=` - Latin-1/replacement character ပါသွားသော value (encoding ပျက်)။
  Burmese copy ထဲ ခွင့်ပြုထားသော punctuation - `· § – ’ “ ” × € ™`

`lang/en` ဖိုင်ကို byte-for-byte မပြောင်းဘဲ ထားလေ့ရှိသည် - အရင် test များ
(`NavigationTest`, `BurmeseLocaleUiTest`) စာသားအဟောင်းများဖြင့် assert လုပ်ထားသောကြောင့်
ဖြစ်သည်။ English စာသားပြောင်းရန် လိုလျှင် သက်ဆိုင်ရာ test များကိုပါ ပြင်ရမည်။

## ဘယ်နေရာတွေ English-only ကျန်သေးလဲ

- **In-app manual** (`/manual`, `resources/views/manual/index.blade.php`) - Burmese
  translate မလုပ်ရသေး။ Burmese လက်စွဲကို [USER_MANUAL_MM.md](USER_MANUAL_MM.md) ဖိုင်ဖြင့်
  ဖတ်ကြရသည်။
- Welcome/setup screen အချို့၊ flash message အနည်းငယ်၊ Admin ထဲ report column header
  အချို့ - key မထည့်ရသေး။ `audit-language-keys.php` ၏ `identical without burmese`
  စာရင်းသည် လက်ကျန် working list ဖြစ်သည်။
- Database ထဲရေးထားသော Agent note/name, activity log `action` string များကို
  ဘာသာပြန်ခြင်း မလုပ်ပါ (data မဟုတ် text)။

## Burmese ရေးသားရေး သတ်မှတ်ချက်များ

1. Unicode Myanmar only (Zawgyi မသုံး)။ Font ကို layout မှာ မသတ်မှတ်ဘဲ browser/OS က
   ရွေးစေသည် - Zawgyi ရောနေလျှင် ပြင်ရမည်။
2. ဂဏန်းများကို Latin (0-9) ဖြင့်ထားသည် - ပိုက်ဆံ/ရက်စွဲ စာရင်းများတွင် ရောမသုံးပါ။
3. Amount/date format ကို `number_format()` နှင့် `Asia/Yangon` date strings များဖြင့်
   ပြင်ဆင်ပြီးမှ translation placeholder ထဲ ထည့်ပါ။
4. Key အသစ်ထည့်တိုင်း `en` + `my` **တစ်ချိန်တည်း** ထည့်ပါ - parity script တက်အောင်။

## Test

```sh
php artisan test --filter="BurmeseLocaleUiTest|UserPreferenceTest"
php scripts/audit-language-keys.php
```

`tests/Feature/BurmeseLocaleUiTest.php` သည် `my` locale ဖြင့် page render, key မပျက်ကြောင်း
စစ်သည်။ `UserPreferenceTest` က locale save/switch ကို စစ်သည်။

## ပြင်ဆင်သူအတွက် checklist (screen အသစ်)

1. Blade ထဲ `__('app.xxx.yyy')` သုံးပါ - hardcoded Burmese/English မရေးပါ။
2. `lang/en/app.php` + `lang/my/app.php` နှစ်ဖိုင်လုံး key ထည့်ပါ။
3. `php scripts/audit-language-keys.php` run ပြီး `missing/extra = 0` သေချာပါစေ။
4. Test ထပ်ရေးပါ (locale နှစ်မျိုးလုံး assert)။
