# Thai2D3D အသုံးပြုသူလမ်းညွှန်

ဤလမ်းညွှန်သည် Owner, Admin နှင့် Operator များအတွက်ဖြစ်သည်။ App များတွင်
၂မျိုးရှိသည်။

| အမျိုးအစား | အသုံးပြုရာ | Database |
|---|---|---|
| Standalone AppImage | Linux ကွန်ပျူတာတစ်လုံးတွင် offline သုံးရန် | ထိုကွန်ပျူတာ၏ SQLite |
| VPS Web App | Internet ရှိသည့်နေရာမှ browser ဖြင့်အသုံးပြုရန် | VPS ၏ MySQL |

AppImage နှင့် VPS database များ **သီးခြားစီ** ဖြစ်ပြီး တစ်ခုမှတစ်ခုသို့
အလိုအလျောက် sync မလုပ်ပါ။ Online အဖွဲ့လိုက်အသုံးပြုရန် VPS URL ကို browser
မှဝင်သုံးပါ။

## 1. ပထမဆုံးဖွင့်ခြင်းနှင့် Login

### Linux standalone AppImage

1. Project folder ထဲရှိ `Thai2D3D-1.1.0.AppImage` ကို double-click နှိပ်ပါ။
2. ပထမဆုံးစတင်ချိန်တွင် Owner အမည်၊ email နှင့် အနည်းဆုံး 12 လုံးရှိသော
   password ဖြည့်ပြီး local Owner account ဖန်တီးပါ။
3. နောက်တစ်ကြိမ်ဖွင့်ရာတွင် အဲဒီ email/password ဖြင့် login ဝင်ပါ။
4. App ပိတ်ချိန် window ကိုပိတ်ပါ။ Data ကို `~/.config/Thai2D3D/` အောက်တွင်
   ထိန်းသိမ်းထားသည်။

### VPS Web App

1. VPS administrator ပေးထားသော HTTPS URL ကို browser မှဖွင့်ပါ။
2. VPS ပေါ်ရှိ Owner account ကို administrator ၏ terminal မှ
   `php artisan thai2d3d:make-owner` command ဖြင့် ဖန်တီးရသည်။
3. Owner/Admin မှ ထုတ်ပေးထားသော email/password ဖြင့် login ဝင်ပါ။
4. Public registration ပိတ်ထားသည်။ အကောင့်အသစ်ကို public login page မှ
   ကိုယ်တိုင် register မလုပ်နိုင်ပါ။

Password ကို ကိုယ်တိုင်သီးသန့်ထားပါ။ Shared computer ပေါ်တွင် **Keep me signed in**
မရွေးပါနှင့်။ Password မေ့သွားလျှင် password reset လင့်ခ်ကို သုံးနိုင်သော်လည်း
server ပေါ် SMTP email ကို မှန်ကန်စွာ configure လုပ်ထားရမည်။

## 2. Role နှင့် အကောင့်တာဝန်

- **Owner** — Admin account ဖန်တီး/စီမံသည်။ Controlled testing အတွက် 7 ရက်
  သက်တမ်းရှိ Temp Admin ဖန်တီးနိုင်သည်။
- **Admin** — မိမိ business အတွင်း Operator နှင့် Agent၊ Round/Draw settings,
  Hot Numbers, results, settlements နှင့် reports ကို စီမံသည်။
- **Operator** — Admin ကဖန်တီးပေးသော account ဖြစ်သည်။ မိမိခွင့်ပြုထားသော
  Agent/Round သို့မဟုတ် Agent/Draw ကို claim လုပ်ပြီး sales ထည့်သွင်းသည်။

Admin သည် Operator ကို မိမိ business နှင့် ချိတ်ဆက်ပေးသည်။ ပိတ်ထားသော သို့မဟုတ်
သက်တမ်းကုန် Temp Admin account ကို ဆက်လက်အသုံးပြု၍မရပါ။

## 3. 2D သို့မဟုတ် 3D mode ရွေးခြင်း

Login ပြီးနောက် mode စာမျက်နှာတွင် 2D သို့မဟုတ် 3D ရွေးပါ။ ပုံမှန် highlight
ဖြစ်နေသည့် **2D** ကို Enter နှိပ်လျှင် 2D သို့ဝင်မည်။ 3D အတွက် 3D ကိုရွေးပြီး
Enter/နှိပ်ပါ။

