# 02 — التقنيات المقترحة (Tech Stack)

كل اختيار هنا مكتوب بصيغة: **القرار → لماذا → البدائل التي رُفضت ولماذا**.

---

## 2.1 النواة (Core)

### القرار: Laravel 13 + PHP 8.4
- Laravel 13 صدر في **مارس 2026**، يتطلب PHP 8.3 كحد أدنى، ودعم أمني حتى **الربع الأول 2028**.
- يجلب: Passkeys مدمجة، PHP Attributes، `Cache::touch()`، Reverb database driver، وLaravel AI SDK مستقر.
- **البديل المرفوض:** Symfony (منحنى تعلّم أطول وسوق مطوّرين أصغر عربياً)، Node/NestJS (فريقك على الأرجح PHP بحكم الخلفية WordPress).

### القرار: Modular Monolith (وليس Microservices)
```
app/
  Core/            ← الإعدادات، الترجمة، الصلاحيات، الوسائط، الأحداث
  Modules/
    Lms/           ← الكورسات، الوحدات، الاختبارات، الشهادات
    Commerce/      ← المنتجات، السلة، الطلبات، الدفع، الاشتراكات
    Services/      ← الخدمات والحجوزات
    Content/       ← المدونة، الصفحات، Page Builder، القوائم
    Community/     ← الإشعارات، الرسائل، النقاشات
    Reporting/     ← التقارير والتحليلات
```
- كل موديول له: Models, Actions, Policies, Events, Migrations, Routes, Settings, Translations, Tests.
- **يمكن تفعيل/تعطيل كل موديول من لوحة التحكم** (يحاكي فكرة "الإضافات" في WordPress بدون فوضاها).
- **البديل المرفوض:** Microservices — تعقيد تشغيلي لا يبرّره حجم المشروع في هذه المرحلة.

**الحزم:** `nwidart/laravel-modules` أو تقسيم يدوي عبر PSR‑4 (نُفضّل اليدوي: تحكّم أكبر، تبعية أقل).

---

## 2.2 لوحة التحكم الإدارية

### القرار: Filament v4 (على Livewire 3)
- يوفّر جاهزاً: CRUD، جداول، فلاتر، نماذج، صلاحيات، رفع ملفات، ويدجت، تصدير، سجل تدقيق.
- **يختصر 60–70% من زمن بناء شاشات الإدارة.**
- نظام **Panels** يسمح بثلاث لوحات منفصلة على نفس الكود:
  - `/admin` — المدير
  - `/instructor` — المدرّس
  - `/account` — الطالب (أو نبنيها بواجهة Vue مخصّصة)
- دعم RTL و**تعدّد اللغات** في الواجهة الإدارية نفسها.
- **لماذا v4 وليس v5؟** Filament v5 (يناير 2026، على Livewire 4) أحدث، لكن **نظام الإضافات (plugins)** — وهو أهم أسباب اختيار Filament — لم ينضج بعد بنفس الدرجة على v5. نبدأ على v4 المستقر، ومسار الترقية إلى v5 موثّق ومتاح لاحقاً.
- **البديل المرفوض:** لوحة مخصّصة بالكامل (تكلفة 8–12 أسبوعاً إضافية بلا قيمة تجارية)، Nova (مدفوع وأقل مرونة).

---

## 2.3 الواجهة الأمامية

### القرار: Inertia 2 + Vue 3 + Tailwind CSS 4 + TypeScript
- **كود واحد** (لا فصل بين API وFront) → سرعة تطوير عالية وفريق واحد.
- **SSR مفعّل** عبر Inertia SSR → أرشفة ممتازة في جوجل (حاسم: الموقع الحالي مؤرشف).
- Vue أسهل على فريق قادم من PHP/jQuery مقارنة بـ React.
- **RTL/LTR** عبر Tailwind Logical Properties (`ms-`, `me-`, `ps-`, `pe-`) → قالب واحد يخدم العربية والإنجليزية.

