# Release, Build and Packaging Guide

Project ၏ ထုတ်ကုန်ပုံစံ ၃ မျိုး - **Windows portable app**, **Linux AppImage**,
**VPS web app** (+ Agent mobile APK)။ ၎င်းတို့အားလုံးသည် `release/` folder ထဲ ရှိသည်။

## 1. release/ folder layout

```
release/
  Windows/
    Start Thai2D3D.cmd                 ← double-click ဖွင့်ရန်
    Reset Thai2D3D password.cmd        ← data မပျက် password ပြန်သတ်မှတ်
    Thai2D3D/                          ← portable app (Thai2D3D.exe + resources/)
    keygen/                            ← vendor-only (ထုတ်ကုန်ထဲ မပါ)
    _superseded/                       ← အလုပ်မလုပ်တော့သော single-file .exe အဟောင်း
  Linux/
    Thai2D3D-1.1.0.AppImage
  VPS/                                 ← server သို့ upload လုပ်ရန် source snapshot
  VPS.zip
  keygen/
```

`release/VPS/` သည် **snapshot** ဖြစ်သည် - source ပြောင်းတိုင်း ပြန်ကူးရပါမည်။
`release/VPS/vendor/` ထဲ throwaway copy တစ်ခု ကျန်နေသေးသည် (ဖျက်ရန် စာရင်းဝင်)။

## 2. Source မှ release/ သို့ sync လုပ်နည်း

```sh
php scripts/sync-release.php all          # VPS + Windows
php scripts/sync-release.php VPS          # တစ်ခုတည်း
php scripts/sync-release.php all --dry-run
```

[scripts/sync-release.php](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/scripts/sync-release.php) -

- ကူးသည် - `app, config, database, lang, public, resources, routes, docs, scripts, tests`
  နှင့် root files (`artisan`, `composer.json`, `composer.lock`, `README.md`, `PRODUCT.md`,
  `phpunit.xml`, `package.json`, `vite.config.js`, `.env.example`)။
- တည်နေရာ - `release/VPS/` နှင့် `release/Windows/Thai2D3D/resources/laravel/`။
- md5 နှိုင်းယှဉ်၍ ပြောင်းလဲမှုသာ ရေးသည်; stale file များကို ပြန်စစ်ပေး
  (`--dry-run` ဖြင့် အရင်ကြည့်)။
- **ဘယ်တော့မှ မကူး/မဖျက်** - `vendor/`, `bootstrap/cache/`, `storage/`,
  `public/storage`, `.env*`, `*.sqlite(.journal)` (regex `$neverCopy`)။

## 3. Smoke test (release payload မှန်/မန်)

```sh
bash scripts/smoke-release.sh
```

[scripts/smoke-release.sh](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/scripts/smoke-release.sh)
သည် payload ကို bundled PHP `php -S` ဖြင့်တင်ပြီး စစ်သည် (23 checks) -

- `thai2d3d:desktop-initialize` run ဖြစ်ခြင်း၊ empty payload သည် owner setup ကို မေးခြင်း၊
  migrations မှတ်တမ်းတက်ခြင်း၊ void routes registered
- `GET /login` = 200, root URL serves app, static PNG/JS MIME type မှန်
- `lang/` files payload ထဲ ပါခြင်း၊ header brand logo reference ရှိ
- Agent mobile API routed, unauthenticated upload rejected, bad sign-in rejected
- Admin-only APK routes registered; APK download သည် admin session မပါ 403
- Laravel log နှင့် built-in server log ကွက်လျက် (warning မရှိ)

`23/23 PASS` ဆိုသည် အထက်စာရင်း အားလုံး အောင်မြင်ကြောင်း ဖြစ်သည် - Windows + VPS
payload နှစ်ခုလုံးအတွက်။

## 4. Desktop app ပြန်တည်ဆောက်နည်း

PHP/Node ပါသော ကွန်ပျူတာတွင် ([desktop/README.md](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/desktop/README.md)) -

```sh
cd desktop
npm ci
npm run dist:linux   # dist/standalone-linux/Thai2D3D-1.1.0.AppImage
npm run dist:win     # dist/standalone-win/  (windows.php.net မှ PHP 8.3 NTS x64 download)
```

ရလဒ်ကို release/ ထဲ အစားထိုး -

- Windows - `dist/standalone-win/win-unpacked/*` → `release/Windows/Thai2D3D/`
  (`Start Thai2D3D.cmd`, `Reset Thai2D3D password.cmd`, `_superseded/` မပြောင်း)။
- Linux - `dist/standalone-linux/Thai2D3D-1.1.0.AppImage` → `release/Linux/`။
  **သတိ** - လက်ရှိ AppImage သည် build အဟောင်းဖြစ်နေသေးသည်၊ source ပြောင်းမှုများ
  မပါသေး။

Windows build တစ်ခုကို ပြန်စုစည်းရန် (Node မရှိလျှင်) - `win-unpacked` ကူးပြီး
`resources/laravel` နှင့် `resources/php` ကို `stage/` မှ ဖြည့်၊ `@electron/asar` ဖြင့်
`resources/app.asar` ကို `main.js`, `preload.js`, `package.json`, `scripts/` မှ ပြန်ပိုးသည်။
Copy rules များသည် `desktop/scripts/prepare.js` နှင့် တူရမည်။

## 5. Agent mobile APK

Local build (Flutter 3.22+ / Android SDK 34) -

```sh
cd mobile
flutter pub get
flutter create --platforms=android --org com.wya --project-name wya_agent_mobile .
flutter build apk --release --dart-define=WYA_SERVER_URL=https://wya2d3d.site
```

