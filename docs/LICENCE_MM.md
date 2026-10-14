# Licence (Desktop app ကို တပ်ဆင်/သုံးခွင့်ပြုသော လက်မှတ်ရေးထိုး string)

ဤ document သည် Desktop app (Windows) ၏ **Owner account ဖန်တီးခြင်း** နှင့်
**လစဉ်/နှစ်စဉ် သုံးခွင့်** ကို ထိန်းချုပ်သော signed licence စနစ်ကို ရှင်းပြသည်။
Licence ထုတ်ပေးမည့် vendor နှင့် code ပြင်မည့် developer နှစ်ဦးစလုံးအတွက် ဖြစ်သည်။
(ယခင် `INSTALL_PASS_KEY_MM.md` တွင် ဖော်ပြသော email မှ တွက်ထုတ်သည့် pass key
စနစ်ကို ဤ licence စနစ်က အစားထိုးပြီးဖြစ်သည်။)

## ဘာကြောင့် လိုခိုင်းသနည်း

Desktop app တစ်ခုစီသည် Internet နှင့် မချိတ်ဆက်ဘဲ ကွန်ပျူတာတစ်လုံးတည်းပေါ်တွင်
SQLite database ဖြင့် အလုပ်လုပ်သည်။ ထို့ကြောင့် licence server မလိုပါ။ App ကိုယ်တိုင်က
vendor ၏ **private key** ဖြင့် လက်မှတ်ရေးထိုးထားမှုကို **public key** ဖြင့် စစ်ဆေးသည်
(Ed25519 / PHP sodium)။ Offline ပင် စစ်နိုင်သည် - signature မှားလျှင် လုံးဝ လက်ခံမည်မဟုတ်ပါ။