- **2D** — နေ့စဉ် Round 3 ကြိမ်။ Yangon time schedule ဖြင့်ဖွင့်/ပိတ်သည်။
- **3D** — လစဉ် 1 ရက်နှင့် 16 ရက် Draw။ Draw ရက် Yangon time 15:30 တွင်ပိတ်သည်။

App navigation ထဲရှိ mode chooser မှလည်း mode ပြောင်းနိုင်သည်။ 2D/3D ရောင်းချမှု,
limit, settlement နှင့် report များကို သက်ဆိုင်ရာ mode အလိုက် ခွဲထားသည်။

## 4. Admin ပထမဆုံးပြင်ဆင်ခြင်း

### 4.1 အကောင့်နှင့် Agent များ

1. Owner **User access** မှ Admin account ဖန်တီးပါ။
2. Admin ဖြင့် login ဝင်ပြီး User access မှ Operator account များ ဖန်တီးပါ။
   Password သည် အနည်းဆုံး 12 လုံးရှိရမည်။
3. **2D Settings → Agents and 2D Round assignments** မှ Agent code, အမည်,
   optional phone နှင့် 2D commission ထည့်ပါ။
4. **3D Settings** မှ Agent တစ်ခုချင်းစီ၏ 3D commission ကို သီးခြားသတ်မှတ်ပါ။

Operator တစ်ဦးသည် Round များစွာ၊ Draw များစွာတွင် အလုပ်လုပ်နိုင်သည်။ သို့သော်
Agent တစ်ခုကို တစ်ခုသော Round/Draw အတွင်း account တစ်ခုသာ claim လုပ်နိုင်သည်။
နောက်ထပ် Round/Draw တစ်ခုတွင် claim အသစ်လုပ်နိုင်သည်။

### 4.2 2D schedule နှင့် limit

**2D Settings → Daily Round schedule** မှ Round 1–3 အတွက် Close time နှင့်
လိုအပ်ပါက Start time ကို သတ်မှတ်ပါ။ Start time ဗလာထားလျှင် ပထမ Round သည်
နေ့အစတွင်စပြီး နောက် Round များသည် ရှေ့ Round ပိတ်ချိန်တွင် စသည်။ Round ဖွင့်ရန်
2D Number Limit သတ်မှတ်ရန် မလိုပါ။ Schedule ပြောင်းလဲမှုသည်
မစတင်ရသေးသော Round များကိုသာ သက်ရောက်သည်။

2D payout multiplier ကို တစ်ကြိမ်သတ်မှတ်ပြီး ပြောင်းလိုသည့်အခါမှ ပြင်ပါ။
Hot Numbers နှင့် ပိတ်မည့် 2D value များကို သက်ဆိုင်ရာ Round ၏ Sale Entry ထဲတွင်
ရွေးထည့်နိုင်သည်။ သတ်မှတ်ပိတ်ချိန်ကျော်ပြီး Round ကို **Reopen** လုပ်ထားလျှင်
**Setting → Upcoming Rounds and Hot Numbers** အပိုင်းရှိ Round စာရင်းတွင်
**Manually reopened** ဟု ပြမည်။ ထို Round အတန်း၏ **Reclose reopened Round** ကို
နှိပ်၍ အချိန်မရွေး ချက်ချင်းပြန်ပိတ်နိုင်သည်။ ပိတ်ပြီးသည်နှင့် သက်ဆိုင်ရာ Agent
sessions များ read-only ဖြစ်ပြီး scheduler က ထို Round ကို အလိုအလျောက်ပြန်ဖွင့်မည်မဟုတ်ပါ။

### 4.3 3D Draw ပြင်ဆင်မှု

**3D Settings** မှ business-wide 3D payout multiplier နှင့် Agent 3D commission
သတ်မှတ်ပါ။ အနာဂတ် Draw တစ်ခုချင်းစီအတွက် Number Limit နှင့် 000–999 Hot Numbers
ကို Draw day မရောက်မီ ပြင်ဆင်ပါ။ Draw day စပြီးနောက် Draw settings/Hot Number
ပြင်ဆင်ခွင့် အကန့်အသတ်ရှိသည်။

