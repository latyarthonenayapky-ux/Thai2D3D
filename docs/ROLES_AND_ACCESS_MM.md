# Roles နှင့် Access (ဘယ်သူ ဘာဝင်နိုင်သလဲ)

## 1. Role လေးမျိုး

`users.role` သည် string(20), default `operator` ဖြစ်သည်။ အစောပိုင်းတွင် enum
`admin|operator` သာခဲ့ပြီး `2026_10_07_023000_expand_user_roles_for_owner.php` ဖြင့်
string သို့ပြောင်းကာ `owner` ထပ်ထည့်ခဲ့သည်။ Code လက်ခံတန်ဖိုးများ -

| Role | Helper | ဘာလုပ်နိုင်သလဲ |
| --- | --- | --- |
| `operator` | `User::isOperator()` | Sale entry, dashboard, 3D sales, offline sync, reports (ကိုင်တွယ်ခံရ Agent များအတွက်သာ) |
| `admin` | `User::isAdmin()` | Business settings, results, settlement, ledger, reports, users (operator များ), mobile admin area အပြည့် |
| `temp_admin` | `User::isAdmin()` + `isTempAdmin()` | `admin` အတူပင်၊ သို့သော် `expires_at` ရောက်လျှင် အလိုအလျောက် ပိတ် (`users:expire-temp`) |
| `owner` | `User::isOwner()` | Desktop/VPS ၏ ပထမဆုံး account။ Admin/temp_admin အကောင့်များ ဖန်တီးနှင့် စီမံနိုင်။ User management အပေါ်သာ အာဏာရှိ (အောက် §4 ကြည့်) |

`User::isHandler()` = `admin | temp_admin | operator` (Agent session ကိုင်တွယ်သူ အဓိပ္ပာယ်)။

## 2. Middleware alias များ

`bootstrap/app.php` တွင် စာရင်းသွင်းထားသည် -

