# دليل النشر (Deployment Runbook) — نظام إدارة الصيانة

مرحلة (8) من خطة المشروع: النشر والإطلاق الفعلي على سيرفر إنتاج.
هذا الدليل خطوة-بخطوة لأي مسؤول أنظمة (SysAdmin) لرفع نظام FixFlow وتشغيله على VPS
أو استضافة مشتركة تدعم Laravel، مع ضبط المجدول (Scheduler) وعامل الطابور (Queue Worker)
وشهادات SSL ونسخ احتياطي تلقائي لقاعدة البيانات.

> **الافتراضات**
> - السيرفر: Ubuntu 22.04+ (أو أي Linux يدعم PHP 8.3+).
> - PHP 8.5 + Composer + Node 22 + Nginx (يفضل) أو Apache.
> - MySQL / MariaDB لقاعدة البيانات الإنتاجية (SQLite ليست للإنتاج).
> - المشروع مسحوب من Git هو المصدر الوحيد؛ `.env` لا يُرفع مطلقًا.

---

## 1) متطلبات النظام (Requirements)

| الحزمة | السبب |
| --- | --- |
| PHP 8.3+ (مُوصى 8.5) | بناء Laravel |
| Composer 2 | تثبيت الاعتماديات |
| Node 22 + npm | بناء الواجهة (Vite) |
| MySQL / MariaDB 8+ | قاعدة البيانات |
| Nginx أو Apache | خادم الويب |
| Supervisor (اختياري) | تشغيل queue worker دائمًا |
| cron | تشغيل المجدول |

اختياري لكن مفيد: Redis (التخزين المؤقت/الطابور). النظام يعمل حاليًا بـ `database`
كطابور بدون Redis، فيمكنك تأجيلها.

---

## 2) إعداد السيرفر والاعتماديات

```bash
# تحديث النظام وتثبيت PHP 8.5 وملحقاته
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt install -y php8.5-cli php8.5-fpm php8.5-mysql php8.5-xml \
  php8.5-mbstring php8.5-curl php8.5-zip php8.5-gd php8.5-intl php8.5-bcmath \
  php8.5-sqlite3 unzip git curl nginx

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node 22 (لـ Vite)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

---

## 3) سحب الكود وتثبيت الاعتماديات وبناء الواجهة

```bash
# إنشاء مسار التطبيق
sudo mkdir -p /var/www/fixflow
sudo chown -R $USER:www-data /var/www/fixflow
cd /var/www/fixflow

# سحب الكود (استبدل الرابط برابط الريبو الفعلي)
git clone <REMOTE_URL> .
# أو: git init && git remote add origin <REMOTE_URL> && git pull origin main

# الاعتماديات
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci && npm run build
```

---

## 4) إعداد ملف البيئة (.env) وتوليد المفتاح

```bash
cp .env.example .env
php artisan key:generate
```

ثم حرّر `.env` بالمفاتيح التالية (النموذج `ادع .env.example` يوضح كل قيمة):

```ini
APP_NAME="نظام إدارة الصيانة"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://fixflow.example.com

# قاعدة البيانات — استخدم MySQL في الإنتاج
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fixflow
DB_USERNAME=fixflow
DB_PASSWORD=<قوة-وسرية>

# بيانات الأدمن الافتراضي (تُنشأ عند db:seed)
ADMIN_NAME="مدير النظام"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=<كلمة-قوية>

# البريد الفعلي
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=notifications@example.com
MAIL_PASSWORD=<سرية>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=notifications@example.com
```

ضع القيم الفعلية في `.env` على السيرفر فقط — لا تكتبها في Git.

---

## 5) إنشاء قاعدة البيانات والمستخدم

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE fixflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'fixflow'@'localhost' IDENTIFIED BY '<قوة-وسرية>';
GRANT ALL PRIVILEGES ON fixflow.* TO 'fixflow'@'localhost';
FLUSH PRIVILEGES;
```