### تقسيم الواجهات
| الواجهة | التقنية | السبب |
|---------|---------|-------|
| الصفحات التسويقية + المدونة + المتجر | Inertia + Vue **مع SSR** | SEO |
| مشغّل الكورس (Course Player) | Vue SPA داخل Inertia | تفاعلية عالية، حفظ تقدّم لحظي |
| لوحة الإدارة | Filament (Livewire/Blade) | سرعة بناء |
| تطبيق الموبايل مستقبلاً | نفس REST API + Sanctum | جاهزية من اليوم الأول |

- **البديل المرفوض:** Next.js منفصل (Headless) — أداء أفضل نظرياً لكن **مشروعين وفريقين ومصادقة مزدوجة**؛ لا يبرَّر إلا عند تجاوز مليون زيارة شهرياً.
- **البديل المرفوض:** Blade + Livewire فقط — ممتاز للإدارة، لكنه أضعف في مشغّل الفيديو والاختبارات التفاعلية.

---

## 2.4 تعدّد اللغات (عربي / إنجليزي) — متطلب أساسي

نفصل بين ثلاث طبقات، ولكل منها حل مختلف:

### أ. ترجمة الواجهة (نصوص النظام: الأزرار، الرسائل، القوائم)
- ملفات `lang/ar/*.php` و`lang/en/*.php` القياسية في Laravel.
- **محرّر ترجمات داخل لوحة التحكم** (`spatie/laravel-translation-loader`) → المدير يعدّل أي نص من الواجهة بدون مبرمج.
- استخراج تلقائي للنصوص المفقودة وتنبيه المدير بها.

### ب. ترجمة المحتوى (اسم الكورس، وصف المنتج، المقال، الصفحة)
- **الحل:** `spatie/laravel-translatable` — تخزين JSON داخل نفس العمود:
  ```php
  // courses.title = {"ar": "أساسيات لارفيل", "en": "Laravel Basics"}
  public array $translatable = ['title', 'slug', 'excerpt', 'description'];
  ```
- **لماذا JSON وليس جدول ترجمات منفصل؟** استعلامات أبسط، لا JOINs، ودعم فهرسة JSON في MySQL 8 / MariaDB 11.
- **Slug لكل لغة** → `/ar/courses/asasiyat-laravel` و`/en/courses/laravel-basics`.
- **حالة الترجمة لكل حقل:** (مترجم / ناقص / مسودة) تظهر كشارة في لوحة التحكم.

### ج. التوجيه والـ SEO متعدد اللغات
- بادئة لغة في المسار: `/ar/...` و`/en/...` (الافتراضي `ar`).
- وسوم `hreflang` تلقائية لكل صفحة + `x-default`.
- `Accept-Language` للكشف الأولي + تفضيل محفوظ في ملف تعريف المستخدم.
- تنسيق التواريخ والأرقام والعملات حسب اللغة (`Carbon` + `NumberFormatter`)، مع خيار **الأرقام العربية أو الهندية**.
- خطوط: `IBM Plex Sans Arabic` أو `Cairo` للعربية، `Inter` للإنجليزية.

### د. المحتوى غير المترجم
سياسة **Fallback** قابلة للضبط: (اعرض اللغة الأصلية) أو (أخفِ العنصر) — لكل نوع محتوى على حدة.

---

## 2.5 محرك التجارة (Commerce)

### القرار: محرك مخصّص خفيف داخل موديول `Commerce`
السبب: نحتاج **سلة واحدة** تبيع أنواعاً غير متجانسة:
`Course` · `CourseBundle` · `Subscription` · `Service` (بحجز) · `DigitalProduct` · `PhysicalProduct` (بشحن ومخزون)

نطبّق **Polymorphic Purchasable** — كل نوع ينفّذ واجهة موحّدة:
```php
interface Purchasable {
    public function priceFor(User $user, string $currency): Money;
    public function isAvailable(): bool;
    public function fulfil(Order $order, OrderItem $item): void;
}
```
- **البديل المرفوض:** Lunar (GetCandy) أو Bagisto — مصمّمة للمنتجات المادية، وإجبار الكورسات والاشتراكات داخلها يكلّف أكثر من بنائها.
- **البديل المرفوض:** ربط WooCommerce خارجي — يُبقينا في WordPress، وهو عكس هدف المشروع.

---

## 2.6 المدفوعات

