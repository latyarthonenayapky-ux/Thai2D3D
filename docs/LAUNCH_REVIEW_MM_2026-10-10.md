# Thai2D3D Launch Review — 2026-10-10

စစ်ဆေးသည့် folder: `/home/waiyanaung/Projects/Thai2D3D-clean-20261009`
စစ်ဆေးသူ: Qoder (read-only inspection + packaged-app smoke test)
ပြင်ဆင်ထားသည့် ဖိုင်: မရှိ (စစ်ဆေးမှုသာ၊ code မပြင်ပါ)

## 0. အနှစ်ချုပ်

| Surface | အခြေအနေ | အဓိက အခက်အခဲ |
| --- | --- | --- |
| Linux `.AppImage` (1.1.0) | **သုံးလို့ရသည် (verified)** — bundle ထဲက PHP+Laravel ကို actually boot ပြီး owner account ဖွင့်သည်အထိ စမ်းအောင်ထွက် | Source က artifact ထက် 1 feature ရှေ့နေသည် (manual reopen) — rebuild သောက်ရန် |
| Windows `.exe` (1.1.0) | **အခက်အခဲ မြင့်** — bundled `php.ini` ထဲ `openssl` ဖွင့်မထား (Windows PHP တွင် dynamic extension) | Real Windows machine ပေါ် run test လုံးဝမပြုလုပ်ရသေး |
| Online live web app | **ယခု live မဟုတ်** — tunnel URL 502, server မដৌက်, cron/go-live script တွက ယခင် folder ကို ပြနေ | VPS deploy + HTTPS + SMTP + backup ကို တစ်ကြိမ်မှ အတည်မပြုရသေး |
| Code quality | Test **119 pass / 715 assertions (OK)** | Local PHP တွင် `pdo_sqlite` မပါသဖြင့် `artisan test` မှားယွင်းစွာ fail ဖြစ်နေ |
| Multi-tenant security | Tenant isolation ရanks ကောင်း (IDOR မတွေ့) | Rate limit/headers/audit/backup/2FA က missing |

---

## 1. Desktop artifacts — တိုင်းတာပြီးသား အဖြေများ

### 1.1 Artifact အတည်ပြုချက်
- Root ရှိ `Thai2D3D-1.1.0.AppImage` (186 MB) နှင့် `desktop/dist/standalone-linux/...AppImage` — md5 **တူညီ** (`dc939fde…`)။
- Root ရှိ `Thai2D3D-1.1.0-Windows.exe` (189 MB) နှင့် `desktop/dist/standalone-win/Thai2D3D 1.1.0.exe` — md5 **တူညီ** (`2a15538a…`)။ `file` အရ Nullsoft portable self-extracting PE32+ GUI။
- နှစ်ခုစလုံး 2026-10-10 04:22 တွက build ဖြစ်သည်။

### 1.2 Linux AppImage — functional smoke test (အောင်သည်)
AppImage ကို extract ပြီး `desktop/main.js` လုပ်သည့် အတိုင်း ပြန်လုပ်၍ စမ်းသည် (GUI window မပါ၊ PHP/Laravel layer)။
- Bundled PHP `8.3.6 NTS` + `pdo_sqlite`, `sqlite3`, `mbstring`, `curl`, `dom`, `xml`, `fileinfo` load ဖြစ် (`resources/php/ext/*.so` 13 လုံး)။
- `artisan thai2d3d:desktop-initialize` → seed အဆင်ပြေ၊ `DESKTOP_OWNER_SETUP_REQUIRED=1` ပြန်ပေးသည်။
- `php -S` တင်ပြီး `/up` 200, `/` 302, `/login` 200, `/desktop/owner-setup` 200။
- Owner setup form ကို POST → **302 → /dashboard**၊ SQLite ထဲ **31 tables / 26 migrations / users=1**။
- ဆိုလိုသည်မှာ AppImage ထဲက app သည် စစ်မှန်စွာ boot ဖြစ်ပြီး owner-onboarding flow အဆုံးစေသည်။ (`README.md` ၏ "tested Linux package" ဟူသော အဆိုကို အတည်ပြုသည်။)

