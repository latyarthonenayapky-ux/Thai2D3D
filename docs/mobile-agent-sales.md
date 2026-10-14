# Agent မိုဘိုင်း sale (အလိုအလျောက် တင်ပို့ခြင်း)

Agent များသည် `mobile/` အောက်ရှိ Android app တွင် 2D/3D sale များကို
ရိုက်ထည့်သည်။ Entry တစ်ခုစီသည် သိမ်းလိုက်သည့် အခိုက်အတန့်တွင် office VPS သို့
တင်ပို့သည်; signal မရှိဘဲ ရိုက်ထည့်ထားသော entry များသည် ဖုန်းအတွင်း queue တွင်
နေပြီး connection ပြန်ရလျှင် အလိုအလျောက် တင်ပို့သည်။ Office webapp, dashboard
နှင့် offline-review screen တို့တွင် ဘာမှ ပြင်စရာမလိုဘဲ ထို sale များကို မြင်နိုင်သည်။

```
Agent phone (Flutter)  --HTTPS-->  VPS Laravel  /api/v1/*  -->  sale_inputs / sale_details
                                            \--> offline_sync_reviews (conflicts)
```

## 1. အသုံးပြုရန် အချက်အလက်များ (Credentials)

Agent များတွင် web login မရှိပါ။ Mobile credentials ကို office မှ ချထားပေးသည်။

**Settings → Agents → Mobile app access** (`admin/settings` page)

| လုပ်ဆောင်ချက် | Route |
| --- | --- |
| Credentials ကို အလိုအလျောက် ထုတ်ပေးခြင်း | `POST /admin/settings/agents/{agent}/mobile-access/issue` |
| အခွင့်မရှိသော Agent အားလုံးအတွက် ထုတ်ပေးခြင်း | `POST /admin/settings/agents/mobile-access/issue-all` |
| Login code + password ကို လက်လုပ် သတ်မှတ်ခြင်း | `PATCH /admin/settings/agents/{agent}/mobile-access` |
| ဖုန်းအားလုံး logout လုပ်ခြင်း | `DELETE /admin/settings/agents/{agent}/mobile-access/devices` |
| Mobile access ကို ပိတ်ခြင်း | `DELETE /admin/settings/agents/{agent}/mobile-access` |

**Issue credentials** သည် ဖုန်းတစ်လုံးကို Agent လက်ထဲပေးသည့် အခါ ပုံမှန်လမ်းဖြစ်သည်။
Login code သည် `AGT-0001` ပုံစံဖြစ်ပြီး (Agent id ကို သုညပြည့်အောင် ရေးထားသည်;
ထို code တစ်နေရာရာတွင် အသုံးပြုပြီးဖြစ်လျှင် suffix ထပ်ထည့်သည်) ကျပန်း
ခြောက်လုံးပါတဲ့ PIN ကို generate လုပ်ပေးသည်။
Settings page သည် တစ်ကြိမ်တည်း ပြသသည့် handover card ကို ပြသည် —
`APP_URL` မှ sign-in URL, login code နှင့် PIN တို့ကို copy လုပ်နိုင်သော
text block ထဲတွင်။ Flash data သည် ထို page တစ်ကြိမ်တင်ပြီးနောက် ပျောက်သွားသဖြင့်
PIN ကို နောက်တစ်ကြိမ် ပြန်ဖတ်၍ မရတော့ပါ — မေ့သွားလျှင် ပြန်ထုတ်ပေး၍
အစားထိုးပါ။ ပြန်ထုတ်ပေးလျှင် Agent သိထားပြီးသော login code ကို ဆက်ထားပြီး
PIN ကိုသာ ပြောင်းပေးသည်; ၎င်းသည် ဖုန်းအားလုံးကို logout ဖြစ်စေသည်။

