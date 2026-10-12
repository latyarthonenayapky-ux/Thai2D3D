# Winning Yields Always (WYA)

Winning Yields Always (WYA) သည် 2D နေ့စဉ် Round များနှင့် 3D လစဉ် Draw များအတွက် sales entry,
Agent စီမံခန့်ခွဲမှု၊ ရလဒ်၊ settlement နှင့် summary များကို စီမံသည့် application
ဖြစ်သည်။

## ယခု Linux ကွန်ပျူတာပေါ်တွင် ဖွင့်သုံးရန်

Linux x86-64 အတွက် standalone AppImage ကို `release/Linux/` folder ထဲတွင်
ထားပေးထားသည်။ ထို folder ကို ကူးယူသွားလျှင်လည်း အလုပ်လုပ်ပါသည်။

**ဖွင့်ရန် — `release/Linux/Thai2D3D-1.1.0.AppImage` ကို File Manager မှ
double-click နှိပ်ပါ။**
ဖိုင်ကို executable အဖြစ်ပြင်ဆင်ထားပြီး PHP သို့မဟုတ် Composer ကို သီးခြား
install လုပ်ရန်မလိုပါ။ ပထမဆုံးဖွင့်ချိန်တွင် Owner အမည်၊ email၊ အနည်းဆုံး
6 လုံးရှိသော password နှင့် ထို email အတွက် ရယူထားသော Install pass key တို့ဖြင့်
local Owner account တည်ဆောက်ပါ။ နောက်တစ်ကြိမ်ဖွင့်လျှင် အဲဒီ account ဖြင့်
login ဝင်ပါ။

File Manager က launch ခွင့်မပေးလျှင် ဖိုင်ပေါ် right-click → **Properties** →
**Permissions** ထဲက executable/run ခွင့်ကို ဖွင့်ပြီး double-click ပြန်နှိပ်ပါ။
လိုအပ်လျှင် Terminal မှ တစ်ကြိမ်သာ အောက်ပါအတိုင်းဖွင့်နိုင်သည်။

```sh
chmod +x ./release/Linux/Thai2D3D-1.1.0.AppImage
./release/Linux/Thai2D3D-1.1.0.AppImage
```

App ပိတ်ရန် window ကိုပိတ်ပါ။ Local SQLite database နှင့် app data ကို
`~/.config/Thai2D3D/` အောက်တွင် သိမ်းထားသည်။ Backup/restore မလုပ်မီ app ကို
အရင်ပိတ်ပါ။ ဒီ standalone build ၏ database သည် ဒီကွန်ပျူတာပေါ်မှာသာရှိပြီး
VPS/online server သို့ အလိုအလျောက် sync မလုပ်ပါ။

**Windows:** portable offline app ကို `release/Windows/Thai2D3D/` folder
(`Thai2D3D.exe` အပါ) အဖြစ် ထားပေးထားသည်။ ဖွင့်ရန် `release/Windows/Start
Thai2D3D.cmd` ကို double-click နှိပ်ပါ (သို့မဟုတ် `Thai2D3D\Thai2D3D.exe` ကို
တိုက်ရိုက် double-click)။ Folder ကို ကူးယူသွားလျှင်လည်း အလုပ်လုပ်ပါသည်။
ပထမဆုံးအကြိမ်တွင် Owner email၊ အနည်းဆုံး 6 လုံး password နှင့် ထို email
အတွက် ရယူထားသော Install pass key တို့ဖြင့် local Owner account တည်ဆောက်ပါ။
PHP/Composer ကို သီးခြား install လုပ်စရာမလိုပါ။ PHP runtime အတွက်
Microsoft Visual C++ 2015–2022 Redistributable (x64) မရှိသေးသော Windows
ကွန်ပျူတာတွင် ၎င်းကို ထပ်မံတပ်ဆင်ရန် လိုနိုင်သည်။ ဤ app တွင် code-signing
certificate မပါသောကြောင့် Windows SmartScreen သတိပေးချက် ပြနိုင်သည်။
`release/Windows/_superseded/` ထဲရှိ ယခင် single-file `.exe` သည် အလုပ်မလုပ်
တော့သော version ဖြစ်၍ အသုံးမပြုပါနှင့်။

## Release folder များ

