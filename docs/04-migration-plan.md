# 04 — خطة الترحيل من WordPress/WPLMS إلى Laravel

> **المبدأ الحاكم:** الترحيل ليس استعلام SQL، بل **عملية ETL قابلة للتكرار** (Extract → Transform → Load)
> تُشغَّل عشرات المرات على نسخة اختبارية قبل أن تُشغَّل مرة واحدة على الإنتاج.

---

## 4.1 المتطلبات قبل البدء

| المطلوب | لماذا |
|---------|-------|
| نسخة كاملة من قاعدة بيانات WordPress (`.sql`) | مصدر البيانات |
| صلاحية قراءة على `wp-content/uploads` | الوسائط |
| قائمة الإضافات المفعّلة + الإصدارات | كشف البيانات المخفية |
| تصدير Google Search Console (أهم 1000 رابط) | حماية الأرشفة |
| تصدير طلبات WooCommerce | التسويات المالية |
| معرفة إن كان WooCommerce يستخدم **HPOS** | يحدد جدول الطلبات |
| بادئة الجداول (`wp_` أم مخصّصة) | صحة الاستعلامات |

---

## 4.2 خريطة التحويل (Mapping)

### المستخدمون
| WordPress | ← | Laravel |
|-----------|---|---------|
| `wp_users.ID` | → | `users.wp_user_id` |
| `user_login` / `user_email` | → | `users.email` (فحص التكرار) |
| `user_pass` (phpass `$P$...`) | → | `users.password` **كما هي** + علم `legacy_hash` |
| `display_name` / `first_name` / `last_name` | → | `users.name` |
| `wp_capabilities` | → | `roles` (subscriber→student, instructor→instructor, administrator→admin) |
| BuddyPress xProfile fields | → | `users.meta` / حقول البروفايل |
| `description` | → | `instructor_profiles.about` |

**كلمات المرور — الحل التقني:**
WordPress يستخدم `phpass`. Laravel لا يفهمها افتراضياً. الحل: **Hasher مخصّص**
```php
// عند تسجيل الدخول
if ($user->legacy_hash && (new PasswordHash(8, true))->CheckPassword($plain, $user->password)) {
    $user->forceFill([
        'password'     => Hash::make($plain),   // إعادة تجزئة بـ bcrypt
        'legacy_hash'  => false,
    ])->save();
    return true; // نجح الدخول
}
```
> النتيجة: **لا أحد يحتاج إعادة تعيين كلمة مروره**، والترحيل إلى bcrypt يحدث تلقائياً عند أول دخول.
> (ملاحظة: WordPress الحديث ينتقل إلى bcrypt أيضاً — يجب دعم الصيغتين.)

### الكورسات
| WordPress | ← | Laravel |
|-----------|---|---------|
| `wp_posts` حيث `post_type='course'` | → | `courses` |
| `post_title` / `post_content` / `post_excerpt` | → | `title` / `description` / `excerpt` (في مفتاح اللغة الأصلية) |
| `post_name` | → | `slug` |
| `_thumbnail_id` | → | `cover_id` بعد ترحيل الوسائط |
| `post_author` | → | `instructor_id` |
| taxonomy `course-cat` | → | `categories` |
| taxonomy `level` | → | `levels` |
| **`vibe_course_curriculum`** (مصفوفة مُسلسلة) | → | `course_sections` + `course_items` |
| `vibe_course_passing_percentage` | → | `passing_percentage` |
| `vibe_course_certificate` / `_template` | → | `certificate_template_id` |
| `vibe_course_badge*` | → | `badge_id` |
| `vibe_duration` | → | `access_days` / `duration_minutes` |
| `_vibe_forum` / `_vibe_group` | → | `discussions` (اختياري) |