`Issue for every Agent without access` သည် business အတွင်း **active** ဖြစ်ပြီး
code သို့မဟုတ် password မရှိသော Agent အားလုံးအတွက် အတူတူလုပ်ပေးသည်။ Credentials
ရှိပြီးသား Agent များကို ကျော်သွားသည် (၎င်းတို့၏ ဖုန်းများသည် login ဆက်ဝင်ထားသည်)၊
ထွက်လာသော PIN အားလုံးကို handover card တစ်ခုတည်းတွင် စာရင်းပြပေးသည်။

- Login code: 4–32 characters၊ `A-Z 0-9 . _ -` မှသာ၊ uppercase အဖြစ် သိမ်းပြီး
  business အားလုံးတွင် ထပ်တူမရှိရ (unique)။
- Password: 6–64 characters၊ bcrypt hash ဖြင့် သိမ်းပြီး ဘယ်တော့မှ ပြန်မပြပါ။
- Password ကို ပြောင်းလိုက်ခြင်း, ပြန်ထုတ်ပေးခြင်း သို့မဟုတ် access ကို ပိတ်လိုက်ခြင်းသည်
  bearer token အားလုံးကို ချက်ချင်း revoke လုပ်လိုက်သည်။

ထိုဖုန်းများ လိုအပ်သော install file ကို ဒီ Settings page မှပင် ဖြန့်ဝေသည် —
§5 ကို ကြည့်ပါ။

## 2. Token ပုံစံ

`POST /api/v1/auth/login` သည် 64-character bearer token ကို ပြန်ပေးသည်။
၎င်း၏ SHA-256 digest ကိုသာ သိမ်းထားသည် (`agent_access_tokens`)။ Token များသည်
`AGENT_TOKEN_TTL_DAYS` (ပုံသေ 180, `config/mobileapi.php` ကို ကြည့်ပါ) ကျော်လျှင်
သက်တမ်းကုန်ပြီး device name နှင့် နောက်ဆုံးအသုံးပြုခဲ့သည့် အချိန်ကို မှတ်တမ်းတင်ထားသည်။
`Authorization: Bearer <token>` ပုံစံဖြင့် ပို့ပါ။

ဖုန်းမှ တင်ပို့သော sale သည် ထို Agent session ကို ကိုင်ဆောင်ထားသည့် Operator
အမည်အောက်တွင် မှတ်သားသည် (`agent_sessions.operator_id`)၊ ထို့ကြောင့် report,
settlement နှင့် Sale Entry ledger တို့သည် operator ဖြင့် ရိုက်ထည့်သော sale
များအတွက် လုပ်သကဲ့သို့ အတိအကျ ဆက်လုပ်နိုင်သည်။

## 3. Endpoints

Base path မှာ `/api/v1` ဖြစ်ပြီး response အားလုံး JSON ဖြစ်သည်။ Password
မှားခြင်း, token မရှိ/သက်တမ်းကုန်/revoked/access ပိတ်ထားခြင်း တို့သည် `401`;
Agent ကို disable လုပ်ထားလျှင် `403`။

| Method | Path | ရည်ရွယ်ချက် |
| --- | --- | --- |
| `POST` | `/auth/login` | Login code + password ဖြင့် token လဲလှယ်ခြင်း (throttle 10/min) |
| `GET` | `/me` | Agent profile, ပွင့်လျက် Round, session, review count |
| `POST` | `/auth/logout` | ခေါ်ဆိုလိုက်သော ဖုန်း၏ token ကို revoke လုပ်ခြင်း |
| `POST` | `/sessions/open` | Round တစ်ခုအတွက် ဤ Agent ၏ session ကို ဖွင့်/claim လုပ်ခြင်း |
| `GET` | `/sessions/{session}/state` | Session စုစုပေါင်းနှင့် နောက်ဆုံး entry 20 ခု |
| `POST` | `/sales/upload` | အလိုအလျောက် တင်ပို့ခြင်း: live entry အတွက် 1 မှတ်တမ်း, backlog အတွက် အများဆုံး 100 |

