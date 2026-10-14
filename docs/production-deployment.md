# Online 2D/3D နှင့် offline-sync production စတင်တင်ခြင်း

Ubuntu VPS အတွက် အစဉ်တိုင်း ရှင်းလင်းသော မြန်မာလမ်းညွှန်ကို
[`VPS_SETUP_MM.md`](VPS_SETUP_MM.md) တွင် ဖတ်ပါ။ Application လုပ်ငန်းစဉ်များကို
[Burmese User Manual](USER_MANUAL_MM.md) တွင် ဖတ်ပါ။

ဤ checklist သည် login လိုအပ်သော 2D/3D sales entry, Admin live sales, Round
စီမံခြင်း, ရလဒ်/settlement, ကာလ report များ နှင့် browser အတွင်း၌သာ သိမ်းဆည်းပြီး
နောက်မှ sync လုပ်သော offline capture တို့ကို ခြုံငုံစစ်ဆေးသည်။ Offline capture
တွင် Operator သည် offline page ကို အွန်လိုင်းရှိစဉ် တစ်ကြိမ်ဖွင့်ထားရန် လိုအပ်ပြီး
browser/device အတူတူပေါ်၌သာ အလုပ်လုပ်သည်။ Connection ပြန်ရလျှင် manual sync
လုပ်ရပါမည်။ Conflict တစ်ခုကို Admin
ပြန်လည်သုံးသပ်ရန် သိမ်းထားသည်; app ပိတ်နေချိန်တွင် background sync သို့မဟုတ် push
notification မရှိပါ။

## Server လိုအပ်ချက်များ

- PHP 8.3 သို့မဟုတ် ၎င်းထက် ပိုသစ်သော version၊ Laravel 12 အတွက် လိုအပ်သည့်
  extension များနှင့် `pdo_mysql` ပါရမည်။
- MySQL သို့မဟုတ် MariaDB — database ကို အမြဲတမ်းသိမ်းထားပြီး backup ကို
  အလိုအလျောက်လုပ်သည့် စနစ်ရှိရမည်။
- Composer 2, HTTPS နှင့် document root ကို application ၏ `public/` directory
  သို့ ညွှန်ထားသော web server။
- Laravel scheduler ကို တစ်မိနစ်လျှင် တစ်ကြိမ် run ပေးသည့် cron service။
- User များ password-reset email လိုအပ်လျှင် SMTP credentials။

## Deploy လုပ်နည်း

1. Release ကို public မဟုတ်သော application directory ထဲသို့ တင်ပါ။ `.env`,
   `storage/` နှင့် ကျန် source code များကို web server document root အပြင်ဘက်တွင်
   ထားပါ။
2. Production dependency များကို install လုပ်ပါ။

   ```sh
   composer install --no-dev --prefer-dist --optimize-autoloader
   ```

3. `.env` ကို ချိန်ညှိပါ — `APP_ENV=production`, `APP_DEBUG=false`, HTTPS
   `APP_URL`, generate လုပ်ထားသော `APP_KEY`, MySQL credentials
   (`DB_CONNECTION=mysql`), လုံခြုံသော session cookie (`SESSION_SECURE_COOKIE=true`),
   report currency label (`CURRENCY=MMK`) နှင့် အလုပ်လုပ်သည့် SMTP settings။
   `TRUSTED_PROXIES` ကို reverse proxy သို့မဟုတ် load balancer နောက်တွင် run
   လျှင်သာ ချထားပါ၊ ဘယ်တော့မှ `*` မပေးပါနှင့်။ ဤဖိုင်ကို လျှို့ဝှက်ထားပြီး
   deployment နှင့် backup အားလုံးတွင် `APP_KEY` အတူတူကို ဆက်သုံးပါ။
4. Web process အား `storage/` နှင့် `bootstrap/cache/` တို့တွင် ရေးချခွင့်ပေးပါ။
   Database migration များ run ပြီး built-in parser rules များကို seed လုပ်ပါ။

   ```sh
   php artisan migrate --force
   php artisan db:seed --force
   ```

5. ပထမဆုံး software Owner ကို မေးမြန်းပြီးမှ တည်ဆောက်သည်။ Account
   တစ်ခုခု ရှိပြီးသားဖြစ်လျှင် ဤ command ဖြင့် run ရန် ငြင်းဆိုပါမည်။

   ```sh
   php artisan thai2d3d:make-owner
   ```

6. Production configuration နှင့် views များကို cache လုပ်ပြီး HTTPS virtual host
   ကို `public/` သို့ ညွှန်ပါ။

   ```sh
   php artisan optimize
   ```

7. Scheduler ကို ချိန်ညှိပါ။

   ```cron
   * * * * * cd /path/to/Thai2D3D && php artisan schedule:run >> /dev/null 2>&1
   ```

   Scheduler သည် `rounds:sync-schedule` ကို တစ်မိနစ်လျှင် တစ်ကြိမ် နှင့်
   `users:expire-temp` ကို တစ်နာရီလျှင် တစ်ကြိမ် run သည်။ project ထဲတွင်
   ပါပြီးသား `scripts/backup-db.sh` ကိုလည်း cron ဇယားထဲ ထပ်ဖြင့် ချိန်ညှိပါ (ဥပမာ နေ့စဉ်) နှင့်
   backup များကို server ပြင်ပသို့ ကူးထားပါ။