### 1.3 Artifact ကို source နှင့် နှိုင်းယှဉ်လျှင် **နောက်ကျနေသည်**
Bundle ထဲက Laravel copy နှင့် working tree ကို diff ယူလျှင် 3 ဖိုင် ကွာသည် (win/linux နှစ်ခုလုံး)။
- `app/Http/Controllers/Admin/SettingsController.php` — tree တွင် `manual_reopen=true` ဖြစ်သည့် Round များကို 7-day window ပြင်ပတွင်ပါ စာရင်းပြန်ပြသည်; bundle တွင် မပါ။
- `resources/views/admin/settings/index.blade.php` — "Manually reopened" label နှင့် **"Reclose reopened Round"** button (3 နေရာ) bundle တွင် မပါ။
- `resources/views/manual/index.blade.php:106` — reopen/reclose ရှင်းလင်းချက် bundle တွင် အဟောင်းစာသား။

**သက်ရောက်မှု**: 1 ပတ်ကျော်ပြီးသား reopen လုပ်ထားသည့် Round တစ်ခုကို AppImage/.exe သုံးသူသည် Settings ရှိ စာရင်းတွင် မမြင်ရတော့ဘဲ ပြန်ပိတ်လို့လည်း မရတော့ပါ (DB ထဲ open state အတင်းကျန်)။ **Rebuild တစ်ကြိမ် လုပ်သင့်** (`cd desktop && npm run dist`)။

### 1.4 Windows `.exe` — အန္တရာယ် အကြီးဆုံးအချက်
- `desktop/stage/php/php.ini` (bundle ထဲ ၀င်သည့် file: `win-unpacked/resources/php/php.ini`) ဖွင့်ထားသည်များ: `mbstring, fileinfo, tokenizer, dom, xml, xmlreader, xmlwriter, curl, pdo_sqlite, sqlite3` — **`openssl` မပါ**။
- Linux ဇကာတွင် openssl သည် PHP binary ထဲ static ပါနေသဖြင့် Linux build တွင် ပြဿနာ မတက်။ Windows official build တွင် `php_openssl.dll` သာ.dynamic — တိုင်းတာအတည်ပြုချက်: `strings php.exe | grep -c openssl_encrypt` = **0**, `strings ext/php_openssl.dll` = **2**၊ `php.ini-development:952` တွင် `;extension=openssl` (dynamic)။
- Laravel 12 ၏ `web` middleware group သည် `EncryptCookies` ကို သုံးပြီး session/CSRF cookie များကို `openssl_encrypt` ဖြင့် encrypt သည် → **Windows တွင် ပထမ page load ၌ "Call to undefined function openssl_encrypt" (HTTP 500) ဖြစ်နိုင်ခြေ အလွန်မြင့်**။
- အခြား Windows သက်သေ: wine ဖြင့် `php.exe -v` စမ်းရာ `'C:\windows\system32\VCRUNTIME140.dll' 2.41 is not compatible with this PHP build linked with 14.29` — README ပြောသည့် **VC++ 2015–2022 x64 Redistributable မလို** ကိစ္စကို အတည်ပြုသည် (wine ၏ runtime နာကျင်မှုသာ)။
- `tokenizer/dom/xml/xmlwriter` တို့သည် Windows build ၌ static ဖြစ်နိုင်သဖြင့် `extension=` ကိန်းများ warning သာဖြစ်စေနိုင် — `display_errors=Off, log_errors=On` ဖြစ်သည့်အတွက် ဖတ်ရန်ခက်။
- **Code-signing မပါ** → SmartScreen "More info → Run anyway" လိုအပ် (README ဖော်ပြပြီး)။
- **အရေးကြီးဆုံး**: `.exe` ကို Windows machine တစ်ခုတှင် actual run ပြီး (1) login, (2) owner setup, (3) sale entry တစ်ခုပ်, (4) report ဖွင့်ကြည့်ခြင်း စမ်းဆဲ မဟုတ်သေး။ Linux မှာ အောင်ခဲ့တဲ့ test နှင့် Windows မှာ အောင်မည်ဟု ယူဆ၍ မရ။