**فك المنهج (أصعب جزء):**
```php
$curriculum = unserialize($meta['vibe_course_curriculum']);
// البنية النموذجية: مصفوفة مختلطة من IDs ونصوص عناوين الأقسام
// العنصر النصي (غير رقمي) = بداية قسم جديد
// العنصر الرقمي = ID لوحدة (unit) أو اختبار (quiz)
$section = null; $pos = 0;
foreach ($curriculum as $entry) {
    if (! is_numeric($entry)) {                 // عنوان قسم
        $section = CourseSection::create([...'title' => $entry]);
        $pos = 0;
        continue;
    }
    $post = $wpPosts[$entry] ?? null;           // unit أو quiz
    CourseItem::create([
        'course_id'     => $course->id,
        'section_id'    => $section?->id,
        'itemable_type' => $post->post_type === 'quiz' ? Quiz::class : Lesson::class,
        'itemable_id'   => $mapped[$post->ID],
        'position'      => $pos++,
    ]);
}
```
> ⚠️ صيغة `vibe_course_curriculum` تختلف بين إصدارات WPLMS.
> **إلزامي:** فحص البنية الفعلية في قاعدة العميل قبل كتابة السكربت النهائي، وكتابة اختبار على 10 كورسات حقيقية.

### الوحدات والاختبارات والأسئلة
| WordPress | ← | Laravel |
|-----------|---|---------|
| `post_type='unit'` | → | `lessons` (استخراج نوع المحتوى من الـ content/meta: iframe فيديو، رابط، نص) |
| `post_type='quiz'` + `vibe_quiz_duration_parameter` | → | `quizzes` |
| `post_type='question'` + meta الأسئلة | → | `questions` (تحويل بنية الخيارات إلى `options`/`correct` JSON) |
| ربط الأسئلة بالاختبار (meta مُسلسل) | → | `quiz_question` |
| بيانات الواجب في الوحدة/الاختبار | → | `assignments` |

**تحويل أنواع الأسئلة:**
| WPLMS | Laravel |
|-------|---------|
| Multiple choice single correct | `single_choice` |
| Multiple choice multiple correct | `multiple_choice` |
| Match Answers | `match` |
| Sort Answers | `sort` |
| Select Dropdown | `dropdown` |
| Fill in the Blank | `fill_blank` |
| Text | `short_text` |
| Essay | `essay` |

### التسجيلات والتقدّم
| المصدر | ← | Laravel |
|--------|---|---------|
| `wp_postmeta` على الكورس بمفتاح = رقم المستخدم | → | `enrollments` (وجود السجل = مسجَّل) |
| `wp_usermeta` مفتاح = رقم الكورس (timestamp) | → | `enrollments.expires_at` |
| `wp_usermeta.course_status{courseID}` | → | `enrollments.status` (1→active بدأ، 2→active مستمر، 3→completed تحت التقييم، 4→completed مُقيَّم) |
| `wp_bp_course_students` | → | تحقق متقاطع للتسجيلات |
| `wp_usermeta.badges` (مُسلسل) | → | `user_badges` |
| `wp_usermeta.certificates` (مُسلسل) | → | `certificates` |
| `wp_bp_activity` + `activity_meta` | → | نتائج الاختبارات + سجل التقدّم + سجل النشاط |

> ⚠️ **تقدّم الدروس ونتائج الاختبارات** أضعف جزء في بيانات WPLMS (مبعثر بين usermeta و activity meta).
> **قرار مطلوب من العميل:** هل نرحّل تاريخ المحاولات كاملاً، أم نكتفي بـ (نسبة التقدّم + الشهادات + الاجتياز)؟
> الخيار الثاني يوفّر 1–2 أسبوع عمل وهو كافٍ في 90% من الحالات.

### WooCommerce
| WordPress | ← | Laravel |
|-----------|---|---------|
| `wp_posts(shop_order)` أو `wp_wc_orders` (HPOS) | → | `orders` |
| `wp_woocommerce_order_items` + `itemmeta` | → | `order_items` |
| `post_type='product'` | → | `products` |
| ربط المنتج بالكورس (meta `vibe_course`) | → | `products.purchasable_*` |
| الكوبونات `shop_coupon` | → | `coupons` |
| حالات الطلب `wc-*` | → | `orders.status` |
| بيانات الفوترة/الشحن (meta `_billing_*`) | → | `orders.billing` / `shipping` (JSON) |