8. တကယ့် sale များ ဝင်ခွင့်မပေးခင် Owner အဖြစ် login ဝင်ပြီး Admin ကို ဖန်တီးပါ၊
   နောက်မှ ထို Admin က Operator များနှင့် Agent များကို ဖန်တီးပါ။ Round
   အချိန်များ, ပိတ်ထားလိုသော 2D တန်ဖိုးများ (`00`–`99`), 3D number-count limit,
   2D/3D payout multiplier, Agent commission rate, 2D/3D Hot Number နှင့်
   Agent အလိုက် Amount Limit တို့ကို ချိန်ညှိပါ။ Operator login သီးသန့်ဖြင့်
   Agent တစ်ဦးကို claim ပြီး ထိန်းချုပ်ထားသော 2D နှင့် 3D sale များ ရိုက်ထည့်၍
   စမ်းသပ်ပါ။

## Go-live စစ်ဆေးချက်များ

- `https://<host>/up` ကျန်းမာသည့် response ပြန်ပြီး HTTP မှ HTTPS သို့ redirect
  ဖြစ်ကြောင်း သေချာပါစေ။
- Admin dashboard သည် ထို Admin ၏ business ကိုသာ ပြကြောင်း၊ accepted 2D နှင့် 3D
  စုစုပေါင်း အပါအဝင် သေချာပါစေ; rejected/excluded entry များသည် accepted
  total ကို မတိုးစေရ။
- Operator သည် မိမိ claim လုပ်ထားသော Agent အတွက်သာ ပွင့်လျက်ရှိသည့်
  Round တွင် sale ရိုက်နိုင်ကြောင်း နှင့် အခြား Operator ၏ sale များကို
  မမြင်နိုင်ကြောင်း သေချာပါစေ။
- Round scheduler သည် ဇယားချထားသော Round တစ်ခုကို ဖွင့်ပိတ်ပေးပြီး ၎င်း၏
  Agent session များကို ပိတ်ကြောင်း သေချာပါစေ။ တစ်မိနစ်လျှင်တစ်ကြိမ် run
  သည့် cron ကို အတည်မပြုရသေးမီ automatic Round lifecycle ကို မမှီခိုပါနှင့်။
- ထိန်းချုပ် Round တစ်ခုကို ပိတ်ပြီးနောက် 2D နှင့် 3D ရလဒ် ထည့်သွင်းခြင်းနှင့်
  settlement များကို စစ်ဆေးပါ; accepted stake, winning stake, winnings,
  commission နှင့် business net တို့ကို sale details နှင့် နှိုင်းယှဉ်ပါ။
- ထောက်ပံ့ထားသော mobile browser တစ်ခုစီတွင် Agent ၏ Offline sales page ကို
  login ဝင်ပြီး online ဖြင့် ဖွင့်ပါ၊ 2D နှင့် 3D entry နှစ်မျိုးလုံး queue ထဲထည့်ပါ၊
  page ကို ပိတ်/ပြန်ဖွင့်ပြီး network ကို ဖြတ်ကာ queue ဆက်ရှိကြောင်း စစ်ပါ။
  ပြန်ဆက်ပြီး manual sync လုပ်ကာ accepted sale တစ်ခု, အကြိမ်ထပ်ပို့သော်လည်း
  တစ်ကြိမ်သာ book ဖြစ်ကြောင်း (idempotent retry), server မလက်ခံဘဲ device ထဲတွင်
  ဆက်ကျန်နေသော invalid entry တစ်ခု, Hot Number/closed-Round conflict သည် Admin
  review ထဲ ရောက်ပြီး approve/reject လုပ်ချိန် audit မှတဆင့် ပါနေကြောင်း စစ်ဆေးပါ။
- Browser အတွင်းရှိ IndexedDB ကို ယာယီ queue အဖြစ်သာ သဘောထားပါ၊ backup
  အဖြစ် မသုံးပါနှင့်။ Device ပျောက်/ storage အားလုံးရှင်းမိလျှင် ပြန်လုပ်ရသည့်
  လုပ်ထုံးလုပ်နည်းများကို စမ်းသပ်ပြီး shared device တွင် အသုံးပြုခွင့်ကို ကန့်သတ်ပါ။
  Admin dashboard ပွင့်နေချိန်တွင် browser notification ခွင့်နှင့် audio alert
  များ အလုပ်လုပ်ကြောင်း သေချာပါစေ; closed-app push ကို မမျှော်လင့်ပါနှင့်။
- တကယ့် sale များ မစခင် database backup များကို ပြန်တင် (restore) နိုင်ကြောင်း
  သေချာပါစေ။

Mobile offline စစ်ဆေးချက်များ၊ MySQL migration၊ cron၊ HTTPS နှင့် restore
လုပ်ထုံးတို့ကို တကယ့် host ပေါ်တွင် အတည်မပြုရသေးမီ production အသုံးပြုနိုင်ကြောင်း
မကြေညာပါနှင့်။