> **هام**: جدول المستخدمين والأدوار يستخدم `utf8mb4` لدعم العربية بالكامل.

---

## 6) تشغيل الترحيلات والبذور (Migrate + Seed)

```bash
# إنشاء اتصال التخزين العام للمرفقات
php artisan storage:link

# تشغيل الترحيلات وإنشاء الأدوار + مستخدم الأدمن الافتراضي + الفئات/الأقسام/المواقع
php artisan migrate --force
php artisan db:seed --force
```

- `db:seed` ينشئ: الأدوار (Admin/Supervisor/Technician/Viewer) مع كل الصلاحيات،
  ومستخدم الأدمن الافتراضي من `ADMIN_EMAIL`/`ADMIN_PASSWORD` في `.env`.
- بعد تسجيل الدخول، غيّر كلمة مرور الأدمن وابدأ بإنشاء المستخدمين الحقيقيين.

---

## 7) ضبط الصلاحيات والملكية والذاكرة المؤقتة

```bash
sudo chown -R www-data:www-data /var/www/fixflow
sudo chmod -R 775 /var/www/fixflow/storage /var/www/fixflow/bootstrap/cache

# تسريع الإنتاج
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> عند تعديل `.env` أو الملفات تحت `config/` لاحقًا، أعد `php artisan config:cache`.

---

## 8) إعداد الخادم (Nginx)

أنشئ ملف `/etc/nginx/sites-available/fixflow`:

```nginx
server {
    listen 80;
    server_name fixflow.example.com;
    root /var/www/fixflow/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # منع الوصول المباشر لملف البيئة
    location ~ \.env$ { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/fixflow /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 9) المجدول (Scheduler / Cron)

الأمران المجدولان يعملان يوميًا عند منتصف الليل:
- `cmms:generate-work-orders` — إنشاء أوامر شغل وقائية للمواعيد المستحقة.
- `inventory:notify-low-stock` — تنبيه إداري بانخفاض مخزون قطع الغيار.

**الطريقة المُوصى بها**: أمر cron واحد يستدعي صانع الجدولة كل دقيقة، و Laravel نفسه
يشغّل كل المهام في أوقاتها الصحيحة من داخل هذا الاستدعاء:

```bash
crontab -e
```

أضف السطر (استبدل المسار بالمسار الفعلي):

```
* * * * * cd /var/www/fixflow && php artisan schedule:run >> /dev/null 2>&1
```

**البديل**: تشغيل دائم بـ `schedule:work` عبر Supervisor (انظر القسم 11).

---

## 10) عامل الطابور (Queue Worker)

التنفيذات التي تستخدم الطابور، مثل إرسال إشعارات البريد (تنبيه المخزون المنخفض)،
تحتاج عملية queue worker تعمل في الخلفية:

```bash
# للاختبار اليدوي (يعمل للأمام فقط):
php artisan queue:work --sleep=3 --tries=3
```

للتشغيل الدائم استخدم **Supervisor** (القسم 11).

---

## 11) Supervisor لإبقاء العامل والمجدول يعملان (اختياري لكن مُوصى)

```bash
sudo apt install -y supervisor
```

أنشئ `/etc/supervisor/conf.d/fixflow.conf`:

```ini
[program:fixflow-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/fixflow/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/var/www/fixflow
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/fixflow/storage/logs/worker.log
stopwaitsecs=3600

[program:fixflow-schedule]
command=php /var/www/fixflow/artisan schedule:work
directory=/var/www/fixflow
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/fixflow/storage/logs/schedule.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

> اختَر أحد الخيارين في القسمين 9 و11 للمجدول — إما cron مع `schedule:run`
> (الأبسط وأكثرها شيوعًا)، أو `schedule:work` عبر Supervisor (إبقاء دائم).

---

## 12) شهادة SSL (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d fixflow.example.com
# يجدد certbot تلقائيًا عبر cron/systemd timer

# اختبار التجديد التلقائي
sudo certbot renew --dry-run
```

بعد الإصدار، أعد تحميل nginx وسيتحول الموقع إلى HTTPS تلقائيًا مع إعادة توجيه 80→443.

---

## 13) النسخ الاحتياطي التلقائي لقاعدة البيانات

أنشئ سكربت `/var/www/fixflow/scripts/backup.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

DB_NAME="fixflow"
DB_USER="fixflow"
DB_PASS="<قوة-سرية>"
BACKUP_DIR="/backups/fixflow"
RETENTION_DAYS=14

mkdir -p "$BACKUP_DIR"

# نسخة MySQL مع الطابع الزمني
mysqldump -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
    | gzip > "$BACKUP_DIR/fixflow-$(date +%F-%H%M).sql.gz"

# حذف النسخ الأقدم من فترة الاحتفاظ
find "$BACKUP_DIR" -name "fixflow-*.sql.gz" -mtime +"$RETENTION_DAYS" -delete
```

```bash
chmod +x /var/www/fixflow/scripts/backup.sh

crontab -e
# أضف: تشغيل نسخة يومية الساعة 02:00 صباحًا
# 0 2 * * * /var/www/fixflow/scripts/backup.sh >> /var/log/fixflow-backup.log 2>&1
```

> **مهم**: انسخ النسخ الاحتياطية إلى موقع خارجي (منصة تخزين سحابية/S3/محرك أقراص آخر)
> حتى لا تفقدها عند حدوث مشكلة في السيرفر نفسه.

---

## 14) قائمة التحقق قبل الإطلاق (Deployment Checklist)

- [ ] `.env` على السيرفر بكل القيم الصحيحة و `APP_DEBUG=false`
- [ ] `composer install --no-dev` و `npm run build` نجحا
- [ ] `php artisan migrate --force` + `db:seed --force` عدلا بدون أخطاء
- [ ] مستخدم الأدمن قادر على تسجيل الدخول
- [ ] روابط التخزين: `php artisan storage:link`
- [ ] `config:cache` / `route:cache` / `view:cache` فعّالة
- [ ] cron أو Supervisor يشغّل المجدول
- [ ] Queue worker يعمل لتسليم الإشعارات
- [ ] شهادة SSL صالحة و HTTPS مفعّل
- [ ] النسخ الاحتياطي اليومي يعمل من سكربت الاختبار
- [ ] قفل الوصول: `location ~ \.env$ { deny all; }` (موجود في إعداد nginx أعلاه)
- [ ] تسجيل الدخول الفعلي للمستخدمين وتدريبهم (التدريب النهائي)

---

## 15) استكشاف الأخطاء الشائعة

| المشكلة | الحل |
| --- | --- |
| صفحة بيضاء/500 بعد النشر | شغّل `php artisan config:clear` وقد تحتاج للتأكد من ملكية `storage` و `bootstrap/cache` لمستخدم www-data |
| أخطاء عربية مشوّشة | تأكد أن الاتصال/الجدول `utf8mb4` وأن `MAIL_FROM_NAME` يدعم العربية |
| الصلاحيات (403) | تحقق أن المستخدم حصل على الدور الصحيح وأن `permission:cache-reset` نُفّذ |
| الإشعارات لا تصل | جرّب `php artisan inventory:notify-low-stock` يدويًا وتحقق من إعدادات SMTP والسجل |
| الجدول لا يُنشئ أوامر | تأكد أن الأمر المجدول يعمل: `php artisan cmms:generate-work-orders` ثم تحقق من `schedule:list` |
| نسخ البيانات | قم باسترجاع النسخة: `gunzip < fixflow-XXXX.sql.gz | mysql -u fixflow -p fixflow` |

---

النهاية — بعد إتمام هذا الدليل، سيكون النظام جاهزًا للاستخدام اليومي مع نسخ احتياطي
وتشغيل تلقائي للمهام المجدولة.
