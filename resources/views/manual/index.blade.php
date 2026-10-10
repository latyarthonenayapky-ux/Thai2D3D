@extends('layouts.app', ['title' => 'အသုံးပြုနည်း လမ်းညွှန်'])

@section('content')
<style>
    .manual{max-width:1040px;margin:30px auto;padding:0 24px 48px}
    .manual h1{margin:0;font-size:30px;letter-spacing:-.03em}
    .manual-intro{max-width:72ch;margin:10px 0 24px;color:var(--muted);line-height:1.7}
    .manual-nav{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 30px;padding:0;list-style:none}
    .manual-nav a{display:inline-block;padding:8px 11px;border:1px solid var(--border);border-radius:6px;background:var(--surface);font-size:13px;font-weight:700}
    .manual section{max-width:76ch;margin:0 0 34px;scroll-margin-top:20px}
    .manual h2{margin:0 0 12px;font-size:21px}
    .manual h3{margin:22px 0 8px;font-size:16px}
    .manual p,.manual li{line-height:1.7}
    .manual p{margin:8px 0}
    .manual ul,.manual ol{padding-left:22px}
    .manual code{padding:2px 5px;border-radius:4px;background:var(--surface-soft);color:var(--text);font-size:.94em}
    .manual kbd{display:inline-block;padding:2px 6px;border:1px solid var(--border);border-bottom-width:2px;border-radius:4px;background:var(--surface);font:inherit;font-size:.9em}
    .manual .table-wrap{margin:12px 0 18px}
    .manual td{white-space:normal;vertical-align:top}
    .manual .manual-note{padding:12px 14px;border:1px solid var(--border);border-radius:7px;background:var(--surface);color:var(--text)}
    .manual .manual-top{display:flex;justify-content:flex-end;margin-top:18px}
    @media(max-width:600px){.manual{margin:22px auto;padding:0 16px 36px}.manual h1{font-size:25px}.manual-nav{gap:6px}.manual-nav a{padding:7px 9px}}
