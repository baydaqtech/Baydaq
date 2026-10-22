# بيدق تك

موقع «بيدق تك» ومنصة بيدق (بوابة العملاء ولوحة الإدارة) بالعربية والإنجليزية، مبنية بـ Laravel 13 و React عبر Inertia، وتعمل داخل Docker مع MySQL.

## التشغيل

1. انسخ ملف الإعدادات (فيه متغيّرات Docker والمنفذ 8080)، واكتب فيه `ADMIN_NAME` و`ADMIN_EMAIL` و`ADMIN_PASSWORD` لحساب مالك النظام:

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
   docker compose exec laravel.test php artisan migrate --seed
   docker compose exec laravel.test php artisan storage:link
   docker compose exec laravel.test npm install
   docker compose exec laravel.test npm run build
   ```

4. افتح الموقع على: http://localhost:8080، ولوحة الإدارة على: http://localhost:8080/admin

## التطوير

- `docker compose exec laravel.test npm run dev`: خادم Vite مع التحديث الفوري.
- `docker compose exec laravel.test php artisan test`: تشغيل الاختبارات.
- `docker compose exec laravel.test php artisan queue:work`: إرسال الرسائل (الدعوات، تنبيهات الطلبات، إعادة تعيين كلمة المرور). محلياً تُكتب في `storage/logs/laravel.log`.
- قاعدة البيانات MySQL (خدمة `mysql` في `compose.yaml`)، والاختبارات على قاعدة `testing` في الخدمة نفسها.
- طلبات الشراكة والخدمة من الموقع تُحفظ في جدول `requests` وتظهر في لوحة الإدارة.

## هيكل المشروع

- `resources/js/Pages/Landing.jsx`: الصفحة الرئيسية وترتيب أقسامها.
- `resources/js/Pages/Admin/` و`resources/js/Pages/Portal/`: لوحة الإدارة وبوابة العملاء (shadcn/ui في `resources/js/components/ui/`).
- `lang/ar/` و`lang/en/`: كل نصوص الموقع والمنصة بالعربية والإنجليزية.
- `resources/js/components/`: مكوّنات الأقسام.
- `resources/js/lib/`: حركات الصفحة ولعبة الشطرنج وشعارات التقنيات.
- `resources/css/app.css`: كل تنسيقات الصفحة.
- `resources/views/partials/`: رموز قطع الشطرنج وشاشة التحميل.
- `brand/`: الشعار وملفات الهوية.
- `prototype/` و`baydaq-2d.html`: النماذج الأولية المرجعية.