`POST /api/v1/sales/upload` request:

```json
{
  "records": [
    {
      "client_uuid": "b4d1e0a2-0f65-4c1a-9d3e-2c7f5a1b8ed0",
      "session_id": 41,
      "sale_type": "2d",
      "input": "121000",
      "recorded_at": "2026-10-13T09:12:04+00:00"
    }
  ]
}
```

Response (HTTP 200, record တစ်ခုလျှင် ရလဒ်တစ်ခု):

```json
{
  "results": [
    {"client_uuid": "b4d1...", "status": "synced", "message": "Sale synchronized."}
  ],
  "uploaded_at": "2026-10-13T09:12:05+00:00"
}
```

| `status` | အဓိပ္ပာယ် | ဖုန်း ပြုမူပုံ |
| --- | --- | --- |
| `synced` | Ledger ထဲ book ဝင်သွားပြီ (သို့မဟုတ် အရင်က ဝင်ထားပြီး) | Queue မှ ဖယ်သည် |
| `pending_review` | Round/session ပိတ်နေ၊ Hot Number သို့မဟုတ် Number Limit conflict | Queue မှ ဖယ်ပြီး "office သုံးသပ်နေဆဲ" ဟု ပြသည် |
| `invalid` | Parser က entry ကို ဖတ်၍ မရ | **Needs attention** ထဲ ပို့ပြီး ပြင်ဆင်ရန် |
| `error` | Session သည် ဤ account အတွက် မရနိုင် | Retry လုပ်ပြီး 8 ကြိမ်အထိ သုံးပြီးနောက် ရပ်တန့်သည် |

`client_uuid` သည် idempotency key ဖြစ်သည် (`agents` → `sale_inputs.operator_id +
client_uuid` unique index)။ Record အတူတူကို ပြန်ပို့လျှင် sale ကို နှစ်ကြိမ်
book မဖြစ်စေပါ — ထို့ကြောင့် app သည် လွယ်လွယ်ကူကူ retry လုပ်နိုင်သည်။ ဤ contract
သည် browser offline page သည် `POST /operator/offline-sync` တွင် သုံးသည်နှင့်
အတူတူပင် ဖြစ်ပြီး conflict များသည် Admin အတွက် **Offline sync reviews** screen
တစ်ခုတည်းထဲ ရောက်သည်။

## 4. APK ဆောက်နည်း

`mobile/README.md` ကို ကြည့်ပါ။ အနှစ်ချုပ်:

```bash
cd mobile
flutter pub get
flutter create --platforms=android --org com.wya --project-name wya_agent_mobile .
flutter build apk --release --dart-define=WYA_SERVER_URL=https://your-vps-address
```

GitHub Actions သည် အတူတူကို အလိုအလျောက် ဆောက်ပေးသည်: `android-apk.yml` သည်
`mobile/**` ကို ထိသည့် push တိုင်းတွင် run ဖြစ်ပြီး (သို့မဟုတ် **Run workflow** တွင်
VPS address ကို ရိုက်ထည့်နိုင်သည်) `wya-agent-debug-apk` နှင့်
`wya-agent-release-apk` တို့ကို upload ဖြစ်သည်။ Workflow သည် app ကို
**WYA Agent** ဟု အညွှန်းတပ်ပြီး cleartext ကို ဖွင့်ပေးသည် — HTTPS မရှိသေးသော
VPS သို့ပင် ဝင်နိုင်ရေးအတွက် ဖြစ်သည်။ Run page မှ `app-release.apk` ကိုယူပြီး
§5 ရှိ **Settings → Agent mobile app (APK)** အောက်တွင် publish လုပ်ပါ — ထို page
သည် Agent များ ဖိုင်ရယူနိုင်သည့် တစ်ခုတည်းသော လမ်းဖြစ်သည်။ အသေးစိတ်ကို
`mobile/README.md` တွင်။

