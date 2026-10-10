# Thai2D3D ကို VPS ပေါ်တွင် တင်သွင်းအသုံးပြုရန်

ဤလမ်းညွှန်သည် Ubuntu Server 24.04 LTS၊ Nginx၊ PHP 8.3၊ MySQL နှင့် HTTPS
သုံးသည့် single-server စနစ်အတွက်ဖြစ်သည်။ VPS account, registered domain နှင့်
ဤ project ၏ source code ရရှိပြီးနောက် လိုက်နာပါ။ VPS တစ်လုံးဝယ်ထားရုံနှင့်
domain သို့မဟုတ် SSL certificate အလိုအလျောက်ရမည်မဟုတ်ပါ။

> **Database သီးခြားဖြစ်သည်။** VPS web app ၏ MySQL database သည် standalone
> AppImage တစ်ခုချင်းစီ၏ SQLite data နှင့် မချိတ်ဆက်ပါ။ AppImage မှ VPS သို့
> sale/account/database sync လုပ်ပေးသည့် feature မရှိပါ။ Online အသုံးပြုမည့်သူ
> အားလုံးက domain ကို browser မှတစ်ဆင့် ဖွင့်ပြီး VPS web app ထဲ login ဝင်ရပါမည်။

## 1. VPS နှင့် Domain ပြင်ဆင်ခြင်း

1. VPS provider မှ Ubuntu Server 24.04 LTS x86-64 server တစ်ခု provision လုပ်ပါ။
2. SSH key ဖြင့် ဝင်ရောက်နိုင်အောင် server ကိုပြင်ဆင်ပြီး provider firewall နှင့်
   Ubuntu firewall တွင် **SSH (22), HTTP (80), HTTPS (443)** ကိုသာ ဖွင့်ပါ။
   UFW enable မလုပ်မီ SSH ကို allow လုပ်ထားကြောင်း သေချာစစ်ပါ။
3. Domain ကို မှတ်ပုံတင်ပြီး DNS ထဲရှိ `A` record ကို VPS ၏ public IPv4 သို့
   ညွှန်ပါ။ IPv6 အသုံးပြုလျှင် မှန်ကန်သော public IPv6 အတွက် `AAAA` record ထည့်ပါ။
4. DNS ပြောင်းလဲမှု public DNS မှာ အလုပ်လုပ်ကြောင်းစစ်ပြီးမှ HTTPS certificate
   ထုတ်ပါ။

## 2. Server package များ install လုပ်ခြင်း

```sh
sudo apt update
sudo apt upgrade -y
sudo apt install -y nginx mysql-server composer git unzip curl cron \
  php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl
```

Ubuntu repository တွင် `php8.3-*` package များမတွေ့ပါက OS version နှင့် PHP
repository ကို အရင်ပြန်စစ်ပါ။ Production server ပေါ် PHP version မကိုက်ညီသော
package repository များကို မစမ်းသပ်ဘဲ ထပ်မပေါင်းပါနှင့်။ Composer 2၊ PHP 8.3၊
Nginx နှင့် MySQL/MariaDB persistent storage လိုအပ်သည်။ Node.js မလိုအပ်ပါ။

```sh
php -v
composer --version
sudo systemctl enable --now nginx mysql php8.3-fpm
sudo systemctl enable --now cron
```

SSH မှတစ်ဆင့် ချိတ်ထားစဉ် UFW ဖွင့်မည်ဆိုလျှင် SSH allow rule ကို အရင်ထည့်ပါ။

```sh
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status
```

SSH port ကို provider ကပြောင်းထားလျှင် သက်ဆိုင်ရာ port ကိုအရင် allow လုပ်ပါ။

## 3. Application source code ကို server သို့တင်ခြင်း

Project ကို public web root (`/var/www/html`) ထဲ တင်မထားပါနှင့်။ Nginx ၏
document root ကို `/var/www/thai2d3d/public` သို့သာ သတ်မှတ်ပြီး `.env` ဖိုင်ကို
public directory ထဲ မထားပါနှင့်။

VPS ပေါ်တွင် application directory ပြင်ဆင်ပါ။

```sh
sudo mkdir -p /var/www/thai2d3d
sudo chown -R "$USER":www-data /var/www/thai2d3d
```

ဤ project ကို upload လုပ်နိုင်သော development ကွန်ပျူတာမှ အောက်ပါပုံစံဖြင့်
source code ကို ပို့နိုင်သည် (VPS user/IP ကို ကိုယ့်တန်ဖိုးဖြင့် အစားထိုးပါ)။
VPS SSH key နဲ့ဝင်လို့ရကြောင်း ကြိုစမ်းထားပါ။ `.env`, Git metadata,
dependencies, local log နှင့် AppImage ကို VPS သို့ မပို့ပါ။

