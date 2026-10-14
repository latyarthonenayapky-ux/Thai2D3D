# Local Development Guide

## 1. လိုအပ်ချက်များ

| Item | တန်ဖိုး |
| --- | --- |
| PHP | `^8.2` (Laravel 12 လိုအပ်ချက် - [composer.json](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/composer.json)) |
| Composer | 2.x |
| Node.js | 24 (Vite build + desktop tooling) |
| Database | Development တွင် SQLite လုံလောက်; test များသည် `:memory:` SQLite သုံး |
| Flutter | 3.24.5 (Agent mobile app ဘက်သာ) |

**PHP မတပ်ထားသော Windows ကွန်ပျူတာ** - repo ထဲ bundled runtime ရှိသည်:
`desktop/runtime/php-win/php.exe`။ Path ထဲထည့်ရန် မလို၊ ဖိုင်path အပြည့်ဖြင့် ခေါ်ပါ -

```sh
./desktop/runtime/php-win/php.exe artisan test
./desktop/runtime/php-win/php.exe scripts/audit-language-keys.php
```

Linux binary များ `desktop/runtime/php-linux/` တွင် (AppImage bundle အတွက်)။

## 2. စတင်ခြင်း

```sh
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force   # CodeRule W/N/X အပါ system rules
composer run dev              # serve + queue + log + vite (npx concurrently)
```

`composer setup` script သည် အထက်လုပ်ရပ်များကို တစ်ကြိမ်တည်း ဆောင်ရွက်ပေး
([composer.json](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/composer.json) `scripts.setup`)။

### `php -S` ဖြင့် run လိုလျှင် (router script လိုသည်)

```sh
php -S 127.0.0.1:8000 server-router.php
```

[server-router.php](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/server-router.php) သည်
`public/` အောက် real file များကို Laravel မဝင်ခံဘဲ built-in server လက်သို့ ပြန်လွှတ်သည် -
Desktop app နှင့် local smoke test များသုံးသော နည်းအတူတူ။

## 3. `.env` အဓိက keys (တန်ဖိုး မရေးပါနှင့် - Git ထဲ မတင်ပါနှင့်)

`APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `APP_LOCALE`,
`SESSION_SECURE_COOKIE`, `SESSION_LIFETIME`, `DB_CONNECTION`, `AGENT_TOKEN_TTL_DAYS`,
`BCRYPT_ROUNDS`, `LOG_*`, `QUEUE_CONNECTION`, `CACHE_STORE`, `MAIL_*`, `AWS_*`။
VPS အတွက် ထပ်လိုသည် - `LARAVEL_STORAGE_PATH`, `LARAVEL_BOOTSTRAP_PATH`,
`TRUSTED_PROXIES` (proxy မရှိလျှင် comment ထား)။ Details:
[VPS Setup Guide](VPS_SETUP_MM.md) §5။

`.env`, `.sqlite`, `storage/` files များကို Git ထဲ မထည့်ပါ ([.gitignore](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/.gitignore))။
`.env.example` ကိုသာ update လုပ်ပါ။

## 4. Tests

```sh
php artisan test                                  # အားလုံး
php artisan test --filter=AmountLimitEnforcementTest
composer test                                     # config:clear + php artisan test
php scripts/audit-language-keys.php               # en/my key parity
bash scripts/smoke-release.sh                     # release payload checks
```

- Suite config - [phpunit.xml](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/phpunit.xml):
  `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` (force), `BCRYPT_ROUNDS=4`,
  cache/session/mail array, queue sync။ **MySQL လိုအပ်ချက် မရှိပါ**။
- အရေအတွက် (2026-10-14) - Feature 35 files, Unit 4 files, ~278 test methods။
- Area mapping -

| Area | Tests |
| --- | --- |
| Sales / parsing | `SaleInputParserTest`, `DigitIncludesParserTest`, `ThreeDigitInputParserTest`, `SaleProcessorTest`, `SaleBatchStoreTest`, `ThreeDigitSalesTest`, `IndependentThreeDigitDrawTest` |
| Amount limits | `AmountLimitEnforcementTest`, `AdminSettingsTest` |
| Settlement / reports | `RoundSettlementTest`, `PeriodSummaryReportTest`, `AdminSaleEntryAggregationTest` |
| Void / offline / mobile | `SaleVoidTest`, `OfflineSaleSyncTest`, `AgentMobileApiTest`, `AgentMobileAccessAdminTest`, `AgentMobileBusinessIsolationTest`, `MobileAppPackageTest` |
| Cash ledger | `AgentCashLedgerTest` |
| Auth / roles / security | `AuthenticationTest`, `NavigationTest`, `TempAdminAccountTest`, `SecurityHardeningTest`, `UserPreferenceTest`, `BurmeseLocaleUiTest` |
| Licence / desktop | `LicenceSignatureTest`, `LicenceEnforcementTest`, `DesktopOwnerSetupTest` |
| Data move / backup | `DataBackupTest`, `DatabaseBackupRestoreTest`, `BusinessDataMoveTest` |
| Admin UX | `AdminFirstRunChecklistTest`, `AdminAgentSalesViewTest`, `ActivityLogTest` |

- Test အသစ်ရေးလျှင် `RefreshDatabase` သုံးပါ၊ Factory များ (`database/factories/`) ရှိသည်။
- Burmese UI ကို ထိခိုက်လျှင် `en` strings များကို byte-for-byte မပြောင်းရ -
  [Localization](LOCALIZATION_MM.md)။

## 5. Code layout

```
app/Http/Controllers/{Admin,Operator,Api/V1,Auth}   screens + endpoints
app/Http/Middleware                                  roles, token, preferences, headers
app/Services                                          business logic (see Business Logic doc)
app/Models                                            Eloquent entities
app/Console/Commands                                  owner creation, sync, backup, export/import
resources/views                                       Blade UI (layouts/app.blade.php = nav)
lang/{en,my}/app.php                                   translations
routes/{web,api,console}.php                          routes + schedule
database/{migrations,seeders,factories}
desktop/                                              Electron shell + bundled PHP + licence signer
mobile/                                               Flutter Agent app
scripts/                                              deploy/sync/smoke/audit tools (bash + PHP)
deploy/nginx/                                         vhost template
release/{Windows,Linux,VPS}                           packaged outputs
tests/{Feature,Unit}
```

## 6. Static analysis / style

`laravel/pint` သည် dev dependency ထဲ ရှိသည် -

```sh
vendor/bin/pint --test      # check
vendor/bin/pint             # fix
```

CI တွင် Laravel workflow ([.github/workflows/laravel-ci.yml](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/.github/workflows/laravel-ci.yml)) သည်
`composer install` + `php artisan test` ကို run လုပ်သည်။

## 7. Git workflow သတ်မှတ်ချက်များ

- Working branch - `VPS2D3D` (main branch မှာ `master`)။
- Commit/push သည် user တောင်းမှ လုပ်ပါ (auto-push မလုပ်)။
- Script/shell ဖိုင်အသစ်များကို stage လုပ်ရာတွင် exec bit ထည့်ပါ -
  `git add --chmod=+x scripts/foo.sh` (Windows NTFS worktree သည် exec bit ကွာသည်)။
- `.log` ဖိုင်များ (`push.log`, `vps-deploy.log`, `vps-inspect.log`) သည် Git ထဲ မဝင်။

## 8. Local smoke check list (ပြင်ဆင်မှုတစ်ခု ပြီးဆုံးပြီဆိုရန်)

```sh
php artisan config:clear
php artisan test
php scripts/audit-language-keys.php
bash scripts/smoke-release.sh
./desktop/runtime/php-win/php.exe -S 127.0.0.1:8000 server-router.php   # then browse /up, /login
```

`/up` သည် Laravel health route ဖြစ်သည် (auth မမေး) - server တကယ် run နေကြောင်း
စစ်ရန် အမြန်ဆုံးနည်း။