### القرار: واجهة `PaymentGateway` موحّدة + مُشغّلات (Drivers)
```php
interface PaymentGateway {
    public function createIntent(Order $order): PaymentIntent;
    public function handleWebhook(Request $r): WebhookResult;
    public function refund(Payment $p, Money $amount): RefundResult;
}
```

| البوابة | السوق | الاستخدام |
|---------|-------|-----------|
| **Paymob** | مصر (+ الخليج) | البوابة الأساسية: بطاقات، محافظ، أقساط |
| **Fawry** | مصر | الدفع النقدي عبر المنافذ — ثقة عالية لدى المستخدم المصري |
| **Stripe** | دولي | البطاقات الدولية + **الاشتراكات المتكررة** |
| **PayPal** | دولي | تفضيل شريحة من العملاء |
| **Tap / Moyasar / HyperPay** | الخليج | تُضاف عند التوسّع (Mada, KNET) |
| **تحويل بنكي / فودافون كاش يدوي** | محلي | رفع إيصال + اعتماد يدوي من الإدارة |

**ملاحظة حرجة:** Paymob وFawry لا يدعمان الاشتراكات المتكررة بنفس نضج Stripe.
لذلك نبني **محرك اشتراكات داخلياً** (دورات فوترة، فترة سماح، محاولات إعادة تحصيل، إشعارات تجديد)
ونستخدم البوابة للتحصيل فقط — وهذا يجعلنا مستقلين عن أي بوابة.

**كل بوابة لها صفحة إعدادات كاملة** في لوحة التحكم (مفاتيح، وضع تجريبي/حقيقي، عملات مدعومة، رسوم، شرح للعميل، ترتيب الظهور، تفعيل/تعطيل).

---

## 2.7 الفيديو والوسائط

### القرار: Bunny Stream للفيديو + Bunny Storage / Cloudflare R2 للملفات
- **Bunny Stream:** أرخص خيار جدّي (~نصف تكلفة Cloudflare Stream)، مشغّل جاهز، ترميز HLS تلقائي، ترجمات، وDRM اختياري (MediaCage).
- **الحماية:** روابط موقّعة قصيرة العمر + تقييد Referrer + **علامة مائية باسم/بريد الطالب** + كشف المشاركة المتزامنة.
- **البدائل:** Cloudflare Stream (تسعير أوضح، بلا DRM استوديو)، Mux (الأفضل تقنياً والأغلى)، **استضافة ذاتية** (الأرخص عند الحجم الكبير جداً والأصعب تشغيلياً).
- الصور: رفع إلى S3‑compatible + معالجة عبر `spatie/laravel-medialibrary` + تحويل تلقائي إلى **WebP/AVIF** وأحجام متعددة.

> **قرار مؤجّل:** DRM كامل (Widevine/FairPlay) مكلف. نبدأ بالروابط الموقّعة + العلامة المائية، ونضيف DRM إذا ثبت وجود قرصنة فعلية.

---

## 2.8 Page Builder (محرر الصفحات المرئي)

### القرار: محرّر قائم على **بلوكات (Blocks)** بمكتبة مكوّنات محدودة — وليس Canvas حر
```json
{
  "blocks": [
    { "type": "hero", "props": { "title": {"ar":"...", "en":"..."}, "image": 42 } },
    { "type": "courses_grid", "props": { "category": 3, "limit": 8, "layout": "cards" } },
    { "type": "faq", "props": { "items": [...] } }
  ]
}
```
- المخرجات **JSON** في قاعدة البيانات، والعرض بمكوّنات Vue/Blade حقيقية → **لا HTML/CSS مبعثر** كما في Elementor.
- كل بلوك: خصائص مترجمة (ar/en)، إعدادات ظهور (ديسكتوب/تابلت/موبايل)، جدولة ظهور، تقييد بصلاحية.
- **مكتبة البلوكات المبدئية (~25 بلوك):** Hero · نص غني · صورة · معرض · فيديو · أزرار · شبكة كورسات · شريط كورسات · شبكة منتجات · خدمات · آراء العملاء · الأسئلة الشائعة · التسعير · العدّادات · الشعارات · المدرّسون · أحدث المقالات · نموذج تواصل · اشتراك بريدي · CTA · فاصل · تبويبات · أكورديون · جدول · كود مخصّص (للمدير فقط)
- **التقنية:** محرّر Vue مخصّص للسحب والإفلات (خفيف، متوافق RTL) أو **GrapesJS** إن أردنا تحكماً بصرياً أعمق.
- **البديل المرفوض:** استنساخ Elementor (canvas حر) — يُنتج صفحات مكسورة على الموبايل وRTL، وكابوس صيانة، ويستهلك 6+ أسابيع.
- **قوالب جاهزة (Presets):** صفحات كاملة جاهزة يختارها المدير ثم يعدّلها.