```sh
cd /path/to/Thai2D3D-clean-20261009
rsync -avz \
  --exclude='/.git/' \
  --exclude='/.env' \
  --exclude='/vendor/' \
  --exclude='/desktop/' \
  --exclude='/Thai2D3D-1.1.0.AppImage' \
  --exclude='/storage/logs/***' \
  --exclude='/storage/framework/views/***' \
  --exclude='/storage/framework/cache/***' \
  --exclude='/storage/framework/sessions/***' \
  ./ VPS_USER@VPS_IP:/var/www/thai2d3d/
```

Source code မရှိသေးပါက deployment မစတင်ခင် project owner ထံမှ အတည်ပြုထားသော
source archive သို့မဟုတ် repository ကို ရယူပါ။ ယခင် AppImage binary ကို VPS
source code အဖြစ် မသုံးပါနှင့်။

## 4. MySQL database ပြင်ဆင်ခြင်း

Server ပေါ်တွင် MySQL administrative shell ဖွင့်ပါ။

```sh
sudo mysql
```

Prompt အတွင်း database နှင့် local-only app user ဖန်တီးပါ။ `REPLACE_WITH_A_LONG_RANDOM_PASSWORD`
ကို ကိုယ်ပိုင် password manager ထဲက long random password ဖြင့် အစားထိုးပြီး
သိမ်းထားပါ။ Password အမှန်ကို source, chat, screenshot, shell script သို့မဟုတ်
Git တွင် မထည့်ပါနှင့်။

```sql
CREATE DATABASE thai2d3d CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'thai2d3d_app'@'localhost'
  IDENTIFIED BY 'REPLACE_WITH_A_LONG_RANDOM_PASSWORD';
GRANT ALL PRIVILEGES ON thai2d3d.* TO 'thai2d3d_app'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Database port `3306` ကို internet သို့ မဖွင့်ပါနှင့်။ App နှင့် MySQL တူညီသော
VPS ပေါ်ရှိသောကြောင့် `localhost` connection ကိုသာ အသုံးပြုပါ။

## 5. Environment နှင့် first Owner account

```sh
cd /var/www/thai2d3d
cp .env.example .env
nano .env
```

`.env` ထဲ အောက်ပါ key များကို ပြင်ပါ။ Password ကို MySQL အတွက်ဖန်တီးထားသော
တန်ဖိုးဖြင့် အစားထိုးပါ။ `APP_KEY` ကို အောက်က `key:generate` command ဖြင့်
ထုတ်မည်ဖြစ်သောကြောင့် အစပိုင်းတွင် ဗလာထားနိုင်သည်။

```dotenv
APP_NAME=Thai2D3D
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR_DOMAIN
APP_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=thai2d3d
DB_USERNAME=thai2d3d_app
DB_PASSWORD=YOUR_LONG_RANDOM_DATABASE_PASSWORD

SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=sync

MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=YOUR_SMTP_HOST
MAIL_PORT=587
MAIL_USERNAME=YOUR_SMTP_USERNAME
MAIL_PASSWORD=YOUR_SMTP_PASSWORD
MAIL_FROM_ADDRESS=YOUR_VERIFIED_SENDER_ADDRESS
MAIL_FROM_NAME="Thai2D3D"
```

`APP_URL` တွင် `https://` နှင့် ကိုယ်ပိုင် domain ကို အတိအကျသုံးပါ။ `APP_KEY`
ကို ပထမဆုံး install လုပ်ချိန်တွင်သာ generate လုပ်ပါ။ Existing database/app
အတွက် `key:generate` ကို ထပ်မ run ပါနှင့်၊ key ပြောင်းလဲလျှင် session/cookie များ
ဖတ်မရတော့နိုင်ပါ။ Password
reset email သုံးမည်ဆိုလျှင် SMTP provider ပေးသော host, port, scheme, username,
password နှင့် verified sender ထည့်သွင်းပါ။ Email ပံ့ပိုးမှု မပြင်ဆင်ရသေးလျှင်
password reset ကို သုံး၍မရနိုင်သဖြင့် SMTP ကို live အသုံးမပြုမီ စမ်းပါ။

```sh
mkdir -p storage/framework/cache/data storage/framework/sessions \
  storage/framework/views storage/logs bootstrap/cache
composer install --no-dev --prefer-dist --optimize-autoloader
sudo chown -R "$USER":www-data /var/www/thai2d3d
sudo find storage bootstrap/cache -type d -exec chmod 2775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
chmod 640 .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan thai2d3d:make-owner
```