### 4.4 Amount Limit နှင့် Hot Number

- **2D Number Limit** သည် `00` မှ `99` အတွင်း ပိတ်ထားသော value စာရင်းဖြစ်သည်။
  စာရင်းထဲရှိ value များသာ reject ဖြစ်ပြီး sale အရေအတွက်ကို မကန့်သတ်ပါ။
- **3D Number Limit** သည် Draw တစ်ခုအတွင်း Agent အားလုံး၏ accepted number
  အရေအတွက်ကို စုပေါင်းကန့်သတ်သည်။
- **Amount Limit** သည် Agent ၏ sales ပမာဏကို စောင့်ကြည့်ရန် threshold သာဖြစ်ပြီး
  sale ကို reject လုပ်သည့် limit မဟုတ်ပါ။ Sale Entry တွင် Agent တစ်ဦးချင်း၏
  Amount Limit နှင့် အဲဒီ Agent ၏ number တစ်ခုချင်း excess ကိုကြည့်နိုင်သည်။
- **Hot Number** နှင့်ကိုက်ညီသော number ကို reject လုပ်သည်။ Rule တွင် အခြား
  valid number များပါလျှင် အသေးစိတ် status ကို saved sales မှ စစ်ဆေးပါ။

## 5. Sale Entry — 2D/3D

1. **Sale Entry** မှ **2D Sale Entry** သို့မဟုတ် **3D Sale Entry** ကိုရွေးပါ။
   **Menu → Agent Statement** မှ per-Agent စာရင်းကို ဖွင့်နိုင်သည်။ Admin ၏
   Live of items sold သည် ရွေးထားသော Round အတွင်း Agent အားလုံး၏ accepted sale
   များကို စုပေါင်းပြသည်။
2. 2D အတွက် ဖွင့်ထားသော Round ကိုရွေးပါ။ 3D အတွက် လက်ရှိ Draw ကိုရွေးပါ။
   Draw day သည် လစဉ် 1/16 ရက်ဖြစ်ပြီး Yangon time 00:00 မှ 15:30 အထိသာ sales
   ဖွင့်သည်။
3. 2D Sale Entry တွင် Admin သည် Sale input အပေါ်က Agent dropdown မှ မိမိ၏
   business အတွင်းရှိ active Agent အားလုံးကို ရွေးနိုင်သည်။ Agent ကို Operator
   တစ်ဦးက အဲဒီ Round အတွက် claim လုပ်ပြီးသားဆိုလျှင် dropdown ထဲတွင်
   unavailable ဟုပြသပြီး Admin က ဝင်ရိုက်၍မရပါ။ မ claim ရသေးသော Agent ကို
   ရွေးလိုက်လျှင် Admin အတွက် အဲဒီ Round ၌ ချက်ချင်း claim လုပ်ပေးမည်။
   **Save (F1)** ပြီးလျှင် dropdown မှ အခြားရနိုင်သော Agent သို့ ပြောင်း၍
   ဆက်ရောင်းနိုင်သည်။ Operator သည် မိမိသို့ claim လုပ်ထားသော Agent များကိုသာ
   ရွေးနိုင်သည်။ 3D တွင်လည်း Agent/Draw ကို ပထမ claim လုပ်သည့် account က
   ဆက်လက်ပိုင်ဆိုင်သည်။
4. Input box ထဲ sale စာသားရိုက်ပြီး **Enter** နှိပ်ပါ။ အဲဒါက browser ထဲရှိ
   **Recent Sales** draft စာရင်းထဲသာ ထည့်သေးသည်။
5. Draft များကိုစစ်ပြီး **Save (F1)** နှိပ်ပါ။ အဲဒီအခါမှ server/database ထဲ
   ပို့သွားမည်။ မအောင်မြင်သော draft ကို error message နှင့် ပြန်စစ်ပြီးမှ retry
   လုပ်ပါ။
6. **List of items sold** တွင် လက်ခံထားသော number နှင့် စုစုပေါင်း amount
   ပေါ်မည်။ Amount အများဆုံးမှ အနည်းဆုံးအလိုက် စီထားသည်။