### 1.5 Desktop data model / backup အကြောင်း
- Data location: `~/.config/Thai2D3D/` (Linux), `%APPDATA%\Thai2D3D\` (Windows) — SQLite + storage + logs + `app-key` (mode 0600)။ **Desktop-to-server sync လုံးဝမပါ**။
- Backup သည် "app ပိတ်ပြီး folder ကို copy" လက်ရှိနည်းသာ — in-app backup/restore button မရှိ။ ၀န်ထုပ်၀န်တက် Admin တစ်ယောက်အတွက် ဒါသည် အန္တရာယ် အချက်။
- `app-key` ပျက်လျှင် encrypted items ဖတ်၍ မရနိုင် — backup list ထဲ ထည့်ရန်။

---

## 2. Online live web app — ယခု အခြေအနေ

### 2.1 Live မဟုတ်ကြောင်း သက်သေ
- `.env` ၏ `APP_URL=https://21b600e1fa3a76.lhr.life` → `curl` = **HTTP 502** (localhost.run hostname သည် reconnect တိုင်း ပြောင်း — memory ဖြင့် ကိုက်ညီ)။
- Port 8000 listening မရှိ၊ `artisan serve`/ssh tunnel process မရှိ။
- Cron: `* * * * * cd /home/waiyanaung/Projects/Thai2D3D && php artisan schedule:run` — **ယခင် folder** ကို ညွှန်နေသည်။ သစ် folder ၏ scheduler (`rounds:sync-schedule`, `users:expire-temp`) ယခု **မရောင်း**။
- `scripts/go-live.sh` တွင် `PROJECT_DIR="/home/waiyanaung/Projects/Thai2D3D"` hardcoded — သစ် folder အတွက် ပြင်ရန်။
- ဆိုလိုသည်မှာ "online live webapp အသုံးပြုနိုင်မှု" သည် **ယခုအချိန်တွင် 0%** (ကြိုတင် config ပြင်ဆင်ပြီး VPS တင်မှ သုံးနိုင်)။

### 2.2 Local dev DB အခြေအနေ
- MariaDB 10.11 `thai2d3d` (30 tables) ချိတ်ဆက်ရသည်; demo data: users=3, agents=2, rounds=27, sale_inputs=4, sale_details=46, three_digit_sale_details=0, round_results=0။
- `migrate:status`: `2026_10_09_000400_add_round_number_limits_and_manual_close` = **Pending** → code သည် DB schema ထက် ရှေ့နေပြီး၊ ဤ machine တွင် web app ကို MySQL နှင့်တင်လျှင် column မရှိ၍ error တက်မည် (`php artisan migrate --force` လိုအပ်)။
- `public/storage` symlink သည် `/home/waiyanaung/Projects/Thai2D3D/storage/app/public` (ယခင် folder) သို့ သွားနေ — deploy မတင်မီ ဖျက်ရန်/ပြန်လုပ်ရန်။

### 2.3 ပရောဂျက် အရည်အသွေး လက္ခဏာ
- `php artisan test` (system PHP) = 112 fail — သို့သော် အားလုံး `could not find driver (sqlite)`၊ env ပြဿနာ။ Bundled PHP ဖြင့် ပြန်ရန်လျှင် **OK (119 tests, 715 assertions)**။ CI/deploy မတင်မီ `php8.3-sqlite3` install လုပ်ထားရန် (သို့) test suite ကို MySQL fixture ဖြင့် ပြောင်းရန်။
- Deploys: `docs/production-deployment.md` (English checklist) + `docs/VPS_SETUP_MM.md` (357 lines, Burmese) + `docs/USER_MANUAL_MM.md` (265 lines) ရှိပြီး route/app နှင့် ကိုက်ညီမှု ရှိ (UX စစ်သူ အတည်ပြု)။
- Stack: Laravel 12 + Blade (Livewire/Breeze/Sanctum မပါ), no billing/subscription package → **paid model သည် Owner လက်ကိုင် Admin account ဖွင့်ပြီး ကောက်သည့် ပုံစံ** ဖြစ်နေမည်။

### 2.4 Paid web app အတွက် architectural gaps
- Tenant plan/trial mechanism: `users.expires_at` + `users:expire-temp` (hourly, Admin expire လျှင် အောက် Operator များ auto deactivate) — **subscription cutoff အတွက် အခြေခံ ရှိပြီး**၊ သို့သော် per-tenant plan/pricing/invoice မရှိ။
- Registration ပိတ်ထား (ကောင်း), Owner create is CLI/desktop-only.
- ၀န်ဆောင်မှုချင်း သီးခြားဖြစ်မှု (Admin isolation) ကောင်း၊ ပြင်ပ billing integration မလိုက်ပါ။