> الطلبات **تُرحَّل للقراءة والتاريخ فقط** — لا نعيد تشغيل أي عملية دفع.

### المحتوى والوسائط
| WordPress | ← | Laravel |
|-----------|---|---------|
| `post_type='post'` | → | `posts` |
| `post_type='page'` | → | `pages` |
| `post_type='attachment'` | → | `media` (نسخ الملفات إلى S3/Bunny) |
| التعليقات `wp_comments` | → | `comments` |
| التقييمات (WooCommerce reviews) | → | `reviews` |
| قوائم التنقل | → | `menus` (يدوياً — أسرع من الأتمتة) |
| صفحات Elementor/WPBakery | → | **إعادة بناء يدوية** بالـ Page Builder الجديد |

> **قرار واضح:** لا تُرحَّل صفحات Elementor آلياً. مخرجاتها HTML/CSS مضغوطة داخل shortcodes
> لا يمكن تحويلها إلى بلوكات نظيفة. نعيد بناء الصفحات المهمة يدوياً (عادةً 10–25 صفحة، 3–5 أيام).

---

## 4.3 التنفيذ

### أوامر الترحيل
```bash
php artisan wp:migrate --step=users        --dry-run
php artisan wp:migrate --step=media
php artisan wp:migrate --step=taxonomies
php artisan wp:migrate --step=courses
php artisan wp:migrate --step=curriculum
php artisan wp:migrate --step=quizzes
php artisan wp:migrate --step=enrollments
php artisan wp:migrate --step=progress
php artisan wp:migrate --step=commerce
php artisan wp:migrate --step=content
php artisan wp:migrate --step=redirects
php artisan wp:migrate:verify              # تقرير مقارنة شامل
```

**مبادئ إلزامية للسكربتات:**
1. **Idempotent** — إعادة التشغيل تُحدِّث ولا تُكرِّر (اعتماداً على `wp_*_id`).
2. **قابلة للاستئناف** — معالجة على دفعات (chunks) مع نقطة استئناف.
3. **سجل كامل** — كل صف فشل يُسجَّل في `migration_errors` بسببه، ولا يوقف العملية.
4. **تقرير تحقق** — يقارن الأعداد والمجاميع بين المصدر والوجهة.

### تقرير التحقق (Verification)
| الفحص | معيار القبول |
|-------|-------------|
| عدد المستخدمين | تطابق 100% |
| عدد الكورسات المنشورة | تطابق 100% |
| عدد الدروس لكل كورس | تطابق 100% |
| عدد التسجيلات النشطة | تطابق 100% |
| إجمالي مبيعات آخر 12 شهراً | فرق < 0.5% |
| عدد الشهادات الصادرة | تطابق 100% |
| الوسائط المنقولة | > 99% (ونُسجّل المفقود) |
| عيّنة يدوية | 20 طالباً × كورساتهم وتقدّمهم وشهاداتهم |

---

## 4.4 حماية الـ SEO (حرج)

الموقع الحالي مؤرشف في جوجل. أي خطأ هنا = **خسارة زيارات مباشرة**.

1. **جرد كامل للروابط:** من Search Console + `sitemap.xml` + زحف بـ Screaming Frog.
2. **خريطة إعادة توجيه** في جدول `redirects`:
   ```
   /courses/laravel-basics/            → /ar/courses/laravel-basics   [301]
   /course-cat/programming/            → /ar/courses?category=programming
   /?p=1234                            → /ar/blog/{slug}
   /product/xyz/                       → /ar/shop/xyz
   /members/{username}/                → /ar/instructors/{slug}
   ```