Keyboard shortcut များ: `Enter` = draft ထဲထည့်, `F1` = Save, `F2` = Agent
selector, `F3` = input box.
Desktop 2D Sale Entry တွင် စာလုံးများကို အလိုအလျောက် uppercase ပြောင်းပြီး
ရိုက်သည့် `*` ကို `R` အဖြစ် သုံးသည်။ Legacy `*` list ကို paste လုပ်လျှင် မပြောင်းပါ။

### Input ဥပမာများ

Rule code များကို uppercase သုံးပြီး space/line break မထည့်ပါနှင့်။ ပမာဏသည်
သုညထက်ကြီးရမည်။ Admin က custom rule ပြင်ဆင်ထားပါက number set လည်း ပြောင်းနိုင်သည်။

| Input | အဓိပ္ပါယ် |
|---|---|
| `121000` | 2D number `12`, amount `1000` |
| `00500` | 2D number `00`, amount `500` |
| `A1000` | အပူး `00, 11, 22, …, 99` ကို တစ်ခုစီ amount `1000` ဖြင့် |
| `2B1000` | Digit နှစ်လုံးပေါင်း၍ နောက်ဆုံး digit `2` ရသော ဘရိတ် number များကို တစ်ခုစီ `1000` ဖြင့် |
| `1F1000` | ရှေ့ဂဏန်း `1` ဖြစ်သော number 10 ခုကို တစ်ခုစီ `1000` ဖြင့် |
| `F11000` | နောက်ဂဏန်း `1` ဖြစ်သော number 10 ခုကို တစ်ခုစီ `1000` ဖြင့် |
| `12R1000` | 2D `12` နှင့် reverse `21`, တစ်ခုစီ amount `1000` |
| `1အပါ1000` သို့မဟုတ် `1 အပါ 1000` | `1` ပါဝင်သော 2D number 19 ခု၊ တစ်ခုစီ amount `1000` ဖြင့်။ အရှေ့/အနောက် `1` နှစ်မျိုးလုံးပါပြီး `11` ကို တစ်ကြိမ်သာထည့်သည်။ စုစုပေါင်း `19000` ဖြစ်သည်။ |
| `1/1000` | `1 အပါ 1000` အတွက် အတိုကောက်။ အခြား rule များတွင် `/` ကို exclusion အဖြစ် အသုံးပြုနိုင်သည်။ |
| `1234P1000` | ထည့်ထားသော digit များကို နှစ်လုံးစီစပ်သော ပတ်သီး rule။ 2D rule ဖြစ်ပြီး 3D ပုံမှန် input မဟုတ်ပါ။ |
| `++1000`, `--1000` | စုံ/စုံ သို့မဟုတ် မ/မ ဖြစ်သော number များကို တစ်ခုစီ `1000` ဖြင့် |
| `+-1000`, `-+1000` | စုံ/မ သို့မဟုတ် မ/စုံ ဖြစ်သော number များကို တစ်ခုစီ `1000` ဖြင့် |
| `W1000`, `N1000`, `X1000` | Admin ၏ Configured number rules တွင် လက်ရှိသတ်မှတ်ထားသော list ကိုသုံးသည်။ |
| `A=1000` | Rule code နောက်တွင် `=` ဖြင့် amount ခွဲရေးနိုင်သည်။ ပုံမှန် number အတွက် `121000` ပုံစံကိုသုံးပါ။ |
| `123500` | 3D number `123`, amount `500` |
| `A[2233]1000` | `A` set, bracket ထဲရှိ `22`, `33` ကို exclude လုပ်ပြီး amount `1000` |

`1 အပါ 1000` အတွက် ထွက်လာသည့် အစီအစဉ်မှာ `11,12,13,14,15,16,17,18,19,10,21,31,41,51,61,71,81,91,01` ဖြစ်သည်။ အခြား digit အတွက်လည်း အရှေ့နှင့်အနောက်နေရာ နှစ်ခုစလုံးတွင် ထို digit ပါသော 2D number များကို တစ်ကြိမ်စီထည့်သည်။

### 2D Rule code များ