---

## 3. အသုံးပြုရလွယ်ကူမှု (Ease of use) — ထူးခြားအားသာချက်

- **Draft-then-commit sale entry**: Enter နှိပ်၍ line များကို localStorage queue ထဲ စု၊ Save (F1) တစ်ကြိမ်မှာပင် server သို့ → `resources/views/operator/sales/show.blade.php:156-172, 541-609`
- **Keyboard-first**: F1 save / F2 agent / F3 input / F8 search (`show.blade.php:627-642`); numpad `.`→`000`, `*`→`R` normalization + unit test (`public/js/sale-entry-keyboard.js`, `desktop/test/sale-entry-keyboard.test.js`)
- **Per-line rejection feedback**: accepted/rejected/excluded chips + reason (`show.blade.php:336-339`; reasons `app/Services/SaleProcessor.php:289, 304`)
- **Report queries set-based, N+1 မရှိ** (`app/Services/PeriodSummaryReport.php:36-99`, `RoundSettlementCalculator.php:32-61`), dashboard eager-load (`Operator/DashboardController.php:111-135`)
- Theme (dark/system) + font scale 4 steps persisted, Yangon countdown နီးလျှင် yellow/orange/red (`layouts/app.blade.php:112-128, 189-237`)
- Empty state + CTA ("No open Rounds → Configure Round schedule") နေရာနေရာတွင် ရှိ
- `/manual` Burmese manual တွင် input code (A/B/R/F/P/W/N/X/Q, `1အပါ1000`), F-keys, offline caveat အကုန်စာရင်းပြုထားပြီး routes နှင့် ကိုက်

## 4. လက်တွေ့ အခက်အခဲများ (Impact အလိုက်)

1. **HIGH — Bulk paste မရ**。Input တွင် `maxlength="255"` single line သာ (`show.blade.php:159`, `three-digit.blade.php:108`)၊ parser သည် whitespace/newline ပါလျှင် ငြင်း (`app/Services/SaleInputParser.php:73-78`)။ Excel မှ ကူးထည့်လိုသည့် Operator အတွက် အခက်ဆုံး။
2. **HIGH — Save သည် fetch loop (逐 line HTTP)** (`show.blade.php:567-605`, `three-digit.blade.php:240-257`) — line 50 ခုဆိုလျှင် request 50 ကြိမ်၊ 4G ပေါ် မတည်ငြိမ်။ Batch endpoint (OfflineSaleSyncController ပုံစံ) သုံး၍ ပြင်နိုင်။
3. **HIGH — Keyboard/numpad normalization ကို desktop mode တွင်သာ ဖွင့်** (`show.blade.php:164, 350` + `config/app.php:30 THAI2D3D_DESKTOP`) → VPS/phone သုံး Operator များသည် `*`/`.` ပြောင်းလဲမှု မရ၊ lowercase လွှတ်မိ၍ reject ဖြစ်။ ဒါက online-first paid deployment အတွက် အကြီးဆုံး usability bug။
4. **HIGH — Offline page သို့ link လုံးဝမရှိ**。Route `/operator/sessions/{id}/offline` ရှိသော်လည်း (routes/web.php:108-111) view များထဲ ရည်ညွှန်းချက် 0 (grep အဖြေ ကွာလျှင်) → Operator ရှာမတွေ့။
5. **MED — Dashboard 15s full-page reload** (`dashboard.blade.php:31-39`) + ထပ်တူ 30s timer တစ်ခု ပိုရှိ (`:148-156`) → phone တွင် scroll/state ပျက်။
6. **MED — ကျန် limit မပြ**。Number limit သည် submit ပြီးမှ fail၊ Amount Limit monitoring only (`admin/settings/index.blade.php:204-205`) — "X of Y used" chip လိုအပ်။
7. **LOW — English labels ရောထွေး**: "Live of items sold", Number Limit (blocked values) vs Amount Limit (threshold) တို့ ခြားနားချက် မရှင်း။

## 5. စာရင်းဇယား ကြည့်ရှုမှု (Reports) — အဆင့် သတ်မှတ်ချက်

**အဆင့်: သုံးလို့ရသည်၊ သွက်လက်မှု အလယ်အလတ် (usable, needs work)**