## 5. APK ကို Agent များလက်ထဲ ရောက်အောင် ပို့ခြင်း (admin-only)

Install ဖိုင်ကို webapp မှ ဖြန့်ဝေပြီး public folder မှ မဟုတ်ပါ။
**Settings → Agent mobile app** (`admin/settings` page) သည် business admin ကို
§4 တွင် ဆောက်ထားသော APK upload လုပ်ခွင့်ပေးပြီး Agent များအတွက်
share မပြုနိုင်သော download link ကို ပေးနိုင်သည်။

| လုပ်ဆောင်ချက် | Route |
| --- | --- |
| APK publish | `POST /admin/settings/mobile-app` |
| ဒေါင်းလုဒ် | `GET /admin/settings/mobile-app/download` |
| ဖျက်ခြင်း | `DELETE /admin/settings/mobile-app` |

- Business တစ်ခုလျှင် row တစ်ခုသာ (`mobile_app_packages` သည် `admin_id` ပေါ် unique)၊
  ထို့ကြောင့် ပြန် publish လျှင် ယခင် build ကို အစားထိုးပြီး version များ
  စုပုံနေမှာ မဟုတ်ပါ။ Business တစ်ခုချင်းစီ၏ ဖိုင်ကို `mobile-apps/{admin}/`
  အောက်တွင် သိမ်းသည်။
- Download ဖိုင်နာမည်သည် **`{business-name}-agent-app-{version}.apk`** (admin ၏
  ကိုယ်ပိုင် account name, ထိုနာမည် ASCII မဟုတ်လျှင် — ဥပမာ မြန်မာစာ — `admin-{id}`)
  ဖြစ်ပြီး သိမ်းထားသည်။ ထို့ကြောင့် office နှစ်ခုမှ ပေးထားသော build များကို
  install မတိုင်မီ ခွဲခြားသိနိုင်သည်။
- ဖိုင်သည် **private** `local` disk ၏
  `storage/app/private/mobile-apps/{admin}/agent-app.apk` တွင် ရှိသည်။
  `storage/app/public` ထဲသို့ ဘယ်တော့မှ မရေးသဖြင့် ရယူနိုင်သည့် တစ်ခုတည်းသော
  လမ်းမှာ download route ဖြစ်ပြီး `Content-Type: application/vnd.android.package-archive`
  ဖြင့် stream ပို့သည်။
- Route အားလုံးသည် `admin` middleware နောက်တွင် ရှိသဖြင့် Operator, Agent နှင့်
  guest များသည် 403 ရသည် (guest များကို login page သို့ redirect)။ ထို့ကြောင့်
  download သည် login ဝင်ထားသော admin ၏ session တွင်သာ ကန့်သတ်ထားသည် — ဖုန်းသို့
  USB/Share ဖြင့် ပို့ပါ၊ သို့မဟုတ် ဖုန်းတွင် Settings page ကို တစ်ကြိမ် ဖွင့်ပါ။
- Upload များကို `extensions:apk` ဖြင့် စစ်ဆေးပြီး 120 MB ဖြင့် ကန့်သတ်ကာ
  throttle 10/min သတ်မှတ်သည်။ Version ကွက် (32 characters) မှာ ဖိုင်နာမည်နှင့်
  ဆိုက် (အရွယ်အစား) အနီးတွင် ပြသရန် label သာ ဖြစ်သည်။
