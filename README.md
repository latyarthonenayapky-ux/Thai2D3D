# Winning Yields Always (WYA) — Thai2D3D

Winning Yields Always (WYA) သည် 2D နေ့စဉ် Round များနှင့် 3D လစဉ် Draw များအတွက်
sales entry, Agent စီမံခန့်ခွဲမှု၊ ရလဒ်၊ settlement နှင့် summary များကို စီမံသည့်
application ဖြစ်သည်။

> **⚠ လိုင်စင်အခြေအနေ — proprietary (ပုဂ္ဂလိကမူပိုင်) ဆော့ဖ်ဝဲ။**
> ဤ repository ကို အများပိုင် မြင်နိုင်ရန်သာ ထားရှိခြင်း ဖြစ်ပြီး **open source မဟုတ်ပါ။**
> ကုဒ်ကို ဖတ်/လေ့လာခွင့်သာ ရှိပြီး အသုံးပြု၊ ကူးယူ၊ ပြင်ဆင်၊ ဖြန့်ချိခွင့် မရှိပါ။
> အသေးစိတ် — [LICENSE.txt](LICENSE.txt) ။

## Development status

ဒီ repository သည် Laravel 12 project structure အပေါ် တည်ဆောက်နေစဉ် **early
skeleton** အဆင့်တွင် ရှိနေဆဲ ဖြစ်သည် — `app/Http`၊ `app/Models`၊ `config`၊ `routes`၊
`tests` တို့၏ base structure သာ ပါဝင်ပြီး 2D/3D business logic များမှာ ထည့်သွင်း
ရေးသားနေဆဲ ဖြစ်သည်။

## Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 (PHP ^8.2) |
| Frontend build | Vite + Tailwind CSS |
| Tests | PHPUnit (`phpunit.xml`, `tests/`) |
| Style | Laravel Pint |

## Local development

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build        # or: npm run dev
php artisan serve
```

Composer scripts အဖြစ် `composer setup` (install + env + key + migrate + npm build)၊
`composer dev` (serve + queue + logs + vite တစ်ပြိုင်နက်)၊ `composer test` တို့
ရရှိနိုင်သည်။

## Project layout

```
app/            Http controllers, models, providers
bootstrap/      framework bootstrapping
config/         environment-driven configuration
database/       migrations, factories, seeders
public/         entry point and built assets
resources/      Blade views, CSS/JS sources
routes/         web / api route definitions
tests/          PHPUnit suites
```

## Reporting and enquiries

| အရာ | လမ်း |
|---|---|
| Licensing / commercial use | waiyanaung.atm@gmail.com |
| Security issues | ထို email ပင် — private ကနေ တင်ပြပါ (public issue ဖြင့် မဖော်ပြပါနှင့်) |
| Contributions | လက်မှတ်ရေးထိုး contributor agreement မပါဘဲ **မလက်ခံပါ** — LICENSE.txt §4 |

## License

WYA နှင့် ဤ repository ထဲက source code အားလုံးသည် **proprietary — all rights
reserved** ဖြစ်သည်။ Copyright (c) 2026 Wai Yan Aung, trading as Winning Yields Always
(WYA)။ အသေးစိတ် — [LICENSE.txt](LICENSE.txt)။

ဒီ project သည် open-source components များပေါ်တွင် တည်ဆောက်ထားပြီး ၎င်းတို့၏ licence
များသည် ၎င်းတို့အတွက်သာ သက်ရောက်သည် — Laravel framework မှာ MIT licence ဖြစ်သည်။
ထို licence များက WYA ၏ ကိုယ်ပိုင် source code အပေါ် ခွင့်ပြုချက် မပေးပါ။