ကောင်းနေပြီး thing:
- Period daily/weekly/monthly/yearly + date picker + Agent + Round filter + 2D/3D toggle (`admin/reports/summary.blade.php:27-60`, `admin/reports/three-digit.blade.php:30-51`)
- Totals `number_format` (separators ရှိ), tables `.table-wrap` ဖြင့် scroll (`layouts/app.blade.php:26`), row တိုင်း settlement link (`summary.blade.php:140`)
- Per-Agent settlement + result correction audit trail (`admin/rounds/settlement.blade.php:60-82`)

လိုနေသေး thing:
- **CSV / print / export လုံးဝမရှိ** (grep 0) — Admin တွေ့အများဆုံး တောင်းဆိုချက်။
- **Pagination မရှိ** — yearly "Agent·day·Round details" သည် 1000+ rows × 11 columns တစ်မျက်နှာတည်း (`summary.blade.php:120-152`)။
- **Currency unit label မပါ** (MMK/Kes သတ်မှတ်ချက် မပြ)။
- Request တိုင်း heavy aggregate 2 ခု ပြန်တွက် (cache မသုံး) — SQLite desktop တွင် ပြဿနာမရှိ၊ VPS MySQL တွင် Admin ရာခိုင်မြောက်လာလျှင် ဖြေးလာမည်။
- Phone: 3D report သည် 760px တွင် collapse ဖြစ်သော်လည်း (`three-digit.blade.php:17`) 2D summary က 7-column raw horizontal scroll သာ။

## 6. Onboarding sequence (ယခု code အရ)

1. Owner ဖွင့်: desktop → `/desktop/owner-setup`; VPS → `php artisan thai2d3d:make-owner`
2. Admin login → `/draw-mode` → Settings: **Round schedule သိမ်းရန်** — သို့သော် Round များပေါ်ရန် `rounds:sync-schedule` cron တစ်မိနစ်တိုင်း လို (`routes/console.php:11-14`)။ **UI ထဲ cron အကြောင်း ဘယ်မှာမှ မရေး** — VPS တင်ချင်း "Upcoming Rounds" အလွတ်ဖြစ်၍ ရှင်းလင်းချက် မဆိး (`admin/settings/index.blade.php:70-71`)
3. 2D payout multiplier (`:56-64`)၊ 3D draw settings
4. Agent + commission (`:168-176`), Amount/Number limits, Hot numbers, W/N/X
5. Operator account (12-char password, လက်ကိုင်ပို့) — registration ပိတ် (`auth/login.blade.php:23`)
6. Operator: dashboard → Agent claim → Sale entry
-> **ပထမ 30 မိနစ် checklist card မရှိ** (schedule → payout → agents → operators order ကို UI မှာ ဘယ်နေရာမှ မပြ)။

---

## 7. Paid launch အတွက် လုံခြုံရေး checklist (စစ်ပြီးသား)

### 7.1 ကောင်းနေပြီး (verify လုပ်ပြီး)
- **Tenant isolation**: `{round}`, `{agent}`, `{draw}`, `{hotNumber}`, `{codeRule}`, `{offlineSyncReview}`, `{user}`, `{agentSession}` bound models အားလုံးကို controller တွင် `admin_id`/owner re-check လုပ် (`Admin/SettingsController.php:188,219,243,385,467,646`; `RoundResultController.php:101`; `ThreeDigitDrawController.php:96,176`; `OfflineSyncReviewController.php:48` + `Services/OfflineSaleSyncService.php:135,209`; `Admin/UserController.php:101`)။ Operator side: `SaleEntryController.php:314-336`, `AgentSessionController.php:53-57`, `ThreeDigitSaleController.php:169-174`။ **IDOR တစ်ခုမှ မတွေ့**။
- XSS: `resources/views` ထဲ `{!! !!}` **0**။ SQL injection: interpolated raw SQL **0** (bindings သုံး)။
- Mass assignment: models အားလုံး `$fillable`; `$request->all()` ကို create() ထဲ မပို့။
- Auth hygiene: bcrypt + `Password::min(12)` အားလုံး path; login တွင် `session()->regenerate()`; logout/invalidate တွင် `invalidate()+regenerateToken()`; reset token 60min single-use + remember token rotate; session cookie `http_only=true`, `same_site=lax`။
- CSRF token middleware + `@csrf` ပုံမှန်အတိုင်း active.
- Registration route မရှိ (lockdown ကောင်း)။
- `/desktop/owner-setup` သည် `config('app.desktop_mode') && REMOTE_ADDR ∈ {127.0.0.1,::1}` လိုအပ် (`DesktopOwnerSetupController.php:55-62`) → VPS တွင် default ပိတ်။
- Offline sync payload ကို server ဘက်မှ ပြန် validate + per-record authorize (`Operator/OfflineSaleSyncController.php:15-22`)။

