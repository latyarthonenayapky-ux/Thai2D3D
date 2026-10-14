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
6 လုံးရှိသော password နှင့် ထို email အတွက် ဆော့ဖ်ဝဲပေးသူထံ ရယူထားသော
Licence string တို့ဖြင့် local Owner account တည်ဆောက်ပါ။ နောက်တစ်ကြိမ်ဖွင့်လျှင် အဲဒီ account ဖြင့်
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
အတွက် ဆော့ဖ်ဝဲပေးသူထံ ရယူထားသော Licence string တို့ဖြင့် local Owner account တည်ဆောက်ပါ။
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
တည်ဆောက်ထားသည် (openssl fix အပါအဝင်)။ ထို build သည် Licence စနစ်
([docs/LICENCE_MM.md](docs/LICENCE_MM.md)) မတိုင်မီ ကာလဖြစ်သဖြင့် desktop licence
ပါဝင်စေရန် ပြန်တည်ဆောက်ရပါမည်။ `release/Linux/`
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

## Online server (VPS) — လက်ရှိအခြေအနေနှင့် လုပ်ရန်များ

Online app ထိပ်မတင်ခင် ဘယ်အဆင့် ရပ်နေလဲ သိနိုင်အောင် အောက်ပါဇယားကို
2026-10-13 တပ်ဆင်စစ်ဆေးမှုအရ ရေးထားသည်။

| အဆင့် | အခြေအနေ | အဖြေပြ အထူးစစ် |
| --- | --- | --- |
| Domain DNS | ❌ မထည့်ရသေး | `nslookup wya2d3d.site 8.8.8.8` → အဖြေမရှိ |
| VPS web server | ⚠️ nginx + PHP-FPM run နေ, HTTP သာ | `http://118.27.150.3/up` → reachable; `https://` → port 443 ပိတ် |
| Agent mobile API | ❌ server ပေါ် မတက်ရသေး | `POST /api/v1/auth/login` → **404** |
| GitHub | ❌ branch မတင်ရသေး | `git ls-remote` → `master`, `MasterW` သာ; local commits ၃ ခု (`9a42eea`, `78899e8`, `65f96ad`) |
| APK build | ❌ မဖွင့်သေး | `…/actions` → run အဟောင်း ၁ ခု (Symfony template, failed) |
| Admin APK publish | ✅ code ထဲ ပြီး | `scripts/smoke-release.sh` 23/23 PASS (Windows + VPS payload) |

Blocker မှာ code မဟုတ်ပါ — **credential ၂ ခု** သာလိုသည်: GitHub push login၊
VPS SSH access။ ၎င်းတို့ကို လက်လုပ်ရိုက်တင်ရန် မလိုအောင် Desktop ထဲ helper
၂ ဖိုင်ထားပေးထားသည် (ဖိုင်ထဲက code သည် `git push` နှင့် read-only SSH
inspection သာ လုပ်သည်)။

- **`Push Thai2D3D to GitHub.cmd`** — double-click → branch `VPS2D3D` ကို push
  ပို့မည်။ ပထမဆုံးအကြိမ် browser ထဲ GitHub (Google) login မေးနိုင်သည်။
  Window ထဲ `PUSH OK` ပေါ်လျှင် Laravel CI နှင့် Android APK workflow အလိုအလျောက်
  စတင်မည်။
- **`Check WYA VPS.cmd`** — double-click → SSH user (default `root`) နှင့် server
  password ရိုက်ထည့်ပါ။ `scripts/vps-inspect.sh` ကို server ပေါ်တွင် pipe ဖြင့်
  run ပါသည် (install/write/restart ဘာမှ မလုပ်)၊ ရလဒ်ကို project root ထဲ
  `vps-inspect.log` (`*.log` ဖြစ်သဖြင့် Git ထဲ မဝင်) သို့ ရေးသည်။ စစ်ပေးသည်:
  PHP/Composer/nginx/MySQL/certbot version + service status၊ `/var/www` ထဲ
  application directory၊ `vendor/` နှင့် `.env` ရှိ/မရှိ၊ `storage`/
  `bootstrap/cache` ရေးချခွင့်၊ အရေးကြီး route ၄ ခု (`api/v1/auth/login`,
  `api/v1/sales/upload`, `settings/mobile-app`, `operator.offline`) တပ်ထားခြင်း
  ရှိ/မရှိ၊ nginx `server_name`/`root`/`client_max_body_size`၊ PHP upload limit၊
  Let's Encrypt certificate၊ `http://127.0.0.1/up` နှင့် `/api/v1/auth/login`
  HTTP code၊ www-data cron နှင့် backup။ **.env value များနှင့် password များကို
  ဘယ်တော့မှ မပုံနှိပ်ပါ** — key နာမည်များသာ ပြသည်။