- `release/Windows/` — Windows portable app folder (`Thai2D3D\Thai2D3D.exe` နှင့်
  `Start Thai2D3D.cmd`)။ Folder ကို ကူးယူပြီး double-click နှိပ်ရုံဖြင့်
  အသုံးပြုနိုင်သည် — install မလိုပါ။ Password မေ့သွားလျှင်
  `Reset Thai2D3D password.cmd` ကို run ၍ data မပျက်ဘဲ ပြင်နိုင်သည်။
  `_superseded/` ထဲတွင် ယခင် single-file `.exe` အဟောင်းကို သိမ်းထားသည် (အသုံးမပြုရန်)။
- `release/Linux/` — Linux AppImage (`.AppImage`)။
- `release/VPS/` — VPS server သို့ upload လုပ်ရန် web app source (လက်ရှိ source
  code မှ တိုက်ရိုက်ကူးထားသည်)။ [VPS Setup Guide](docs/VPS_SETUP_MM.md) အတိုင်း
  အသုံးပြုပါ။

`release/Windows/Thai2D3D/` ကို 2026-10-11 တွင် လက်ရှိ source မှ ပြန်လည်
တည်ဆောက်ထားသည် (openssl fix၊ Install pass key အပါအဝင်)။ `release/Linux/`
AppImage သည် ယခင် build အတိုင်း ဖြစ်သည်။ Source ပြောင်းလဲမှုများ ပါဝင်စေရန်
Node.js ရှိသော computer တွင် `cd desktop && npm run dist` ဖြင့် ပြန်တည်ဆောက်ပြီး
ထွက်လာသည့် ဖိုင်များကို `release/` folder များထဲ အစားထိုးပါ။ `release/VPS/`
သည် snapshot ဖြစ်သဖြင့် source code ပြင်ဆင်တိုင်း ပြန်လည်ကူးယူသင့်သည်။

`php scripts/sync-release.php all` (သို့မဟုတ် `VPS` / `Windows` တစ်ခုတည်း) သည်
လက်ရှိ source မှ `app, config, database, lang, public, resources, routes, docs,
scripts, tests` နှင့် `artisan` စသည်တို့ကို `release/VPS/` နှင့်
`release/Windows/Thai2D3D/resources/laravel/` ထဲသို့ md5 နှိုင်းယှဉ်၍ သစ်လွင်အောင်
ပြင်ပေးသည် (`--dry-run` ဖြင့် အရင်စစ်ကြည့်နိုင်သည်)။
`vendor/`, `bootstrap/cache/`, `storage/`, `public/storage` တို့ကို မထိဘဲ
`.env` နှင့် `.sqlite` ဖိုင်များကို ဘယ်တော့မှ မကူးပါ။

## စာရွက်စာတမ်း

- [Burmese User Manual](docs/USER_MANUAL_MM.md) — login, role, 2D/3D sales,
  settings, results, settlement နှင့် offline queue
- [VPS Setup Guide](docs/VPS_SETUP_MM.md) — Ubuntu, MySQL, PHP, HTTPS,
  scheduler, Owner စတင်ဖန်တီးခြင်း၊ backup နှင့် update
- [Production Go-live Checklist](docs/production-deployment.md)
- [Agent Mobile Sales](docs/mobile-agent-sales.md) — Agent မိုဘိုင်း login code၊
  `/api/v1` auto-upload API၊ APK ဆောက်နည်း နှင့် GitHub Actions deploy
- [Desktop App Guide](desktop/README.md)

## VPS နှင့် Desktop အကြား အရေးကြီးသော ကွာခြားချက်

AppImage သည် ကွန်ပျူတာတစ်လုံးချင်းစီအတွက် သီးခြား local SQLite app ဖြစ်သည်။
VPS deployment သည် internet မှဝင်သုံးသည့် Laravel web app ဖြစ်ပြီး VPS ပေါ်ရှိ
MySQL database ကို အသုံးပြုသည်။ AppImage မှ VPS သို့ database sync လုပ်ပေးသည့်
feature မပါဝင်ပါ။ VPS ကို setup လုပ်မည့်အခါ User Manual ထဲက online server
လမ်းကြောင်းကို လိုက်နာပါ။

ဤ folder ထဲတွင် ရှိပြီးသား `.env` နှင့် local database configuration ကို
အစားမထိုးပါနှင့်။ Source code ပြင်ဆင်ခြင်း သို့မဟုတ် server deployment ပြုလုပ်ရန်
[VPS Setup Guide](docs/VPS_SETUP_MM.md) ကိုလိုက်နာပါ။