### 7.2 Paid launch မတင်မီ **မဖြစ်မနေ** ထည့်ရန် (Owner အဖို့ mandatory)
1. **`trustProxies(at: '*')` ကို တံဆိပ်ခတ်ရန်** (`bootstrap/app.php:21`)။ ယခုအတိုင်းဆိုလျှင် `X-Forwarded-For` ဖြင့် IP ကို လုပ်ဘတ်နိုင်ပြီး login throttle (`AppServiceProvider.php:28-34` — `email|ip` key) နှင့် audit-by-IP အားလုံး ပျက်။ VPS/nginx + proxy CIDR (127.0.0.1) သာ trust လုပ်ပါ။
2. **Security headers မလို** — `Strict-Transport-Security`, `Content-Security-Policy`, `X-Frame-Options`/`frame-ancestors`, `X-Content-Type-Options` (grep app/config/bootstrap = 0)။ HSTS မဖွင့်သေး; `SESSION_SECURE_COOKIE` သည် `.env` တွင် unset (`config/session.php:172`)။
3. **Rate limiting ပြန့်ပြူးမှု မရှိ**: `operator.sales.store` (routes/web.php:112-115) throttle မပါ; admin POST/PUT/PATCH/DELETE အားလုံး (117-235) မပါ (`users.store` သာ 10,1)။ ငွေပိုင်း touch တဲ့ write များကို per-user/per-admin limiter တပ်ရန်။
4. **Audit/auth logging သုည**: `app/` ထဲ `Log::` **0**။ Failed login, role/status ပြောင်း (`UserController.php:99-116`), round reopen (`SettingsController.php:473-499`) ဘယ်မှတ်တမ်းမှ မရှိ — lottery settlement ပြောင်းတဲ့ action တွေအတွက် ဒါသည် business risk။ `activity_log` table + Login/Logout event listeners လို။
5. **Backup automation မရှိ**: `scripts/` ထဲ tunnel script 2 ခုသာ။ MySQL `mysqldump` cron + **restore drill** သက်သေ၊ desktop အတွက် in-app export။ Checklist ထဲ "backups can be restored" သီးသန့် စမ်းရန် ဆိုထားပေမယ့် လက်ရှိ tooling မရှိ။
6. **2FA လုံးဝမပါ** (Owner/Admin)။ ပိုက်ဆံယူသည့် system တွင် Owner account သည် အန္တရာယ် အကြီးဆုံး asset — TOTP (yubipass/fortify) မဟုတ်လျှင်သော် login notify + IP allowlist တပ်ပါ။
7. **DB user privileges**: `docs/VPS_SETUP_MM.md:111` `GRANT ALL PRIVILEGES` → `SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,LOCK TABLES` သာ ပေးရန်။
8. **Secrets hygiene**: `.env` is gitignored (ကောင်း), but `.env` ထဲ `DB_PASSWORD` အစစ် ရှိနေ → committed မဟုတ်သော်လည်း share folder မှာ မထားရ။ `php-8.3.3-...zip`, AppImage/.exe တို့ git ထဲ မပါ (ကောင်း)။ **သို့သော် git working tree တွင် commit အားလုံး ပျောက်နေ** — `git log` သည် `2a47a9a Initial Laravel Setup` တစ်ခုတည်း၊ 90 files staged + 24 untracked. ပထမဆုံး risk: လက်ရှိ အလုပ်အားလုံး backup မရှိ။ Commit/push ချက်ချင်း လုပ်ရန် (artifact binaries တွေကတော့ `.gitignore` ထဲ ထည့်).
9. **Password edge cases**: `UserController.php:48` rule တွင် `max:` မပါ → 72-byte ကျော်လျှင် bcrypt truncate; `min:12` သာမက `max:255` ထည့်ပါ။ Expired temp Admin သည် login `Auth::attempt` (`AuthenticatedSessionController.php:26-33` — `status` သာ check) အောင်နိုင်ပြီး နောက် request တွင်သာ ထုတ်ခံရ (`EnsureActiveAccount.php:14-21`) → attempt criteria ထဲ `expires_at` ထည့်ပါ။
10. **Fail-closed owner setup**: `THAI2D3D_DESKTOP=true` ကို VPS/tunnel origin (peer 127.0.0.1) တွင် မှားဖွင့်မိလျှင် owner ဖွင့်ခွင့် ပွင့်သွား → `DB_CONNECTION=sqlite` ကိုပါ condition တွင် ထည့်ပါ။