Server အခြေအနေ သိပြီးလျှင် upgrade အစီအစဉ် (တစ်ခုချင်း run ပါ):
code ပို့ → `composer install --no-dev --prefer-dist --optimize-autoloader` →
`php artisan migrate --force` → `php artisan db:seed --force` →
`php artisan optimize` → vhost (`deploy/nginx/wya2d3d.site.conf`) + PHP upload
limit (`upload_max_filesize=130M`) → `certbot --nginx -d wya2d3d.site -d
www.wya2d3d.site` → `.env` ထဲ `APP_URL=https://wya2d3d.site`,
`SESSION_SECURE_COOKIE=true` → config clear + optimize။ အသေးစိတ် (Burmese):
[VPS Setup Guide](docs/VPS_SETUP_MM.md) §1 DNS ဇယား၊ §5 `.env`၊ §6 nginx +
certbot၊ §7 scheduler။ Mobile API/APK အတွက်:
[Agent Mobile Sales](docs/mobile-agent-sales.md)။

## စာရွက်စာတမ်း

အားလုံး၏ အညွှန်း - [docs/README.md](docs/README.md)

**သုံးစွဲသူ / တပ်ဆင်သူ**

- [Burmese User Manual](docs/USER_MANUAL_MM.md) — login, role, 2D/3D sales,
  settings, results, settlement နှင့် offline queue
- [VPS Setup Guide](docs/VPS_SETUP_MM.md) — Ubuntu, MySQL, PHP, HTTPS,
  scheduler, Owner စတင်ဖန်တီးခြင်း၊ backup နှင့် update
- [Production Go-live Checklist](docs/production-deployment.md)
- [Troubleshooting](docs/TROUBLESHOOTING_MM.md) — 413 upload limit, HTTPS ပျောက်ခြင်း,
  SmartScreen/MSVC, AppImage exec bit, password reset, mobile sign-in
- [Round Scheduling](docs/round-scheduling.md)

**လုပ်ငန်းစည်းမျဉ်း (business rules)**

- [Amount Limit နှင့် Number Limit](docs/AMOUNT_LIMITS_MM.md) — 2D round-wide cap,
  3D tier ၃ ဆင့်, warn vs block
- [Agent Cash Ledger](docs/AGENT_CASH_LEDGER_MM.md) — deposit/credit/repayment/withdrawal
  နှင့် balance တွက်နည်း
- [Business Logic](docs/BUSINESS_LOGIC_MM.md) — sale processing, settlement math,
  void/restore, offline sync review, reports
- [Licence](docs/LICENCE_MM.md) — Desktop licence string, vendor signer နှင့် known-answer vectors
- [Roles and Access](docs/ROLES_AND_ACCESS_MM.md) — owner/admin/temp_admin/operator,
  middleware, owner 403 quirk
- [Data Model](docs/DATA_MODEL_MM.md) — ဇယားများ၊ columns၊ relations
- [Localization](docs/LOCALIZATION_MM.md) — en/my key parity နှင့် language switch

**Developer / release**

- [Local Development](docs/DEVELOPMENT_MM.md) — PHP runtime, tests, CI, code layout
- [Release and Build](docs/RELEASE_BUILD_MM.md) — release folders, sync/smoke scripts,
  desktop build, APK build, VPS deploy tooling
- [Agent Mobile Sales](docs/mobile-agent-sales.md) — Agent မိုဘိုင်း login code၊
  `/api/v1` auto-upload API၊ APK ဆောက်နည်း နှင့် GitHub Actions deploy
- [Desktop App Guide](desktop/README.md)
- [Mobile App Guide](mobile/README.md)

## VPS နှင့် Desktop အကြား အရေးကြီးသော ကွာခြားချက်

AppImage သည် ကွန်ပျူတာတစ်လုံးချင်းစီအတွက် သီးခြား local SQLite app ဖြစ်သည်။
VPS deployment သည် internet မှဝင်သုံးသည့် Laravel web app ဖြစ်ပြီး VPS ပေါ်ရှိ
MySQL database ကို အသုံးပြုသည်။ AppImage မှ VPS သို့ database sync လုပ်ပေးသည့်
feature မပါဝင်ပါ။ VPS ကို setup လုပ်မည့်အခါ User Manual ထဲက online server
လမ်းကြောင်းကို လိုက်နာပါ။

ဤ folder ထဲတွင် ရှိပြီးသား `.env` နှင့် local database configuration ကို
အစားမထိုးပါနှင့်။ Source code ပြင်ဆင်ခြင်း သို့မဟုတ် server deployment ပြုလုပ်ရန်
[VPS Setup Guide](docs/VPS_SETUP_MM.md) ကိုလိုက်နာပါ။