Owner command မှ အမည်၊ email နှင့် အနည်းဆုံး 12-character password မေးပါမည်။
Password ကို terminal မှ လျှို့ဝှက် input အဖြစ်ထည့်ပါ။ ဒီ command သည် user account
တစ်ခုခုရှိနေပါက ထပ်မလုပ်နိုင်ပါ။ အရင်က Owner သို့မဟုတ် account ရှိပြီးသား
database ကို စစ်ဆေးခြင်းမရှိဘဲ fresh install ကဲ့သို့ ဆက်မလုပ်ပါနှင့်။

## 6. HTTPS Nginx virtual host

Domain ကို DNS ဖြင့် VPS IP သို့ ညွှန်ပြီးနောက် Nginx site file ဖန်တီးပါ။
`YOUR_DOMAIN` ကို domain အမှန်ဖြင့် အစားထိုးပါ။

```sh
sudo nano /etc/nginx/sites-available/thai2d3d
```

ဖိုင်ထဲ အောက်ပါ configuration ထည့်ပါ။

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name YOUR_DOMAIN;
    root /var/www/thai2d3d/public;
    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Site ဖွင့်ပြီး config စစ်ဆေးပါ။

```sh
sudo ln -s /etc/nginx/sites-available/thai2d3d /etc/nginx/sites-enabled/thai2d3d
sudo nginx -t
sudo systemctl reload nginx
```

`/etc/nginx/sites-enabled/default` မှာ default site ရှိလျှင် server name တူညီမှု
သို့မဟုတ် default site ကို မိမိအတည်ပြုပြီးမှ disable လုပ်ပါ။ DNS စစ်ဆေးပြီးနောက်
Let's Encrypt certificate ထုတ်ပါ။

```sh
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d YOUR_DOMAIN
sudo certbot renew --dry-run
```

Certbot wizard က email, terms နှင့် HTTPS redirect ကို သတ်မှတ်ခိုင်းပါမည်။
Certificate renew dry-run အောင်မြင်ကြောင်း အတည်ပြုပါ။

## 7. Scheduler ဖွင့်ပြီး service စစ်ဆေးခြင်း

Application သည် daily Round နှင့် 1/16 ရက် 3D Draw schedule ကို စစ်ဆေးဖို့
Laravel scheduler ကို **မိနစ်တိုင်း** run ရသည်။

```sh
sudo crontab -u www-data -e
```

Crontab ထဲ ဒီတစ်ကြောင်းထည့်ပါ။

```cron
* * * * * cd /var/www/thai2d3d && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Production cache ဖန်တီးပြီး service များ restart/reload လုပ်ပါ။

```sh
cd /var/www/thai2d3d
php artisan optimize
sudo systemctl restart php8.3-fpm
sudo systemctl reload nginx
```

Browser မှ `https://YOUR_DOMAIN/up` ကိုဖွင့်ပြီး healthy response ရကြောင်း
စစ်ဆေးပါ။ HTTPS certificate warning မရှိဘဲ `/login` ပွင့်ကြောင်း၊ Owner login
ဝင်နိုင်ကြောင်း အတည်ပြုပါ။ PHP/Nginx error များအတွက်
`/var/log/nginx/error.log` နှင့် `storage/logs/laravel.log` ကို စစ်ပါ။

## 8. စတင်အသုံးပြုမည့်အဆင့်

1. Owner account ဖြင့် ဝင်ပါ။
2. **User access** မှ Admin account ဖန်တီးပါ။ 7 ရက်သက်တမ်းရှိ Temp Admin ကိုလည်း
   controlled field test အတွက် ရွေးချယ်နိုင်သည်။
3. Admin account ဖြင့်ဝင်ပြီး Operators နှင့် Agents ဖန်တီးပါ။
4. **2D Settings** တွင် 2D payout multiplier၊ သုံးနေ့စဉ် Round တစ်ခုချင်းစီ၏
   Round တစ်ခုချင်းစီအတွက် close/start time သတ်မှတ်ပါ။ 2D Number Limit ကို
   Sale Entry တွင် 00–99 အတွင်း ပိတ်လိုသော value များအဖြစ် သတ်မှတ်ပါ။
   Start time ဗလာထားလျှင် ရှေ့ Round ပိတ်ချိန်မှာ နောက် Round အလိုအလျောက်စသည်။
5. **3D Settings** တွင် 3D payout multiplier၊ အနာဂတ် Draw တစ်ခုချင်းစီ၏
   3D Number Limit နှင့် Hot Numbers သတ်မှတ်ပါ။ 3D Draw သည် လစဉ် 1 ရက်နှင့်
   16 ရက်ဖြစ်ပြီး Yangon time 15:30 ပိတ်သည်။