</style>
<main class="manual" lang="my">
    <h1 id="manual-title">Thai2D3D အသုံးပြုနည်း လမ်းညွှန်</h1>
    <p class="manual-intro">Sale Entry စာရိုက်ပုံ၊ 2D/3D နှင့် Admin Setting များ၊ report နှင့် အခြားလုပ်ဆောင်ချက်များကို အပိုင်းလိုက် ရှင်းပြထားပါသည်။ လိုသည့်ခေါင်းစဉ်ကို ရွေးပြီး သွားနိုင်သည်။</p>

    <nav aria-label="လမ်းညွှန်အပိုင်းများ">
        <ul class="manual-nav">
            <li><a href="#sale-entry">Sale Entry</a></li>
            <li><a href="#two-d-input">2D စာရိုက်နည်း</a></li>
            <li><a href="#three-d-input">3D စာရိုက်နည်း</a></li>
            <li><a href="#settings-2d">2D Setting</a></li>
            <li><a href="#settings-3d">3D Setting</a></li>
            <li><a href="#reports">Report နှင့် Statement</a></li>
            <li><a href="#display">Display နှင့် Desktop</a></li>
            <li><a href="#offline">Offline အသုံးပြုခြင်း</a></li>
        </ul>
    </nav>

    <section id="sale-entry">
        <h2>Sale Entry စတင်အသုံးပြုခြင်း</h2>
        <ol>
            <li>Navigation မှ <strong>Sale Entry → 2D Sale Entry</strong> သို့မဟုတ် <strong>3D Sale Entry</strong> ကို ရွေးပါ။ 2D နှင့် 3D စာရင်းများ၊ သတ်မှတ်ချက်များ သီးခြားဖြစ်သည်။</li>
            <li>သက်ဆိုင်ရာ ဖွင့်ထားသော Round သို့မဟုတ် Draw ကိုရွေးပြီး Agent ကိုရွေးပါ။ Agent ကို မတိုင်မီ မည်သူမျှ claim မလုပ်ထားလျှင် <strong>Claim</strong> ကို တစ်ကြိမ်လုပ်ပါ။</li>
            <li>Input box ထဲတွင် sale တစ်ကြောင်းစီရိုက်ပြီး <kbd>Enter</kbd> နှိပ်ပါ။ ထိုအဆင့်သည် Recent Sales draft ထဲထည့်ခြင်းသာဖြစ်ပြီး မသိမ်းရသေးပါ။</li>
            <li>Draft နှင့် ပမာဏကို ပြန်စစ်ပြီး <kbd>F1</kbd> သို့မဟုတ် <strong>Save</strong> ကိုနှိပ်မှသာ server ထဲသို့ သိမ်းမည်။</li>
        </ol>
        <p>Admin ၏ Live of items sold သည် ရွေးထားသော Round/Draw အတွင်း Agent အားလုံး၏ လက်ခံထားသော sale များကို စုပေါင်းပြသည်။ <strong>Menu → Agent Statement</strong> သည် Agent တစ်ဦးချင်းစာရင်းဖြစ်သည်။</p>
        <p class="manual-note">Desktop 2D Sale Entry တွင် ရိုက်ထည့်သော အင်္ဂလိပ်စာလုံးများကို အကြီးစာလုံးပြောင်းပြီး <code>*</code> ကို <code>R</code> အဖြစ်ပြောင်းပေးသည်။ ဤပြောင်းလဲမှုသည် ရိုက်သည့်စာသားအတွက်သာဖြစ်သည်။ Clipboard မှ paste လုပ်သည့်စာသားကို မပြောင်းပါ။</p>
    </section>

    <section id="two-d-input">
        <h2>2D Sale Input စာရိုက်နည်း</h2>
        <p>နံပါတ်တစ်ခုနှင့် ပမာဏကို code နောက်တွင် ဆက်ရေးပါ။ ပမာဏသည် သုညထက်ကြီးရမည်။ ပုံမှန် input များတွင် space သို့မဟုတ် line break မထည့်ပါနှင့်။</p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>စာရိုက်ပုံ</th><th>အဓိပ္ပါယ်</th></tr></thead>
                <tbody>
                    <tr><td><code>121000</code></td><td>ပထမနှစ်လုံး <code>12</code> သည် 2D နံပါတ်၊ ကျန် <code>1000</code> သည် ပမာဏ။</td></tr>
                    <tr><td><code>00500</code></td><td>နံပါတ် <code>00</code> ကို ပမာဏ 500 ဖြင့် ရောင်းသည်။ ရှေ့ဆုံး zero ကို မဖြုတ်ပါနှင့်။</td></tr>
                    <tr><td><code>A1000</code></td><td>အပူးစာရင်း <code>00, 11, 22, …, 99</code> ကို တစ်ခုစီ 1000 ဖြင့်။</td></tr>
                    <tr><td><code>2B1000</code></td><td>ဘရိတ် 2 စာရင်းကို တစ်ခုစီ 1000 ဖြင့်။ B နောက်က digit သည် digit နှစ်လုံးပေါင်းပြီး နောက်ဆုံး digit 2 ဖြစ်သော နံပါတ်များကို ရွေးသည်။</td></tr>
                    <tr><td><code>1F1000</code> / <code>F11000</code></td><td><code>1F</code> သည် ရှေ့ဂဏန်း 1 ပါသောနံပါတ်များ၊ <code>F1</code> သည် နောက်ဂဏန်း 1 ပါသော နံပါတ်များဖြစ်သည်။</td></tr>
                    <tr><td><code>1 အပါ 1000</code></td><td>ဂဏန်း 1 ပါသော နံပါတ် 19 မျိုးကို တစ်ခုစီ 1000 ဖြင့်ထည့်သည်။ <code>1အပါ1000</code> နှင့် <code>1/1000</code> ကိုလည်း လက်ခံသည်။ 11 ထပ်နေမှုကို တစ်ကြိမ်သာထည့်သည်။</td></tr>
                    <tr><td><code>12R1000</code></td><td><code>12</code> နှင့် ပြောင်းပြန် <code>21</code> ကို တစ်ခုစီ 1000 ဖြင့်။ <code>11R</code> ကဲ့သို့ ပြောင်းပြန်တူသည့်နံပါတ်သည် တစ်ကြိမ်သာဖြစ်သည်။</td></tr>
                    <tr><td><code>1234P1000</code></td><td>ထည့်ထားသော digit များကို နှစ်လုံးစီစပ်သည့် ပတ်သီးစာရင်း။ <code>1234P</code> တွင် 11, 12, 13, 14, 21 … 44 စုစုပေါင်း 16 ခု ပါသည်။</td></tr>
                    <tr><td><code>++1000</code>, <code>--1000</code>, <code>+-1000</code>, <code>-+1000</code></td><td>အစုံလိုက်စုံ/စုံ, မ/မ, စုံ/မ, မ/စုံ နံပါတ်များကို ရွေးသည်။</td></tr>
                    <tr><td><code>W1000</code>, <code>N1000</code>, <code>X1000</code></td><td>Admin ၏ Configured number rules တွင် လက်ရှိသတ်မှတ်ထားသော စာရင်းကို သုံးသည်။ အဲဒီ Settings အပိုင်းတွင် နံပါတ်အစုကို စစ်ဆေးပါ။</td></tr>
                    <tr><td><code>Q1000</code></td><td>Admin က ဖန်တီးပြီး Active ထားသော custom စာလုံးတစ်လုံးစာရင်းကို သုံးသည်။ ဥပမာ Q ကို သင့် Admin ၏ စာရင်းနှင့် အစားထိုးပါ။</td></tr>
                    <tr><td><code>A=1000</code></td><td>Rule အတွက် <code>=</code> ဖြင့် amount ခွဲရေးနိုင်သည့်ပုံစံ။ ပုံမှန်နံပါတ် input အတွက်တော့ <code>121000</code> ကဲ့သို့ ရိုက်ပါ။</td></tr>
                </tbody>
            </table>
        </div>
        <p><strong><code>1 အပါ 1000</code> ထွက်လာမည့်နံပါတ်များ:</strong> <code>11, 12, 13, 14, 15, 16, 17, 18, 19, 10, 21, 31, 41, 51, 61, 71, 81, 91, 01</code>။ တစ်ခုစီ 1000 ဖြစ်သဖြင့် စုစုပေါင်း amount သည် 19000 ဖြစ်သည်။</p>
        <h3>Exclusion နံပါတ်များ ဖယ်ရှားခြင်း</h3>
        <p>Rule မှထွက်လာသော နံပါတ်အချို့ကို <code>[]</code> ထဲတွင် ထည့်ပြီး ဖယ်ရှားနိုင်သည်။ ဥပမာ <code>A[2233]1000</code> သည် အပူးစာရင်းမှ <code>22</code> နှင့် <code>33</code> ကိုဖယ်သည်။ Bracket ကို တစ်ခုသာ သုံးပါ၊ အတွင်းစာရင်းကို ဗလာမထားပါနှင့်။</p>
        <p>Lowercase code၊ မမှန်သောပမာဏ၊ မလိုအပ်သော space သို့မဟုတ် ကိုယ့် business တွင် Active မဖြစ်သော custom rule သုံးလျှင် input ကို reject လုပ်နိုင်သည်။</p>
    </section>

    <section id="three-d-input">
        <h2>3D Sale Input စာရိုက်နည်း</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>စာရိုက်ပုံ</th><th>အဓိပ္ပါယ်</th></tr></thead>
                <tbody>
                    <tr><td><code>123500</code></td><td>သုံးလုံးနံပါတ် <code>123</code> ကို amount 500 ဖြင့်ရောင်းသည်။</td></tr>
                    <tr><td><code>A1000</code></td><td><code>000, 111, 222, …, 999</code> ကို တစ်ခုစီ 1000 ဖြင့်ရောင်းသည်။</td></tr>
                </tbody>
            </table>
        </div>
        <p>3D တွင် 000 မှ 999 အထိ သုံးလုံးလုံးရိုက်ပါ။ Lowercase နှင့် space မရပါ။ 3D Number Limit သည် ရောင်းနိုင်သော accepted number အရေအတွက်ကို Draw တစ်ခုလုံးအတွက် ကန့်သတ်သည်။</p>
    </section>

    <section id="settings-2d">
        <h2>Admin · 2D Setting များ</h2>
        <p>Admin account ဖြင့် Navigation ၏ <strong>Setting</strong> ထဲမှ 2D setting ကိုဖွင့်ပါ။ ဤထိန်းချုပ်မှုများသည် ထို Admin ၏ business အတွက်ဖြစ်သည်။</p>
        <ul>
            <li><strong>Daily Round schedule</strong> — တစ်နေ့တာ Round 1–3 ၏ Start/Close အချိန်ကို Asia/Yangon time ဖြင့်သတ်မှတ်ပါ။ Start ကိုဗလာထားလျှင် ယခင် Round ပိတ်ချိန်မှ နောက် Round စတင်နိုင်သည်။</li>
            <li><strong>Winning payout multiplier</strong> — 2D settlement အတွက် business payout ကို ပြင်ဆင်သည်။</li>
            <li><strong>Upcoming Rounds / Number Limit</strong> — Round ရွေးပြီး <code>00</code> မှ <code>99</code> အတွင်း ပိတ်မည့် value များကို comma, space သို့မဟုတ် line break ခြား၍ထည့်ပါ။ Number Limit သည် ရောင်းအရေအတွက်မဟုတ်ဘဲ ရွေးထားသော 2D value များကိုသာ ပိတ်သည်။</li>
            <li><strong>Hot Numbers</strong> — ရွေးထားသော Round အတွက် Hot Number များထည့်/ဖယ်ရှားသည်။ ကိုက်ညီသော sale သည် reject ဖြစ်နိုင်သည်။</li>
            <li><strong>Agents / Round assignments</strong> — Agent ဖန်တီး/ပြင်ဆင်၊ Agent တစ်ဦးချင်း commission နှင့် ရွေးထားသော Round ၏ Amount Limit ကို ပြင်ဆင်သည်။</li>
            <li><strong>Amount Limit</strong> — အရောင်းပမာဏကို စောင့်ကြည့်ရန် threshold ဖြစ်သည်။ Sale Entry တွင် သတ်မှတ်ထားသည့်ပမာဏထက်ကျော်သော number များကို excess အများဆုံးမှစီကြည့်နိုင်သည်။ Sale ကို အလိုအလျောက် reject မလုပ်ပါ။</li>
            <li><strong>Configured number rules</strong> — W/N/X စာရင်းများကို ပြင်နိုင်ပြီး custom uppercase letter ဖြင့် ကိုယ့် 2D number list ဖန်တီးနိုင်သည်။ Rule တစ်ခုကို inactive လုပ်လျှင် sale input တွင် သုံးမရပါ။</li>
            <li><strong>Round close/reopen</strong> — Round စာရင်းတွင် Reopen လုပ်ပြီးနောက် အဲဒီ Round ကို <strong>Manually reopened</strong> ဟု ပြမည်။ <strong>Reclose reopened Round</strong> ကိုနှိပ်၍ သတ်မှတ်ပိတ်ချိန်ကျော်ပြီးသည့်အခါတွင်လည်း ချက်ချင်းပြန်ပိတ်နိုင်သည်။ ပိတ်ပြီးသည့် Agent session များ read-only ဖြစ်ပြီး scheduler က ထပ်မံမဖွင့်ပါ။ တစ်ပတ်ထက်ပိုဟောင်းသော manually reopened Round များလည်း စာရင်းထဲတွင် ဆက်ပြမည်။</li>
        </ul>
        <p>2D schedule တွင် 2D count limit မရှိပါ။ 3D count limit သည် သီးခြားဖြစ်သည်။</p>
    </section>

    <section id="settings-3d">
        <h2>Admin · 3D Setting များ</h2>
        <p><strong>Setting → 3D Settings</strong> မှ 3D payout multiplier နှင့် Agent တစ်ဦးချင်း၏ 3D commission ကို သတ်မှတ်ပါ။ Draw number limit သည် Draw တစ်ခုအတွင်း business အားလုံး၏ accepted 3D number အရေအတွက်ကို ကန့်သတ်သည်။ 3D Hot Number များသည် သုံးလုံးဖြစ်ရမည်။ နောင်လာမည့် Draw များ၏ limit/Hot Numbers ကို Draw day မတိုင်မီ ပြင်ဆင်ပါ။</p>
        <p>Round/Draw result များကို ပိတ်ပြီးနောက် သက်ဆိုင်ရာ 2D results and settlements သို့မဟုတ် 3D results and settlements အပိုင်းမှ ဖြည့်သွင်း၊ ပြင်ဆင်နိုင်သည်။ ပြင်ဆင်မှုများကို audit လုပ်ထားသည်။</p>
    </section>

    <section id="reports">
        <h2>Dashboard, Report နှင့် Agent Statement</h2>
        <ul>
            <li><strong>Dashboard</strong> သည် မိမိ role နှင့် scope အတွင်းရှိ အချက်အလက်များကို ပြသည်။</li>
            <li><strong>Report</strong> တွင် ကာလ၊ Agent၊ Round/Draw ကိုရွေးပြီး 2D နှင့် 3D ရလဒ်များကို ခွဲကြည့်နိုင်သည်။ Operator သည် မိမိခွင့်ပြုထားသောစာရင်းကိုသာမြင်သည်။</li>
            <li><strong>Menu → Agent Statement</strong> သည် လက်ရှိရွေးထားသော Agent ၏ accepted/rejected/excluded စာရင်းဖြစ်သည်။ Admin ၏ Live of items sold သည် Agent အားလုံးကို စုပေါင်းပြသည်။</li>
        </ul>
    </section>

    <section id="display">
        <h2>Menu · Display နှင့် Desktop လုပ်ဆောင်ချက်</h2>
        <ul>
            <li><strong>Theme</strong> တွင် System, Light, Dark ကိုရွေးပါ။</li>
            <li><strong>Font size</strong> တွင် Small, Standard, Large သို့မဟုတ် Extra large ကိုရွေးပါ။ ရွေးချယ်မှုကို အသုံးပြုနေသော browser/device ၏ local storage တွင် သိမ်းထားသည်။</li>
            <li>Clock နှင့် date သည် Yangon time ကိုပြသည်။ Active Round/Draw ပိတ်ချိန်နီးလာလျှင် countdown ကိုကြည့်နိုင်သည်။</li>
            <li>Desktop Sale Entry တွင် <kbd>F1</kbd> = Save, <kbd>F2</kbd> = Agent ရွေးရန်, <kbd>F3</kbd> = Input box သို့ပြန်သွားရန်၊ <kbd>Enter</kbd> = draft ထည့်ရန် ဖြစ်သည်။</li>
        </ul>
    </section>

    <section id="offline">
        <h2>Offline Sale Entry</h2>
        <p>Offline sales သည် VPS web app ၏ browser/device တစ်ခုတည်းပေါ်တွင်သာ ယာယီသိမ်းသည်။ Offline sales စာမျက်နှာကို Online ရှိနေစဉ် တစ်ကြိမ်ဖွင့်ထားပြီးမှ အင်တာနက်မရှိချိန်တွင် အသုံးပြုပါ။ အွန်လိုင်းပြန်ရလျှင် ကိုယ့် account ဖြင့်ဝင်ပြီး Sync ကို ကိုယ်တိုင်လုပ်ပါ။</p>
        <p>Sync ပြီးလျှင် sale တစ်ခုချင်းစီ၏ရလဒ်ကိုစစ်ပါ။ Offline queue သည် backup မဟုတ်ပါ၊ AppImage local database နှင့် VPS database ကို အလိုအလျောက် sync မလုပ်ပါ။</p>
    </section>

    <div class="manual-top"><a href="#manual-title">စာမျက်နှာထိပ်သို့</a></div>
</main>
@endsection