---

## 2.9 البنية الداعمة

| الحاجة | القرار | البديل |
|--------|--------|--------|
| قاعدة البيانات | **MySQL 8.4** / MariaDB 11 | PostgreSQL 17 (أقوى في JSON والتقارير) |
| الكاش والطوابير | **Redis 7 / Valkey** + `Laravel Horizon` | Database queue للبداية |
| البحث | **Meilisearch** عبر Laravel Scout | Typesense · Elasticsearch |
| الوقت الحقيقي | **Laravel Reverb** (WebSockets ذاتي الاستضافة) | Pusher · Soketi |
| إشعارات المتصفح | **Web Push (VAPID)** + PWA | Firebase FCM |
| الصلاحيات | `spatie/laravel-permission` (Roles + Permissions) | Policies يدوية |
| سجل التدقيق | `spatie/laravel-activitylog` | مخصّص |
| الإعدادات | `spatie/laravel-settings` + طبقة مخصّصة (انظر وثيقة 05) | جدول key/value |
| رفع الملفات | `spatie/laravel-medialibrary` | يدوي |
| PDF (شهادات/فواتير) | `spatie/laravel-pdf` (Browsershot) | DomPDF |
| البريد | Amazon SES / Postmark + قوالب قابلة للتحرير | SMTP |
| الرسائل النصية | Twilio / SMS Misr / Unifonic | — |
| الأداء | **Laravel Octane + FrankenPHP** | PHP‑FPM + OPcache |
| المراقبة | Sentry + **Laravel Pulse** + Telescope (تطوير) | New Relic |
| النسخ الاحتياطي | `spatie/laravel-backup` → S3 يومياً | سكربت مخصّص |

---

## 2.10 جودة الكود والتشغيل

| المجال | الأداة |
|--------|--------|
| الاختبارات | **Pest 3** — Feature + Unit + Browser (Playwright/Dusk) |
| التحليل الساكن | **Larastan** المستوى 6→8 تدريجياً |
| التنسيق | Laravel Pint + ESLint + Prettier |
| إعادة الهيكلة | Rector |
| CI/CD | **GitHub Actions**: pint → larastan → pest → build → deploy |
| البيئات | Docker (Laravel Sail محلياً) → Staging → Production |
| النشر | **Laravel Forge** أو **Coolify** على VPS (Hetzner/DigitalOcean) — أرخص وأبسط من Kubernetes في هذه المرحلة |
| الأسرار | متغيرات بيئة + Vault/Doppler |

---

## 2.11 الأمان

- 2FA للمدرّسين والإدارة (TOTP) + **Passkeys** (مدعومة أصلاً في Laravel 13)
- Rate limiting على الدخول والاختبارات والـ API
- CSP + HSTS + secure cookies + CSRF
- تشفير البيانات الحساسة (مفاتيح البوابات) على مستوى العمود
- سجل تدقيق كامل لكل عملية إدارية (من فعل ماذا ومتى)
- فحص تبعيات دوري (`composer audit`, `npm audit`, Dependabot)
- امتثال GDPR: تصدير بيانات المستخدم، وحق الحذف، وسجل الموافقات

---

## 2.12 ملخص الـ Stack في سطر واحد

> **Laravel 13 + PHP 8.4 + MySQL 8.4 + Redis + Meilisearch + Filament v4 (الإدارة) + Inertia 2/Vue 3/Tailwind 4 مع SSR (الواجهة) + Bunny Stream (الفيديو) + Paymob/Fawry/Stripe (الدفع) + Reverb (اللحظي) + Octane/FrankenPHP (الأداء)، بمعمارية Modular Monolith ثنائية اللغة (عربي/إنجليزي) بالكامل.**
