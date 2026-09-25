# بيدق تكنولوجي

الموقع التعريفي لشركة «بيدق تكنولوجي»: صفحة عربية واحدة من اليمين إلى اليسار، مبنية بـ Laravel 12 و React عبر Inertia، وتعمل داخل Docker.

## التشغيل

1. انسخ ملف الإعدادات (فيه متغيّرات Docker والمنفذ 8080):

   ```sh
   cp .env.example .env
   ```

2. شغّل الحاويات:

   ```sh
   docker compose up -d --build
   ```

   > سكربت `./vendor/bin/sail` لا يعمل في Git Bash على ويندوز، لذلك نستخدم `docker compose` مباشرة. في Git Bash أضف `MSYS_NO_PATHCONV=1` قبل أوامر `docker compose exec`.

3. أول مرة فقط: ثبّت الحزم وجهّز قاعدة البيانات:

   ```sh
   docker compose exec laravel.test composer install
   docker compose exec laravel.test php artisan key:generate
   docker compose exec laravel.test php artisan migrate
   docker compose exec laravel.test npm install
   docker compose exec laravel.test npm run build
   ```

4. افتح الموقع على: http://localhost:8080

## التطوير

- `docker compose exec laravel.test npm run dev`: خادم Vite مع التحديث الفوري.
- `docker compose exec laravel.test php artisan test`: تشغيل الاختبارات.
- رسائل نموذج التواصل تُحفظ في جدول `contact_messages`، وقاعدة البيانات SQLite.

## هيكل المشروع

- `resources/js/Pages/Landing.jsx`: الصفحة وترتيب أقسامها.
- `resources/js/components/`: مكوّنات الأقسام.
- `resources/js/lib/`: حركات الصفحة ولعبة الشطرنج وشعارات التقنيات.
- `resources/css/app.css`: كل تنسيقات الصفحة.
- `resources/views/partials/`: رموز قطع الشطرنج وشاشة التحميل.
- `brand/`: الشعار وملفات الهوية.
- `prototype/` و`baydaq-2d.html`: النماذج الأولية المرجعية.
