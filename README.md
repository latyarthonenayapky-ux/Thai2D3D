# Thai2D3D

Thai2D3D သည် 2D နေ့စဉ် Round များနှင့် 3D လစဉ် Draw များအတွက် sales entry,
Agent စီမံခန့်ခွဲမှု၊ ရလဒ်၊ settlement နှင့် summary များကို စီမံသည့် application
ဖြစ်သည်။

## ယခု Linux ကွန်ပျူတာပေါ်တွင် ဖွင့်သုံးရန်

Linux x86-64 အတွက် standalone AppImage ကို ဤ project folder ၏ root တွင်
ထားပေးထားသည်။

**ဖွင့်ရန် — `Thai2D3D-1.1.0.AppImage` ကို File Manager မှ double-click နှိပ်ပါ။**
ဖိုင်ကို executable အဖြစ်ပြင်ဆင်ထားပြီး PHP သို့မဟုတ် Composer ကို သီးခြား
install လုပ်ရန်မလိုပါ။ ပထမဆုံးဖွင့်ချိန်တွင် Owner အမည်၊ email နှင့်
အနည်းဆုံး 12 လုံးရှိသော password ဖြင့် local Owner account တည်ဆောက်ပါ။
နောက်တစ်ကြိမ်ဖွင့်လျှင် အဲဒီ account ဖြင့် login ဝင်ပါ။

File Manager က launch ခွင့်မပေးလျှင် ဖိုင်ပေါ် right-click → **Properties** →
**Permissions** ထဲက executable/run ခွင့်ကို ဖွင့်ပြီး double-click ပြန်နှိပ်ပါ။
လိုအပ်လျှင် Terminal မှ တစ်ကြိမ်သာ အောက်ပါအတိုင်းဖွင့်နိုင်သည်။

```sh
chmod +x ./Thai2D3D-1.1.0.AppImage
./Thai2D3D-1.1.0.AppImage
```

App ပိတ်ရန် window ကိုပိတ်ပါ။ Local SQLite database နှင့် app data ကို
`~/.config/Thai2D3D/` အောက်တွင် သိမ်းထားသည်။ Backup/restore မလုပ်မီ app ကို
အရင်ပိတ်ပါ။ ဒီ standalone build ၏ database သည် ဒီကွန်ပျူတာပေါ်မှာသာရှိပြီး
VPS/online server သို့ အလိုအလျောက် sync မလုပ်ပါ။

**Windows:** portable offline app ကို
`Thai2D3D-1.1.0-Windows.exe` အမည်ဖြင့် build လုပ်ထားသည်။ Windows 10/11 x64
ကွန်ပျူတာတွင် double-click ဖြင့်ဖွင့်နိုင်ပြီး ပထမဆုံးအကြိမ်တွင် local Owner
account တည်ဆောက်ပါ။ PHP/Composer ကို သီးခြား install လုပ်စရာမလိုပါ။
PHP runtime အတွက် Microsoft Visual C++ 2015–2022 Redistributable (x64)
မရှိသေးသော Windows ကွန်ပျူတာတွင် ၎င်းကို ထပ်မံတပ်ဆင်ရန် လိုနိုင်သည်။
ဤ `.exe` တွင် code-signing certificate မပါသောကြောင့် Windows SmartScreen
သတိပေးချက် ပြနိုင်သည်။ ယခင် `1.0.0.exe` သည် online
website wrapper အဟောင်းဖြစ်ပြီး standalone offline app မဟုတ်ပါ။

## စာရွက်စာတမ်း

- [Burmese User Manual](docs/USER_MANUAL_MM.md) — login, role, 2D/3D sales,
  settings, results, settlement နှင့် offline queue
- [VPS Setup Guide](docs/VPS_SETUP_MM.md) — Ubuntu, MySQL, PHP, HTTPS,
  scheduler, Owner စတင်ဖန်တီးခြင်း၊ backup နှင့် update
- [Production Go-live Checklist](docs/production-deployment.md)
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