### 7.3 Paid model အတွက် အကြံ (business/security)
- ယခု Owner လက်ကိုင် Admin ဖွင့်သည့်ပုံစံကို **trial/expiry** ဖြင့် ချဲ့နိုင်: `users.expires_at` + cascade (`ExpireTempAccounts.php`) ရှိပြီး။ Plan/pricing table သာ ထပ်တည်ရန်။
- Admin တစ်ယောက်ချင်းစီ၏ `admin_business_settings` isolation ကောင်းသဖြင့် multi-tenant billing အခြေခံ ရှိ။
- SMTP မလိုအပ်သေလာက် ဖော်ပြချက် လို: `MAIL_MAILER=log` ဆိုလျှင် password-reset link email မပို့ရ၊ Admin ကို "share the password through a secure channel" path ဖြင့်သာ သွားမည် (`UserController.php:78-96`) — paid launch တွင် real SMTP (or in-app first-login password set) ရွေးရန်။

---

## 8. ဤ review တွင် **မစမ်းရသေး** သည့် အချက်များ
- Electron GUI launch (Linux/Windows) — X display မရှိ၊ wine တွင် VCRUNTIME140 ABI မကိုက်။ PHP/Laravel layer သာ အတည်ပြုပြီး။
- Windows `.exe` ၏ end-to-end first run (openssl အကြောင်း Static analysis သက်သေဖြင့် ခန့်မှန်းထားသည်၊ run test မဟုတ်)။
- Offline IndexedDB queue → server sync round-trip ကို browser တွင် လက်တွေ့ မစမ်း။
- Real VPS (nginx + HTTPS + cron + MySQL backup restore) မတင်ရသေး။
- `docs/USER_MANUAL_MM.md` ၏ Burmese screenshot/UI naming တွေကို screen တိုင်းနှင့် တစ်ခုချင်း မတိုဆေး (manual ထဲက routes ညွှန်းချက်များ ကိုက်ညီမှု ရှိဟု UX agent သုံးသပ်)။

## 9. အလျင်အမြန် လုပ်သင့်သည်များ (အစဉ်လိုက်)
1. `desktop/stage/php/php.ini` + `desktop/runtime/php-win/php.ini` ထဲ `extension=openssl` ထည့်ပြီး `.exe` rebuild → Windows machine တစ်ခုတွင် first-run + login + sale entry + report စမ်း။
2. `cd desktop && npm run dist` — လက်ရှိ 3-file source drift ကို ဖျောက် (reclose/reopen feature)။
3. `git add` + commit/push (worklog md, binaries, `.history` ကို gitignore ထည့်)။
4. Sale entry keyboard script ၏ `config('app.desktop_mode')` gate ဖျက် (phone users) + Sale Entry header ထဲ Offline page link ထည့်။
5. `trustProxies(at: '127.0.0.1')` ပြောင်း + security-headers middleware (HSTS/CSP/X-Frame) + `SESSION_SECURE_COOKIE=true` in `.env.example`။
6. `operator.sales.store` နှင့် admin writes များတွင် throttle; `activity_log` + auth event logging; `mysqldump` cron script + restore test။
7. Reports တွင် CSV export + pagination + currency label; dashboard reload ကို fetch-poll ပြောင်း၊ duplicate timer ဖျက်။
8. Admin dashboard ထဲ first-run checklist card (schedule → payout → agents → operators → cron note)။
9. VPS deploy ကို `docs/production-deployment.md` အတိုင်း တစ်ကြိမ်အမှန်တင်ပြီး go-live checks 9 ခုအပြည့် စမ်း (mobile offline flow အပါအ ၀ င်)။