| Alias | Class | ဘာကို gate သလဲ | Failure |
| --- | --- | --- | --- |
| `active` | `EnsureActiveAccount` | `expires_at` ကျော်ပါက / `status = false` ဖြစ်ပါက | logout + login သို့ redirect (error message) |
| `admin` | `EnsureAdmin` | `isAdmin() || isOwner()` သာ အသွားခွင့် | **403** |
| `agent.token` | `EnsureAgentAccessToken` | Mobile API bearer token | **401 JSON** (token မပါ/ပျက်)၊ **403 JSON** (Agent သည် mobile access မရှိ / inactive) |
| `preferences` | `ApplyUserPreferences` | Locale သတ်မှတ်ခြင်း + idle timeout (`idle_minutes >= 5`) | logout + login redirect |
| (web append) | `EnsureValidLicence` | Desktop copy (desktop mode + SQLite) ၏ licence သည် `valid`/`grace` မဟုတ်လျှင် business ပြောင်းလဲသည့် POST/PUT/PATCH/DELETE များကို ပိတ်သည် (read-only)။ `desktop.licence.store`, backup/restore, business-move, `logout`, `preferences.locale` တို့သည် အမြဲခွင့်ပြု ([app/Http/Middleware/EnsureValidLicence.php:26](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Http/Middleware/EnsureValidLicence.php#L26)) | Business write များ ပိတ်; VPS install တွင် လုံးဝ မတက်ပါ |
| (global) | `SecurityHeaders` | `nosniff`, `X-Frame-Options DENY`, CSP, HSTS (secure request တွင်သာ) | - |
| (inline) | `RecordAdminActivity` | Admin area ၏ POST/PUT/PATCH/DELETE များကို `activity_logs` ထဲ route name + bound model + status ဖြင့် ရေးသည် (body မသိမ်း) | - |

## 3. Route အုပ်စုများ

| အုပ်စု | Middleware | မည်သူ ဝင်နိုင် |
| --- | --- | --- |
| Public / guest | `guest` | login, forgot/reset password, `/desktop/owner-setup` (throttle 6/hr) |
| Operator area | `auth`, `active`, `preferences` | အကောင့် active ဖြစ်သူအားလုံး; controller အတွင်း `isAdmin()`/`isOperator()` ဖြင့် ထပ်စစ် |
| Admin area (`/admin`, name `admin.`) | `admin` + `RecordAdminActivity` | admin/temp_admin/owner (သို့သော် §4) |
| Mobile API (`/api/v1`) | `agent.token` | Agent app သာ (Agent login code + PIN မှ token) |
| Health | - | `/up` (auth မမေး) |

## 4. သိထားရန် အရေးကြီးသော quirk: owner သည် admin မဟုတ်ပါ

`EnsureAdmin` သည် `owner` ကို ခွင့်ပြုသော်လည်း၊ admin controller အများစုသည် **နောက်တစ်ဆင့်**
`$request->user()->isAdmin()` ကိုသာ စစ်သည်။ `isAdmin()` သည် `admin|temp_admin` ကိုသာ
true ပေးပြီး `owner` အတွက် false ဖြစ်သည်။ ထို့ကြောင့် owner ဖြင့် ဝင်လျှင် -

| နေရာ | ရလဒ် | အခိုင်အမာ code |
| --- | --- | --- |
| Business settings (အားလုံး) | 403 | `SettingsController::authorizeBusinessAdmin()` [SettingsController.php:858](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Http/Controllers/Admin/SettingsController.php#L858) |
| Round reopen / close | 403 | `:690`, `:718` |
| Cash ledger | 403 | `AgentLedgerController.php:24`, `:118` |
| Round results / settlement | 403 | `RoundResultController.php:20`, `:103` |
| 3D draw admin | 403 | `ThreeDigitDrawController.php:173` |
| Offline sync review | 403 | `OfflineSyncReviewController.php:17`, `:31`, `:47` |
| Activity log | 403 | `ActivityLogController.php:71` |
| APK publish (mobile package) | 403 | `MobileAppPackageController.php:130` |
| User management | **အလုပ်လုပ်** (owner အတွက် အထူးပွင့်) | `UserController.php:25`, `:46` |

**Navigation သည် ဒီနှစ်gate ကို ထပ်တူ ကူးပြောင်းရမည်**။ `resources/views/layouts/app.blade.php:112`
တွင် menu ကို `isAdmin() || isOwner()` ဖြင့် ပြသည် - ဆိုလိုသည်မှာ owner သည် link များကို
**မြင်ရသော်လည်း နှိပ်လျှင် 403** ဖြစ်နိုင်သည်။ Screen/feature အသစ်ထည့်တိုင်း nav ကို
`layouts/app.blade.php` တွင်၊ gate ကို controller တွင် **နှစ်ခုလုံး** ထပ်တူညီအောင် ချမှတ်ပါ။

## 5. Business isolation (တစ်လုပ်ငန်းနှင့် တစ်လုပ်ငန်း)

အသုံးဝင်သမျှ query များသည် `admin_id = auth()->id()` ဖြင့် ချည်နှောင်ထားသည်။ တခြား
လုပ်ငန်း၏ Agent/Round/session ID ကို URL ထဲ ရိုက်ထည့်လျှင် 404 (403 မဟုတ်) ရသည်။
ဥပမာ - `abort_unless((int) $agent->admin_id === (int) $request->user()->id, 404)`
([SettingsController.php:209](file:///D:/Project/Thai2D3D-clean-20261009/Thai2D3D-clean-20261009/app/Http/Controllers/Admin/SettingsController.php#L209))။
Agent mobile API သည် token မှ Agent ကိုတင် မဟုတ်ဘဲ ၎င်း၏ `admin_id` scope အတွင်းသာ ရသည်
(`AgentMobileBusinessIsolationTest`)။

## 6. Throttle ( brute force ကာကွယ်)

| Endpoint | Limit |
| --- | --- |
| `POST /login` | `throttle:login` |
| `POST /desktop/owner-setup` | 6/min |
| forgot/reset password | 6/min |
| `POST /api/v1/auth/login` | 10/min |
| `POST /api/v1/sales/upload` | 120/min |
| sale store / store-batch | 120/min |
| void / restore | 60/min |
| backup download / restore | သီးသန့် limiter prefix (admin area နှင့် မမျှ) |

## 7. စမ်းသပ်မှုများ

```sh
php artisan test --filter="AuthenticationTest|NavigationTest|TempAdminAccountTest|SecurityHardeningTest|AgentMobileAccessAdminTest"
```

- `SecurityHardeningTest` - headers, throttles, session/injection ကာကွယ်ချက်များ
- `NavigationTest` - role အလိုက် menu အပြောင်းအလဲ (owner quirk ကိုပါ ထမ်းတမ်းပြသည်)
- `AgentMobileBusinessIsolationTest` - အခြားလုပ်ငန်း data မမြင်ရကြောင်း