3. **الحفاظ على الـ slugs الأصلية** حيثما أمكن — أفضل من أي redirect.
4. **قرار بادئة اللغة:** الموقع القديم بلا بادئة. الخيار الأنظف: `/ar/*` هو الافتراضي مع 301 من الجذر،
   وإضافة `hreflang` صحيح. (البديل: إبقاء العربية بلا بادئة و`/en/*` للإنجليزية — أفضل للـ SEO لكنه أعقد في التوجيه.)
   **التوصية: العربية بلا بادئة + `/en/` للإنجليزية** ← يحافظ على كل الروابط القديمة كما هي.
5. نقل: `title` · `meta description` · Open Graph · **Schema.org** (Course, Product, Article, BreadcrumbList, FAQPage).
6. `sitemap.xml` جديد متعدد اللغات + `robots.txt` + إعادة إرسال في Search Console.
7. **مراقبة 60 يوماً** بعد الإطلاق: Search Console + مقارنة زيارات أسبوعية.

---

## 4.5 استراتيجية الإطلاق (Cutover)

**التوصية: Big‑Bang مع تجميد قصير** (الأبسط والأقل مخاطرة لموقع بهذا الحجم)

```
T-14 يوم │ ترحيل تجريبي كامل على Staging + مراجعة العميل
T-7      │ ترحيل تجريبي ثانٍ + اختبار قبول المستخدم (UAT)
T-3      │ تجميد إضافة المحتوى على الموقع القديم
T-1      │ نسخة احتياطية كاملة + بروفة الإطلاق (rehearsal)
T-0  02:00│ وضع الصيانة على القديم
     02:15│ تشغيل الترحيل النهائي (يستغرق 1–4 ساعات)
     04:00│ تقرير التحقق + فحص يدوي
     05:00│ تبديل DNS/Nginx إلى المنصة الجديدة
     05:30│ اختبار دخان: تسجيل · شراء · مشاهدة درس · اختبار · شهادة
     06:00│ رفع الصيانة + مراقبة مكثّفة 48 ساعة
```

**خطة التراجع (Rollback):** يبقى الموقع القديم قائماً وجاهزاً لمدة **30 يوماً**؛
التراجع = إعادة توجيه DNS فقط. أي شراء تم على الجديد يُرحَّل يدوياً للقديم (عددها صغير في أول ساعات).

**البديل (Strangler Fig):** نقل تدريجي (المدونة أولاً، ثم المتجر، ثم LMS) مع تشغيل النظامين معاً.
أكثر أماناً لكنه يتطلب **مزامنة مستخدمين وجلسات بين نظامين** — تكلفة إضافية 3–4 أسابيع.
يُنصح به فقط إذا كانت الإيرادات اليومية عالية جداً ولا تحتمل ساعة توقف.

---

## 4.6 المخاطر الخاصة بالترحيل

| الخطر | الاحتمال | الأثر | التخفيف |
|-------|---------|------|---------|
| بنية `vibe_course_curriculum` مختلفة عن المتوقع | **عالٍ** | عالٍ | فحص القاعدة الحقيقية في **الأسبوع الأول** قبل تثبيت التقديرات |
| نتائج اختبارات غير قابلة للاسترجاع بالكامل | عالٍ | متوسط | الاتفاق مسبقاً على ترحيل مختصر (تقدّم + اجتياز + شهادات) |
| بيانات مشوّهة (ترميز عربي `utf8` vs `utf8mb4`) | متوسط | عالٍ | تحويل الترميز في مرحلة Extract + فحص عيّنات عربية |
| ملفات وسائط مفقودة فعلياً على الخادم | متوسط | متوسط | تقرير بالمفقود + رفع بدائل قبل الإطلاق |
| إضافات مخصّصة تخزّن بيانات غير موثّقة | متوسط | عالٍ | جرد شامل لجداول ومفاتيح meta غير المعروفة في التدقيق الأولي |
| فقدان ترتيب أرشفة جوجل | متوسط | **عالٍ جداً** | خريطة 301 كاملة + الإبقاء على الروابط + مراقبة 60 يوماً |
| فشل دخول المستخدمين | منخفض | عالٍ جداً | Hasher مخصّص + اختبار على 50 حساباً حقيقياً قبل الإطلاق |
