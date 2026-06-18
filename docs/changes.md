# ملخص التعديلات والإضافات

هذا الملف يجمّع التعديلات التي أجريت على المشروع أثناء جلسة العمل الأخيرة، مع أوامر اختبار سريعة وروابط للملفات المعدّلة.

---

## 1) تهيئة البريد (MAIL)
- تم تحديث `/.env` لاستخدام SMTP:
  - `MAIL_MAILER=smtp`
  - `MAIL_HOST=live.smtp.mailtrap.io`
  - `MAIL_PORT=587`
  - `MAIL_USERNAME=api`
  - `MAIL_PASSWORD=b4196ed90bc8737a5d945ef374bea35a`
- تم إضافة قالب إنتاج: [.env.production](.env.production#L1-L60) مع `APP_URL=https://api.freetimedev.me`.
- تم التأكيد على اختبار الإرسال عبر Mailtrap أو مراجعة `storage/logs/laravel.log`.

## 2) استعادة كلمة المرور (Forgot / Reset)
- أُضيفت المكونات التالية لتمكين إرسال روابط إعادة الضبط عبر البريد:
  - `app/Notifications/ResetPasswordNotification.php` — إشعار البريد المرسل للمستخدم.
  - تعديل `app/Models/User.php` لإضافة `Notifiable` trait.
  - توسيع `app/Http/Controllers/Api/AuthController.php` بإضافتي:
    - `forgotPassword(Request $request)` — ينشئ توكن ويُرسل الإشعار. عند `APP_DEBUG=true` يُرجع التوكن في الاستجابة لمساعدة الاختبار المحلي.
    - `resetPassword(Request $request)` — يعالج إعادة تعيين كلمة المرور.
  - المسارات المضافة: `/api/auth/forgot-password` و `/api/auth/reset-password` في [routes/api.php](routes/api.php#L1-L20).
  - أدوات مساعدة للاختبار محليًا:
    - `tools/generate_reset_token.php`
    - `tools/reset_password.php`

- ملاحظة أمنيّة: في البداية كانت الاستجابة عامة (لتجنّب الكشف عن وجود المستخدم). بناءً على طلبك عدّلتها الآن لكي تُرجع رسالة عربية `"هذا البريد غير مسجل لدينا"` مع 404 عندما لا يوجد المستخدم.

## 3) إدارة البيئات والتشغيل
- تم إضافة/تحديث:
  - `.env.example` (قالب)
  - `.env.production` (قالب إنتاج)
  - أوامر في `composer.json`:
    - `start-local` — لتشغيل `php artisan serve` محليًا.
    - `deploy` — لأوامر التخزين المخبئي (config/route/view) في الإنتاج.
  - تحديث `README.md` لإرشادات التشغيل المحلي والإنتاجي.

## 4) تغييرات في المدفوعات (Payments)
- متطلبات جديدة: كل دفعة يجب أن ترتبط بفاتورة (لا يوجد دفع "دون فاتورة").
- تغييرات في الباكند:
  - `app/Http/Requests/Api/V1/Payment/RecordPaymentRequest.php` — الآن يطلب `invoice_id` بدل `party_id`.
  - `app/Domain/Store/DTOs/RecordPaymentDTO.php` — يحتوي الآن على `invoiceId`.
  - `app/Services/PaymentService.php`:
    - تغييرات: تحصيل العملاء الآن يطبّق على `SalesInvoice` (يربط القيود المالية والنقدية بالفاتورة ويحدّث `paid_amount` و `remaining_amount`).
    - دفع للموردين يطبّق على `PurchaseInvoice` بنفس الأسلوب.
    - أُضيفت وظائف: `listDirectPayments`, `updateDirectPayment`, `deleteDirectPayment` (لإدارة الفواتير/الدفعات المرتبطة).
  - `app/Http/Controllers/Api/SuperAdmin/PaymentController.php`:
    - أُضيفت نقاط نهاية (endpoints):
      - `GET /api/store/payments/customers/{id}`
      - `GET /api/store/payments/suppliers/{id}`
      - `PUT /api/store/payments/{id}`
      - `DELETE /api/store/payments/{id}`
    - تظلّ نقاط إنشاء الدفعات: `POST /api/store/payments/customer` و `/api/store/payments/supplier`، لكن الآن يجب تمرير `invoice_id`.

## 5) نقاط يجب تعديلها في الواجهة الأمامية (Frontend)
- واجهات الدفع الآن يجب أن ترسل `invoice_id` مع الطلبات بدلاً من `party_id`.
- استدعاءات API (إن كنت تستخدم `axios` مع `baseURL='/api'`) يجب أن تستخدم المسارات بدون `/api` مكرر، مثال: `axios.post('/store/payments/customer', payload)`.
- توفر endpoints جديدة لعرض/تعديل/حذف دفعات الفواتير؛ الواجهة يجب أن تُعرّض قائمة المدفوعات وتتيح تعديلها وحذفها مع التحقق من عدم تجاوز `amount > remaining_amount`.

## 6) أوامر اختبار سريعة
- اطلب توكن إعادة الضبط (local debug):
```bash
curl -X POST -H "Content-Type: application/json" \
  -d '{"email":"ahmed@agristore.com"}' \
  http://127.0.0.1:8001/api/auth/forgot-password
```
- نفّذ إعادة التعيين (باستخدام التوكن الناتج):
```bash
curl -X POST -H "Content-Type: application/json" \
  -d '{"email":"ahmed@agristore.com","token":"<TOKEN>","password":"newpass123","password_confirmation":"newpass123"}' \
  http://127.0.0.1:8001/api/auth/reset-password
```
- إنشـاء دفعة مرتبطة بفاتورة (مثال):
```bash
curl -X POST -H "Content-Type: application/json" -H "Authorization: Bearer <TOKEN>" \
  -d '{"invoice_id":123, "amount":150.5, "receipt_number":"RC-001" }' \
  http://127.0.0.1:8001/api/store/payments/customer
```
- جلب دفعات عميل:
```bash
curl -H "Authorization: Bearer <TOKEN>" \
  http://127.0.0.1:8001/api/store/payments/customers/45
```

## 7) ملفات رئيسية محدثة
- `app/Models/User.php`
- `app/Notifications/ResetPasswordNotification.php` (جديد)
- `app/Http/Controllers/Api/AuthController.php`
- `routes/api.php`
- `tools/generate_reset_token.php` (جديد)
- `tools/reset_password.php` (جديد)
- `app/Http/Requests/Api/V1/Payment/RecordPaymentRequest.php`
- `app/Domain/Store/DTOs/RecordPaymentDTO.php`
- `app/Services/PaymentService.php`
- `app/Http/Controllers/Api/SuperAdmin/PaymentController.php`
- `.env`, `.env.production`, `.env.example`, `composer.json`, `README.md`

---

إذا تريد أدرجت أيضاً مقطع التعليمات الخاص بالواجهة الأمامية (React / Vue) كمثال جاهز؟ أو أضع ملف MD آخر فيه أمثلة مكوّنات؟