6. Operator သည် login ဝင်၊ 2D/3D mode ရွေး၊ မိမိလုပ်မည့် Agent ကို claim လုပ်ပြီး
   Sale Entry ကို စမ်းသပ်ပါ။ Claim တစ်ခုသည် သက်ဆိုင်ရာ Round သို့မဟုတ် Draw
   အတွက်သာဖြစ်ပြီး ထို Round/Draw အတွင်း အခြားသူက ထပ် claim မလုပ်နိုင်ပါ။
7. Test Round/Draw ပိတ်ပြီးနောက် result၊ settlement နှင့် report ကို စစ်ပါ။
   Controlled test မပြီးမချင်း အမှန်တကယ်လုပ်ငန်း data မသွင်းပါနှင့်။

## 9. Backup, update နှင့် recovery

အစပိုင်းတွင် MySQL dump ကို daily ထုတ်ပြီး server အပြင်ဘက်ရှိ access-controlled
နေရာသို့ ကူးပါ။ `.env` ကိုလည်း သီးခြား encrypted/secret storage ထဲတွင် backup
လုပ်ပါ။ Backup ဖိုင်များကို web root ထဲ မထားပါနှင့်၊ permission ကို user-only
ထားပါ။ Restore procedure ကို စမ်းမထားသော backup ကို အာမခံချက်ရှိသော backup
ဟု မယူဆပါနှင့်။

ဥပမာ dump (password prompt ကိုသုံးသည်၊ terminal command ထဲ password မထည့်ပါ):

```sh
sudo install -d -o "$USER" -g "$USER" -m 700 /var/backups/thai2d3d
set -o pipefail
mysqldump --single-transaction -h 127.0.0.1 -u thai2d3d_app -p thai2d3d \
  | gzip > "/var/backups/thai2d3d/db-$(date +%F-%H%M).sql.gz"
chmod 600 /var/backups/thai2d3d/db-*.sql.gz
```

Backup ကို recovery အတွက်သုံးမည်ဆိုလျှင် target database မှန်ကန်ကြောင်းနှင့်
restore လုပ်ခြင်းက ထို database ၏လက်ရှိ data ကို အစားထိုး/ပြောင်းလဲမည်ဖြစ်ကြောင်း
အရင်စစ်ပါ။ Production မဟုတ်သော test database ပေါ်တွင် restore ကို စမ်းထားပါ။

```sh
set -o pipefail
gzip -dc /secure/path/to/verified-backup.sql.gz \
  | mysql -h 127.0.0.1 -u thai2d3d_app -p thai2d3d
```

Update တင်မီ DB backup ထုတ်ပါ။ အတည်ပြုထားသော code update တင်ပြီးမှ အောက်ပါ
အတိုင်း run လုပ်ပါ။

```sh
cd /var/www/thai2d3d
php artisan down
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize
sudo systemctl restart php8.3-fpm
sudo systemctl reload nginx
php artisan up
```

Update step တစ်ခုခု error ဖြစ်ပါက ပြဿနာကို စစ်ဆေးပါ။ မပြေလည်သေးပါက
`php artisan up` ဖြင့် maintenance mode ပိတ်ပြီး site ပြန်ဖွင့်ပါ။ Migration များကို
backup မရှိဘဲ rollback မလုပ်ပါနှင့်။ Source code version နှင့် DB migration အခြေအနေ
ကို မှတ်တမ်းတင်ထားပါ။

## Go-live မလုပ်မီ

- HTTPS, domain/DNS, `/up`, Owner/Admin/Operator login ကို စစ်ဆေးပါ။
- Nginx document root က `public/` ကိုသာ ညွှန်ကြောင်းနှင့် `.env` internet မှ
  ဖတ်မရကြောင်း စစ်ပါ။
- Cron အမှန်တကယ်အလုပ်လုပ်ပြီး Yangon time အတိုင်း Round/Draw အခြေအနေ
  ပြောင်းလဲနေကြောင်း စစ်ပါ။
- Accepted/rejected/Hot Number/blocked 2D value နှင့် 3D count-limit offline sync review ကို
  controlled test ဖြင့်စမ်းပါ။ Amount Limit သည် monitoring သာဖြစ်ပြီး sale ကို
  reject လုပ်မည့် threshold မဟုတ်ပါ။
- 2D/3D result, payout, commission, settlement ကို အချက်အလက်နမူနာဖြင့်ပြန်တွက်ပါ။
- DB backup restore၊ SMTP password reset၊ SSH access recovery ကို စမ်းပါ။

ဤ server setup လမ်းညွှန်သည် operator-specific VPS တစ်လုံးအတွက်ဖြစ်သည်။
VPS provider, domain, DNS panel နှင့် PHP/Nginx configuration ကိုမသိသေးသဖြင့်
server provision၊ account/price ရွေးချယ်ခြင်း သို့မဟုတ် DNS setting ကို အလိုအလျောက်
ပြောင်းမပေးပါ။