| Code | အလုပ်လုပ်ပုံ |
|---|---|
| `A` | အပူး: `00, 11, …, 99` |
| `dB` | ဘရိတ်: digit နှစ်လုံးပေါင်းပြီး နောက်ဆုံး digit သည် `d` ဖြစ်သော number များ။ ဥပမာ `2B`။ |
| `dF` / `Fd` | ရှေ့ဂဏန်း `d` / နောက်ဂဏန်း `d`။ ဥပမာ `1F`, `F1`။ |
| `ddR` | number နှင့် reverse။ ဥပမာ `12R` သည် `12, 21`; `11R` သည် `11` တစ်ခုသာ။ |
| `digitsP` | ထည့်ထားသော digit များကို ထည့်ထားသည့်အစဉ်အတိုင်း နှစ်လုံးစီစပ်သည်။ ထပ်နေသော digit ကို တစ်ကြိမ်သာယူသည်။ |
| `++`, `--`, `+-`, `-+` | နံပါတ်၏ ရှေ့/နောက် digit များ စုံ/မ ပေါင်းစပ်မှု။ |
| `W`, `N`, `X` နှင့် custom uppercase letter | Admin Settings တွင် သတ်မှတ်ထားသည့် number set။ လက်ရှိ contents နှင့် Active status ကိုစစ်ပါ။ |

Rule မှ နံပါတ်အချို့ကိုဖယ်ရန် bracket သုံးပါ။ ဥပမာ `A[2233]1000` သည် `A`
စာရင်းမှ `22` နှင့် `33` ကိုဖယ်သည်။ Bracket block တစ်ခုသာ သုံးနိုင်ပြီး
ဗလာမထားရပါ။ Lowercase code၊ amount သုည သို့မဟုတ် အနုတ်၊ မလိုအပ်သော space
ပါသော input ကို reject လုပ်နိုင်သည်။

Admin ပြင်ဆင်ထားသော `W/N/X` သို့မဟုတ် custom rule သည် business တစ်ခုချင်းအလိုက်
ကွဲနိုင်သည်။ သင့် Admin ၏ **2D Settings → Configured number rules** အပိုင်းတွင်
လက်ရှိ number set နှင့် Active status ကိုစစ်ပါ။

### Rejected / Excluded input စစ်ဆေးခြင်း

Sale Entry history တွင် `accepted`, `rejected`, `excluded` status နှင့်
reject reason များကို ကြည့်ပါ။ 2D Number Limit စာရင်းထဲရှိ value တစ်ခုချင်းသာ
reject ဖြစ်သည်။ 3D Number Limit
ပြည့်နေခြင်း သို့မဟုတ် ဒီ input တစ်ခုက ကျန်ရှိသော 3D count ထက်ကျော်သွားခြင်းရှိလျှင်
3D input တစ်ခုလုံး reject ဖြစ်နိုင်သည်။ Amount Limit သည် sale ကို reject လုပ်သည့်အကြောင်း
မဟုတ်ပါ။ Admin သည် သက်ဆိုင်ရာ Round ၏ ပိတ်ထားသော 2D values၊ Draw ၏ 3D
Number Limit၊ Hot Numbers,
input code/spaces, Round/Draw ဖွင့်နေသလားကို စစ်ပြီး လိုအပ်လျှင် ဆုံးဖြတ်ပါ။
Already saved sale ကို အလိုအလျောက်ပြင်မပေးပါ။

## 6. 2D/3D ရလဒ်၊ settlement နှင့် summaries

### 2D

Round ပိတ်ပြီးမှ Admin သည် **Round results and settlements** တွင် 2D ရလဒ်ကို
ထည့်/ပြင်နိုင်သည်။ ပြောင်းလဲမှုကို change audit ထဲတွင် ထိန်းသိမ်းသည်။ Settlement
သည် accepted, non-excluded wager များကိုသာ သုံးသည်။ အဲဒီထဲမှာ accepted stake,
winning stake, winnings, Agent commission နှင့် business net ကိုပြသည်။

### 3D

Draw ပိတ်ပြီးနောက် **3D results and settlements** မှ သုံးလုံးရလဒ် ထည့်/ပြင်ပါ။
Result correction များကို audit လုပ်ထားပြီး settlement ပြန်တွက်သည်။ 3D payout
multiplier ကို 3D Settings တွင် saved ထားသော business rate ကိုသုံးသည်။

