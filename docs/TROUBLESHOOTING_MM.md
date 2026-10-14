# Troubleshooting (ဖြစ်လေ့ရှိသော ပြဿနာများနှင့် အဖြေ)

မတိုင်မီ - အရင်ဆုံး `/up` ကို စစ်ပါ။ `curl -o /dev/null -w '%{http_code}\n' https://wya2d3d.site/up`
= `200` ဆို server run နေ။ `401/422` သည် API ရှိနေကြောင်း ပြ (404 ဆို code မတက်ရသေး)။

## 1. Web / Server

### Upload / mobile auto-upload သည် 413 (Request Entity Too Large) ပေးသည်

**အကြောင်းရင်း** - Laravel ၏ global `ValidatePostSize` middleware သည် nginx မဟုတ်ဘဲ
PHP-FPM ၏ `post_max_size` (stock Ubuntu = `8M`) ကို စစ်သည်။ nginx
`client_max_body_size 130M` ([deploy/nginx/wya2d3d.site.conf:31](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/deploy/nginx/wya2d3d.site.conf#L31))
မှန်နေလျှင်ပင် PHP limit က အရင်တားသည်။

**အဖြေ** -

```sh
scripts/vps-fix-uploads.sh
```

`upload_max_filesize = 130M`, `post_max_size = 135M`, `max_execution_time = 300` ထည့်ပြီး
PHP-FPM ကို restart ပေးသည် (`TARGET_KB=133120` - vhost နှင့် ကိုက်)။ Deploy script သည်
step တစ်ခုအဖြစ် ထို ini ကို ထည့်ပြီးသား ဖြစ်သည်။ စစ်ရန် -

```sh
php -r 'echo ini_get("upload_max_filesize"), " / ", ini_get("post_max_size");'
```

Regression test - `tests/Feature/UploadTooLargeResponseTest.php`။

### HTTPS ရုတ်တရက် ပျောက်သွားသည် (443 = connection refused)

**အကြောင်းရင်း** - certbot သည် TLS block ကို vhost ဖိုင်တည်းထဲ ရေးထားသည်။ ထိုဖိုင်ကို
overwrite လုပ်သည့် vhost reinstall step သည် HTTPS နှင့် 80→443 redirect ကို ချက်ချင်း
ဖျက်သည် (2026-10-14 တွင် တွေ့ခဲ့ရ)။

**ကာကွယ်** - [scripts/vps-deploy.sh:118](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/scripts/vps-deploy.sh#L118)
သည် `listen 443` တွေ့လျှင် ဖိုင်ကို **ထိခြင်းမပြု** တော့ (warning ပဲ ပြ)။ Deploy မမေးသော
vhost install prompt ရောက်လျှင် **N ဖြင့် ပြေလည်** (Y = TLS ပျက်)။

**ပြင်** - တစ်ကြိမ်တည်းဖြင့် ပြန်ရ -

```sh
certbot --nginx -d wya2d3d.site -d www.wya2d3d.site
```

ထို့နောက် `.env` ထဲ `APP_URL=https://wya2d3d.site`, `SESSION_SECURE_COOKIE=true` ပြန်စစ်ပြီး
`php artisan config:clear && php artisan optimize`။

### nginx rejects the vhost config

`nginx -t` အရင် run ပါ။ Config ရေးသည့် step သည် reject ဖြစ်လျှင် ဘာမှ မပြောင်းဘဲ
warning ကျန်သည် (deploy log)။ `root /var/www/thai2d3d/public` ရှိ/မရှိ နှင့်
`server_name wya2d3d.site www.wya2d3d.site` ကို စစ်ပါ။

### 500 error / white page (VPS)

- `storage/` နှင့် `bootstrap/cache/` ရေးချခွင့် - `chown -R www-data:www-data`,
  `chmod -R ug+rwx` (inspect script ပြသည်)။
- `.env` မှန်/`APP_KEY` generate လုပ်ပြီးသလား၊ `php artisan config:clear` လုပ်ပြီးသလား။
- Log - `storage/logs/laravel.log`။ Smoke test သည် log ကွက်လျက်ရမည့် check ပါသည်။

### Cron/scheduler မ run နေ (Round မဖွင့်/မပိတ်)

```sh
crontab -u www-data -l                 # ရှိမရှိ
php artisan schedule:list              # rounds:sync-schedule (everyMinute), users:expire-temp (hourly)
php artisan rounds:sync-schedule       # လက်ခေါ် စမ်း
```

Timezone သည် `Asia/Yangon` ဖြစ်သည်။ `close_time` မသတ်မှတ်ထားသော Round schedule သည်
Round ကို မဖန်တီးပါ ([round-scheduling.md](round-scheduling.md))။

## 2. Desktop (Windows / Linux)

### Windows SmartScreen သတိပေးချက်

Code-signing certificate မရှိသောကြောင့် ပုံမှန်ဖြစ်သည် - **More info → Run anyway**။
App folder ကို copy လုပ်၍ သုံးနိုင်သည် (install မလို)။

### PHP runtime မ run (Windows)

Visual C++ 2015-2022 Redistributable (x64) လိုနိုင်သည် - Install မလုပ်ထားသော PC တွင်
bundled PHP ဖွင့်မရ။ Microsoft official redistributable ကို တပ်ပါ။

### Linux AppImage double-click ဖြင့် မဖွင့်

File Manager launch ခွင့်မပေးလျှင် - right-click → **Properties → Permissions** →
executable ဖွင့်၊ သို့မဟုတ် terminal တွင် -

```sh
chmod +x ./release/Linux/Thai2D3D-1.1.0.AppImage
./release/Linux/Thai2D3D-1.1.0.AppImage
```

(Repo script များ၏ exec bit ပျက်ခြင်းသည် Windows NTFS worktree issue ဖြစ်သည် -
`git add --chmod=+x scripts/foo.sh` ဖြင့် stage ပါ။)

### Password မေ့

`release/Windows/Reset Thai2D3D password.cmd` run ပါ - SQLite data မပျက်ပါ။
VPS တွင် - `php artisan thai2d3d:reset-password`။

### Local data ဘယ်နေရာရောက်နေ

- Linux - `~/.config/Thai2D3D/data/thai2d3d.sqlite`
- Windows - `%APPDATA%\Thai2D3D\data\thai2d3d.sqlite`

Backup/restore မလုပ်မီ app ကို **အရင်ပိတ်** (live DB copy မယူပါနှင့်)။
Desktop DB သည် VPS နှင့် sync **မဖြစ်ပါ** - လုပ်ငန်း data ကို ရွှေ့ရန်
Admin → Data backup / Business move (`thai2d3d:export-business`) ကို သုံးပါ။

## 3. Login / Account

| လက္ခဏာ | အကြောင်းရင်း | လုပ်ရန် |
| --- | --- | --- |
| "This temporary account has expired." | `expires_at` ကျော်ပြီး cron ပိတ် (`users:expire-temp`) | Admin က temp_admin/operator account ကို status ပြန်ဖွင့် သို့မဟုတ် အသစ်ဖန်တီး |
| "This account is inactive." | `users.status = false` | Admin → Users → status |
| Login မှန်ပေမယ့် ခဏခဏ ထွက်ကျ | `preferences` idle timeout (`idle_minutes >= 5`) သို့မဟုတ် `SESSION_LIFETIME`/`SESSION_DRIVER` မှား | Settings → Security တွင် idle တန်ဖိုး၊ `.env` session config စစ် |
| Login throttle သတင်းစကား | `throttle:login` ကျော်အောင်ကြိမ်ရိုက် | 1 မိနစ်ခန့် စော |
| 403 on admin screens (owner account) | owner သည် `isAdmin()` မဟုတ် - [Roles and Access](ROLES_AND_ACCESS_MM.md) §4 | Admin role ဆုံးအကောင့်ဖြင့် ဝင် |

## 4. Agent mobile app

### "Cannot reach the office server"

1. Domain သုံးပါ - `https://wya2d3d.site` (IP `118.27.150.3` ရိုက်လျှင် TLS certificate
   check ဖြင့် ငြင်းခံရမည်)။ Bare `wya2d3d.site` ကို app က `https://` အဖြစ် သတ်မှတ်သည်။
2. ဖုန်း internet တကယ် သုံးလို့ရလား (captive portal Wi-Fi, data credit ကုန်)။
3. APK ၏ `INTERNET` permission - `flutter create` သည် debug/profile manifest ထဲသာ
   ထည့်ပေးတော့သည်။ Release APK ဤ permission မပါလျှင် socket မဖွင့်နိုင် ([mobile/README.md](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/mobile/README.md))။
   လက်ရှိ APK ကို **Settings → Agent mobile app (APK)** မှ ပြန် download ယူပါ။
4. HTTPS မတပ်ရသေးသော server သို့ - `usesCleartextTraffic="true"` လိုအပ် (CI build လုပ်ပေး)။

Office machine မှ စစ်ရန် - `GET /up` = 200, `POST /api/v1/auth/login` (Accept:
application/json) = 422 (credential မပါ) / 401 (token endpoint)။

### Sign-in ဖြစ်ပေမယ့် upload မရ / token ပျက်

- Token သည် `AGENT_TOKEN_TTL_DAYS` (default 180 ရက်) သက်တမ်း။
- PIN ပြောင်းခြင်း သို့ mobile access ပိတ်ခြင်းသည် ထို Agent ၏ **ဖုန်းအားလုံး** ကို
  ချက်ချင်းထုတ် (`AgentMobileAccessService::issue()` revokes all tokens)။
- `403 This Agent is not allowed to use the mobile app` = Agent `status != active`
  သို့ `hasMobileAccess() == false`။

### Duplicate / pending review

Upload batch တွင် `client_uuid` duplicate ဆို ပြီးသား record ကို ပြန်ပေး (conflict message)။
Round ပိတ်နေ/Hot number/limit conflict တွေ့လျှင် sale မသိမ်းဘဲ
Admin → Offline sync review ထဲ `pending` ဝင်သည် - approve/reject လုပ်ရ ([Business Logic](BUSINESS_LOGIC_MM.md) §7)။

## 5. Numbers / Limits

| မေးခွန်း | အဖြေ |
| --- | --- |
| နံပါတ် ဘာလို့ rejected ဖြစ်သလဲ | `reject_reason` ကြည့် - `HOT_NUMBER`, `NUMBER_LIMIT`, `HOT_NUMBER_3D`, `NUMBER_LIMIT_3D`, `ALL_NUMBERS_REJECTED` |
| 2D limit ကျော်ရင် ရောင်းလို့မရဘူးလား | ရသည် - warn-only၊ အနီရောင် flag ပဲ ပေါ် ([Amount Limit](AMOUNT_LIMITS_MM.md)) |
| Settlement တွင် winnings = 0 | Result မထည့်ရသေး သို့ `payout_2d_multiplier`/`payout_3d_multiplier` မထည့်ရသေး (`payout_.._configured = false`) |
| Void လုပ်ပေမယ့် total မကျ | Detail status များ `voided` ဖြစ်ရမည် - restore snapshot ပျက်နေလျှင် `admin.activity-log` မှ ပြန်စစ် |
| Ledger balance လွဲနေ | Balance သည် sum of entries - entry မှားဖျက်မိ/ထပ်နေကြောင်း statement (`from/to`) နှင့် cross-check |

## 6. ပြင်ဆင်သူများအတွက်

- ကွန်ပျူတာတွင် system PHP မရှိလျှင် - `./desktop/runtime/php-win/php.exe` သုံး
  ([Development](DEVELOPMENT_MM.md) §1)။
- Test run - `php artisan test` (SQLite `:memory:`, MySQL မလို)။
- Release payload ပြဿနာ - `bash scripts/smoke-release.sh` ([Release & Build](RELEASE_BUILD_MM.md) §3)။
- Server state audit - `Check WYA VPS.cmd` → `vps-inspect.log` (`.log` files are Git-ignored,
  credentials are never printed - key names only)။