Private key သည် repository ထဲတွင် လုံးဝ မရှိပါ။ Vendor ကွန်ပျူတာ၏
`%USERPROFILE%\.wya-licence-key\secret-key.txt` (သို့မဟုတ် `WYA_LICENCE_SECRET_KEY`
env) တွင်သာ ရှိသည်။ App ထဲတွင် public key သာ ပါဝင်သည်
([app/Services/Licence/Licence.php:42](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/Licence/Licence.php#L42))။

## Licence string ပုံစံ

```
base64url(payload) . base64url(signature)
```

Payload သည် canonical JSON ဖြင့် key အစဉ် မပြောင်းအောင် ရေးထားသည် -

```json
{"v":1,"id":"WYA-2026-0001","email":"owner@example.com","plan":"standard","max_admins":1,"max_agents":50,"issued_at":"2026-10-14","expires_at":"2027-10-14"}
```

- `id` - licence စာရင်းနံပါတ် (CSV တွင်လည်း မှတ်တမ်းတင်သည်)။
- `email` - Owner account ဖွင့်မည့် email။ `trim` + `strtolower` ဖြင့် နှိုင်းယှဉ်သည်။
- `plan` - ခွဲခြားရေးသားချက်သာ (ယခု version တွင် seat quota ကို အတင်း အကောင်အထည် မဖော်ပါ)။
- `max_admins` / `max_agents` - သိမ်းထား၍ Licence page တွင် ပြသည်။
- `issued_at` / `expires_at` - `Y-m-d`။ Expires ရက်သည် ထိုရက်၏ 23:59:59 (Asia/Yangon) အထိ ဖြစ်သည်။

Key အစဉ်၊ `JSON_UNESCAPED_SLASHES` တို့သည် signature ဖြင့် တင်းတင်းကျပ်ကျပ် ချိတ်သည်။
Signer နှင့် app နှစ်ဖက်လုံး `Licence::canonicalPayload()` တစ်ခုတည်းကို သုံးသည်။

## အဆင့်အားလုံး (status)

| Status | အဓိပ္ပာယ် | ရေးသားခွင့် |
| --- | --- | --- |
| `valid` | expires_at အတွင်း | ရှိသည် |
| `grace` | expires_at နောက် ၇ ရက် (GRACE_DAYS) | ရှိသည် |
| `expired` | grace ပြီးပြီး | **မရှိ** (read-only) |
| `clock_rollback` | နာရီကို ၂၄ နာရီထက် ပိုနောက်ဆွဲသည် (`last_seen_at` နှင့် နှိုင်းယှဉ်) | **မရှိ** |
| `invalid` | database ထဲက string signature မမှန်တော့ (ပြင်ထားသည်) | **မရှိ** |
| `missing` | licence လုံးဝ မတပ်ရသေး | **မရှိ** |

Read-only ဖြစ်ချိန်တွင်ပင် **ဖတ်ခြင်း၊ backup ယူခြင်း/restore လုပ်ခြင်း၊ Licence page ဖွင့်၍
renew လုပ်ခြင်း၊ logout** တို့ ဆက်လက် အလုပ်လုပ်သည်။ ဖောက်သည် data ကို ချုပ်မထားပါ။
Block သည် web group ရှိ POST/PUT/PATCH/DELETE များနှင့် login သာ ဖြစ်သည်
([app/Http/Middleware/EnsureValidLicence.php](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Http/Middleware/EnsureValidLicence.php))။
VPS install (desktop mode ပိတ်ထားသော server) တွင် ဤ middleware လုံးဝ မတက်ပါ။

## Vendor အနေဖြင့် licence ထုတ်နည်း

1. **Key pair အသစ် (ပထမဆုံး အကြိမ်တည်း)** -

   ```sh
   desktop\runtime\php-win\php.exe desktop\keygen\generate-key.php
   ```

   ထွက်လာသော public key line ကို `Licence.php` ထဲ `EMBEDDED_PUBLIC_KEY` တွင်
   ထည့်ပြီး app ကို ပြန် buildပါ။ Private key ဖိုင်ကို repository ထဲ မထည့်ရပါ (0600 ဖြင့်
   repo အပြင်တွင် သိမ်းသည်)။ Key ပြောင်းလျှင် ယခင် licence အားလုံး သုံးမရတော့ပါ။

2. **Licence လက်မှတ်ရေးထိုးခြင်း** - ဖောက်သည် email ကို receive လုပ်ပြီးနောက် -

   ```sh
   desktop\keygen\sign-licence.bat --email owner@example.com --id WYA-2026-0012 \
     --plan standard --years 1 --max-admins 1 --max-agents 50 --payment-ref INV-17
   ```

   licence string ကို print လုပ်ပြီး `desktop/keygen/issued-licences.csv` ထဲ
   `id,email,plan,issued,expiry,payment-ref` row ထပ်ရေးသည်။ ထို CSV တွင် ဖောက်သည်
   email နှင့် payment reference ပါသောကြောင့် **git-ignore**ထားပြီး commit မရပါ။

3. ဖောက်သည်ထံ licence string ကို ပို့ပေးပါ (Step 2 wizard သို့မဟုတ် Licence page တွင် paste)။

## ဖောက်သည်ဖက်မှ လုပ်ရန်

1. Desktop app ကို ပထမဆုံးဖွင့်ပါ (`release/Windows/Start Thai2D3D.cmd`)။
2. **အဆင့် ၁** - Owner အမည်၊ licence တောင်းသည့် email အတူ၊ အနည်းဆုံး ၆ လုံး password။
3. **အဆင့် ၂** - vendor မှရသော licence string ကို textarea ထဲ paste ပြီး Create Owner နှိပ်ပါ။
   Email မကိုက်လျှင် / grace အပြင်ရက်လွန် licence ဖြစ်လျှင် / signature မမှန်လျှင်
   Owner account ဖန်တီးမည် မဟုတ်ပါ။
4. Licence သက်တမ်း ၃၀/၇/၁ ရက် ကျန်ချိန်တွင် dashboard အပေါ် banner ပေါ်သည်။
   Settings → Licence (owner သာ) တွင် renew လုပ်နိုင်သည်။
5. သက်တမ်းကုန်ပြီး grace ၇ ရက်လွန်လျှင် app က read-only ဖြစ်သွားသည်။
   `/desktop/licence` page ပေါ်ရောက်ပြီး renew လုပ်မှသာ ပြန်အသုံးပြုနိုင်သည်။
   Data မပျက်ပါ - backup လည်း ထုတ်နိုင်သည်။

Licence page သည် login မဝင်ဘဲ ဖွင့်နိုင်သည် (locked app တွင် login ပိတ်ထားသောကြောင့်
renew box သည် signed-out အခြေအနေမှာပင် အလုပ်လုပ်ရမည်)။

## Test နှင့် known-answer vector

| အစိတ်အပိုင်း | ဖိုင် |
| --- | --- |
| Parse/verify/status rules | [app/Services/Licence/Licence.php](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/Licence/Licence.php) |
| Install/status/banner | [app/Services/Licence/LicenceService.php](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Services/Licence/LicenceService.php) |
| Read-only gate | [app/Http/Middleware/EnsureValidLicence.php](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Http/Middleware/EnsureValidLicence.php) |
| Signer / keygen (vendor-only) | `desktop/keygen/sign-licence.php`, `generate-key.php`, `sign-licence.bat` |
| Signature tests | [tests/Feature/LicenceSignatureTest.php](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/tests/Feature/LicenceSignatureTest.php) |
| Enforcement tests | [tests/Feature/LicenceEnforcementTest.php](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/tests/Feature/LicenceEnforcementTest.php) |

`LicenceSignatureTest` ၏ `VECTOR_LICENCE` သည် **အစစ် embedded key** ဖြင့် signer ကို
သုံးပြီး ထုတ်ထားသော fixed vector ဖြစ်သည် - signer နှင့် app drift တက်လျှင် ထို test ချက်ချင်း
ပျက်မည်။ Test များသည် တိုက်ရိုက် key pair အသစ် ဖန်တီးပြီး
`config('licence.public_key')` ကို ပြောင်းသုံးကြသည် (vendor private key မလိုပါ)။

```sh
desktop\runtime\php-win\php.exe artisan test --filter=Licence
```

sodium extension မပါသော CI image တွင် licence test များသည် skip ဖြစ်သည်။

## ထပ်မံသိထားသင့်သည်များ

- Owner account ၏ role မှာ `owner` ဖြစ်သည်။ Admin များကို စီမံနိုင်သော်လည်း business
  settings၊ ledger၊ settlement စသည့် admin-only screen များတွင် 403 ရနိုင်သည် -
  [Roles and Access](ROLES_AND_ACCESS_MM.md) ကို ဖတ်ပါ။
- Password မေ့သွားလျှင် data မပျက်ဘဲ `release/Windows/Reset Thai2D3D password.cmd` ကို
  run ပါ (licence မမေးပါ)။
- `desktop/keygen/` သည် vendor-only ဖြစ်ပြီး ထုတ်ကုန် package ထဲ မထည့်ပါ။
- Agent mobile API (`routes/api.php`၊ `/api/v1`) ကို ယခု version တွင် licence ဖြင့်
  မပိတ်သေးပါ - desktop read-only တွင် web UI မှတဆင့်သာ ကန့်သတ်သည်။
- Licence code ကို ပြင်ဆင်တိုင်း Desktop bundle များကို ပြန်တည်ဆောက်ရပါမည် -
  [Release and Build guide](RELEASE_BUILD_MM.md) ကို လိုက်နာပါ။
- Linux packaging: `desktop/runtime/php-linux` တွင် bundled PHP မထည့်ရသေးသဖြင့်
  Linux build သည် sodium ရှိ/မရှိ အပေါ် မူတည်သည်။ Linux package မတည်ဆောက်မီ
  ထို runtime ကို အရင်ဖြည့်ပါ။