- App ကို ဖျက်လျှင် သိမ်းထားသော ဖိုင်နှင့် row နှစ်ခုလုံး ဖျက်သည်။
- Business အချင်းချင်း ဘာမှ မကူးမည်: download နှင့် remove route များသည်
  `admin_id = login ဝင်ထားသော admin` ကိုသာ ရှာဖွေပြီး mobile API call အားလုံးသည်
  login ဝင်ထားသော Agent ၏ ကိုယ်ပိုင် `admin_id` မှတဆင့်သာ ဖြေရှင်းသည် — အခြား
  business ၏ Round တစ်ခု `POST /sessions/open` တွင် 404 ဖြစ်ပြီး၊ အခြား business
  ၏ session ကို `POST /sales/upload` က ငြင်းဆိုကာ `GET /sessions/{id}/state` တွင်
  404 ဖြစ်သည်။ Login code များသည် install တစ်ခုလုံးတွင် unique ဖြစ်သည်။
  `AgentMobileBusinessIsolationTest` နှင့်
  `MobileAppPackageTest::test_each_admin_only_reaches_their_own_published_app`
  တို့သည် ဤအချက်ကို သေချာစေသည်။

## 6. CI/CD

`.github/workflows/`:

| Workflow | Trigger | လုပ်ဆောင်ချက် |
| --- | --- | --- |
| `laravel-ci.yml` | `master`, `VPS2D3D`, `MasterW` သို့ push/PR | `composer install` + PHPUnit suite အပြည့်အစုံ (PHP 8.3, SQLite) |
| `deploy-vps.yml` | `master` တွင် CI အောင်မြင်ပြီးနောက် သို့မဟုတ် လက်လုပ် | app files rsync (ဘယ်တော့မှ `storage/`, `.env`, `vendor` မပါ), နောက် `composer install --no-dev`, `migrate --force`, `db:seed --force`, `optimize`, health check `/up` |
| `android-apk.yml` | `mobile/**` အောက် ပြောင်းလဲမှု သို့မဟုတ် လက်လုပ် | app ကို `WYA Agent` ဟု အညွှန်းတပ်, `flutter analyze`, နောက် debug နှင့် release APK artifacts |

Deploy အတွက် လိုအပ်သော repository secrets: `VPS_HOST`, `VPS_USER`,
`VPS_SSH_PRIVATE_KEY`, `VPS_PATH`, ရွေးချယ်ခွင့်ရှိသော `VPS_SSH_PORT`။ Optional
variables: `VPS_HEALTH_URL` နှင့် `WYA_SERVER_URL` (CI တွင် ဆောက်သော APK များထဲ
ထိုးသွင်းသည်)။ ဤ deployment အတွင် နှစ်ခုလုံးသည် `118.27.150.3` VPS တွင် မှတ်ပုံတင်ထားသော
Z.com domain ဆီ ညွှန်သည်: `VPS_HOST=118.27.150.3`,
`VPS_HEALTH_URL=https://wya2d3d.site/up` နှင့် `WYA_SERVER_URL=https://wya2d3d.site`။
`WYA_SERVER_URL` သည် build-time default သာ ဖြစ်ပြီး workflow သည် အတူတူ
address သို့ ပြန်သွားသည်; Agent တစ်ဦးချင်းစီသည် ၎င်းကို sign-in screen တွင်
ဆက်ပြောင်းလဲနိုင်သည်။ Deploy SSH key သည် `VPS_PATH` ကို ရေးချနိုင်ရမည် နှင့်
`VPS_PATH` သည် `docs/production-deployment.md` တွင် ဖော်ပြထားသည့် public မဟုတ်သော
application directory ဖြစ်ရမည်။

Copilot က ယခင်က ဖန်တီးထားသော GitHub branch နှစ်ခု
(`latyarthonenayapky-ux-mobile-agent-sale-entry`,
`latyarthonenayapky-ux-sale-entry-workflows`) တွင် template မှ generate လုပ်ထားသော
Symfony test workflow နှင့် SLSA release workflow သာ ပါဝင်သည်။ ၎င်းတို့သည် ဤ
Laravel app နှင့် ကိုက်ညီမှု မရှိ၊ mobile code လုံးဝ မပါ၊ ထို့ကြောင့် ဖျက်သင့်ပြီး
အထက်ပါ workflow များက ၎င်းတို့နေရာကို အစားထိုးသည်။