### Period summaries

**Business summaries** မှ daily, weekly, monthly သို့မဟုတ် yearly ကာလရွေးပြီး
Agent, Round/Draw filter ဖြင့်ကြည့်နိုင်သည်။ 2D နှင့် 3D summary များသည် သီးခြား
စစ်နိုင်သည်။ Operator သည် မိမိ scope အတွင်းရှိ records များကိုသာ မြင်ရသည်။

## 7. Offline Sale Entry (VPS Web App)

Offline capture သည် **VPS web app** တွင် browser/device တစ်ခုတည်းအတွက်သာဖြစ်သည်။
AppImage ၏ local SQLite ကို VPS နှင့် sync လုပ်ပေးခြင်းမဟုတ်ပါ။

1. Operator သည် online ဖြစ်နေချိန် မိမိ Agent ၏ **Offline sales** page ကို
   တစ်ကြိမ်ဖွင့်ပါ။ Browser က offline page shell ကို cache လုပ်ရန်လိုသည်။
2. Internet ပြတ်လျှင် အဲဒီ device/browser မှ queued sale များထည့်ပါ။ Queue ကို
   ထို browser ၏ local IndexedDB တွင်သိမ်းသည်။ Browser data မရှင်းပါနှင့်။
3. Internet ပြန်ရလာလျှင် online ပြန်ချိတ်၊ အဲဒီ account ဖြင့် login ဝင်ပြီး
   manual **Sync** လုပ်ပါ။
4. Sync result တစ်ခုချင်းစီ စစ်ပါ။ Server သည် လက်ရှိ Round/Draw, Number Limit
   နှင့် Hot Number ကိုပြန်စစ်သည်။ Conflict များကို Admin review queue သို့
   ပို့နိုင်သည်; Admin သည် approve/reject နှင့် audit လုပ်ရသည်။

Offline queue သည် **backup မဟုတ်ပါ**။ Device ပျောက်ခြင်း၊ browser data ရှင်းခြင်း၊
browser profile ပြောင်းခြင်းကြောင့် queue ပျောက်နိုင်သည်။ Sync completed ဟု
ပြသသည်ကို အတည်ပြုပြီး server-side report မှာ တူညီကြောင်း စစ်မှသာ online data
ထဲဝင်သည်ဟု ယူဆပါ။

## 8. Theme, font size, clock နှင့် notification

Header control မှ **System / Light / Dark** theme နှင့် font size ရွေးနိုင်သည်။
ရွေးချယ်မှုသည် browser ၏ local storage ထဲတွင်သိမ်းထားပြီး ထို browser/device
အတွက်သာဖြစ်သည်။ Header clock/date သည် Yangon time ကိုပြသည်။ Active Round
ပိတ်ချိန်နီးလာလျှင် countdown အရောင်ပြောင်းသည်။

Admin live dashboard သည်ဖွင့်ထားစဉ် update လုပ်ပြီး browser notification သို့မဟုတ်
audio alert သည် user permission ပေးထားမှ အလုပ်လုပ်မည်။ App/browser ပိတ်ထားချိန်
push notification မရပါ။

## 9. Data ကိုကာကွယ်ခြင်း

- AppImage အသုံးပြုလျှင် app ကိုပိတ်ပြီး `~/.config/Thai2D3D/` folder တစ်ခုလုံးကို
  လုံခြုံသောနေရာသို့ copy လုပ်ပါ။ Live database ဖိုင်ကို app ဖွင့်နေစဉ် copy
  မလုပ်ပါနှင့်။
- VPS အသုံးပြုလျှင် Admin/hosting administrator သည် MySQL database ကို
  အချိန်မှန် backup လုပ်ပြီး `.env`/`APP_KEY` ကို သီးခြားကာကွယ်ရမည်။
- Password, backup, database သို့မဟုတ် `.env` ကို public folder, shared link,
  chat သို့မဟုတ် screenshot ဖြင့် မပို့ပါနှင့်။

အသေးစိတ် VPS deployment အတွက် [VPS Setup Guide](VPS_SETUP_MM.md) ကိုကြည့်ပါ။