`android/` သည် generated ဖြစ်သဖြင့် commit မလုပ်ပါ။ `applicationId`
`com.wya.wya_agent_mobile`; manifest ထဲ `android.permission.INTERNET` **ပါရမည်**
(ပါမနေသေးသော ပထမ APK သည် sign-in မရ - 2026-10-14 ပြင်ပြီး aapt ဖြင့် အတည်ပြုထား)။

GitHub Actions (`workflow_dispatch` သို့မဟုတ် `mobile/**` ပြောင်းလဲချိန်; branches
`master`, `VPS2D3D`) - [.github/workflows/android-apk.yml](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/.github/workflows/android-apk.yml)
သည် Flutter 3.24.5 install ပြီး artifact ၂ ခုတင် - `wya-agent-debug-apk` (30 days),
`wya-agent-release-apk` (90 days)။ Release build သည် debug key ဖြင့် sign ဖြစ်နေသေးသည် -
Phone ထဲ တိုက်ရိုက် install လုပ်ရန် လုံလောက်သော်လည်း Play Store မတင်ဘဲ sideload
သုံးမည့်အတွက် real keystore ကို repository secret အဖြစ် ထည့်သွင်းရန် ကျန်သေး ([mobile/README.md](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/mobile/README.md))။

APK distribution သည် Admin only - **Settings → Agent mobile app (APK)**
(`admin.settings.mobile-app.*`) တွင် upload/publish လုပ်ပြီး download route သည် private
disk ပေါ်ရှိ admin-only ဖြစ်သည် (Operator/Agent = 403)။

## 6. VPS သို့ ပို့ရန် tooling

| Script | ဘာလုပ် | သတိစရာ |
| --- | --- | --- |
| [scripts/vps-deploy.sh](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/scripts/vps-deploy.sh) | code update, `composer install --no-dev`, migrate, seed, optimize, vhost, PHP upload ini | vhost step သည် `listen 443` တွေ့လျှင် **ထိមិនပါ** (certbot TLS ထိုဖိုင်ထဲရှိ)။ nginx config reject လျှင် step ရပ်ပြီး ဘာမှမပြောင်း |
| [scripts/vps-bundle.sh](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/scripts/vps-bundle.sh) | `Deploy to WYA VPS.cmd` အတွက် tar.gz bundle (base64 stream သို့မဟုတ် `--tarball`) | Windows helper တွင် rsync/sshpass မရှိ |
| [scripts/vps-inspect.sh](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/scripts/vps-inspect.sh) | read-only server audit (PHP/nginx/MySQL/certbot, routes, vhost, cron, backup) | install/write/restart မလုပ်; `.env` values မပုံနှိပ် |
| [scripts/vps-fix-uploads.sh](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/scripts/vps-fix-uploads.sh) | `upload_max_filesize=130M`, `post_max_size=135M`, `max_execution_time=300` ini ထည့် | 413 ပြဿနာဖြေ - [Troubleshooting](TROUBLESHOOTING_MM.md) |
| [scripts/backup-db.sh](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/scripts/backup-db.sh) | `mysqldump` backup, `[backup-dir]`, `BACKUP_KEEP_DAYS` (14) | VPS cron ထဲ ထည့်ရန် ([VPS_SETUP_MM.md](VPS_SETUP_MM.md) §9) |
| [scripts/go-live.sh](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/scripts/go-live.sh) + tunnel-supervisor.sh | localhost.run SSH tunnel ဖြင့် demo public HTTPS URL | **demo only** - hostname ပြောင်းတိုင်း, idle drop ဖြစ်; production မဟုတ် |

Windows helpers (`release/Windows/` နှင့် Desktop ထဲ `.cmd`) -
`Push Thai2D3D to GitHub.cmd`, `Check WYA VPS.cmd`, `Deploy to WYA VPS.cmd`။ Credential
prompt များကို `.cmd` ထဲသာ ဖတ်ပြီး file ထဲ မရေးပါ။

## 7. GitHub Actions

| Workflow | Trigger | ရလဒ် |
| --- | --- | --- |
| `laravel-ci.yml` | push/PR | `composer install` + `php artisan test` |
| `android-apk.yml` | `mobile/**` push, `master`/`VPS2D3D`, သို့ workflow_dispatch (VPS URL input ပါ) | debug + release APK artifacts |
| `deploy-vps.yml` | push `master` (သို့ dispatch, branch input) | rsync + server ပေါ် migrate |

## 8. Release checklist (ထုတ်ကုန်တစ်ခေါက် မတင်ခင်)

1. `php scripts/sync-release.php all --dry-run` ပြီး ပြောင်းလဲမှုစာရင်း စစ်။
2. `php scripts/sync-release.php all`။
3. `php artisan test` + `php scripts/audit-language-keys.php` (missing/extra = 0)။
4. `bash scripts/smoke-release.sh` → 23/23 PASS။
5. Desktop ပြောင်းလဲမှုပါလျှင် `npm run dist:win` / `dist:linux` ပြီး `release/` ထဲ အစားထိုး။
6. Licence ထုတ်ရန် `desktop/keygen/sign-licence.bat` ကို သုံးပါ
   ([Licence](LICENCE_MM.md))။ Vendor private key
   (`%USERPROFILE%\.wya-licence-key\secret-key.txt`) နှင့် `issued-licences.csv`
   တို့ကို `release/**` ထဲ ဘယ်တော့မှ မကူးရပါ - `release/**/keygen` ထဲ ယခင် pass-key
   tool ကျန်လျှင် ဖျက်ပြီး `desktop/keygen` ၏ `sign-licence.*` ဖိုင်များသာ ထားပါ။
7. VPS အတွက် - [VPS Setup Guide](VPS_SETUP_MM.md) §9 update လမ်းညွှန် အတိုင်း deploy၊
   ထို့နောက် `Check WYA VPS.cmd` run ပြီး `vps-inspect.log` စစ်။
