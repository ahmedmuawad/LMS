# 03 — المعمارية ونموذج البيانات

---

## 3.1 المخطط العام

```
                       ┌──────────────────────────────┐
   زوّار / طلاب  ─────▶ │  Web (Blade+Alpine, ثيم المشترك)│
                       │  /*  (عربي)  ·  /en/*         │
   مدرّسون      ─────▶ │  لوحة المدرّس (نواة Resource) │
   مشترك/إدارة  ─────▶ │  لوحة المشترك + Super Admin  │
   موبايل لاحقاً ────▶ │  REST API (Sanctum)          │
                       └──────────────┬───────────────┘
                                      │
                  ┌───────────────────▼────────────────────┐
                  │      Laravel 13 — Modular Monolith     │
                  │  Core │ Lms │ Commerce │ Services      │
                  │  Content │ Community │ Reporting       │
                  └───┬───────┬───────┬────────┬───────────┘
                      │       │       │        │
                  MySQL   Redis   Meilisearch  S3/Bunny
                            │
                    Horizon (Queues) · Reverb (WS) · Scheduler
                            │
              بوابات الدفع · البريد · SMS · Bunny Stream
```

---

## 3.2 النطاقات (Domains) والمسؤوليات

| الموديول | يملك | لا يملك |
|----------|------|---------|
| **Core** | المستخدمون، الأدوار، الإعدادات، الوسائط، الترجمات، الإشعارات، سجل التدقيق | أي منطق أعمال |
| **Lms** | الكورسات، الأقسام، الدروس، الاختبارات، الأسئلة، الواجبات، التسجيلات، التقدّم، الشهادات | التسعير والدفع |
| **Commerce** | المنتجات، السلة، الطلبات، المدفوعات، الكوبونات، الاشتراكات، الفواتير، الشحن | تسليم المحتوى |
| **Services** | الخدمات، الباقات، الحجوزات، التقويم، طلبات العملاء | الدفع |
| **Content** | المقالات، الصفحات، البلوكات، القوائم، البانرات، النماذج، الـ SEO | — |
| **Community** | الرسائل، النقاشات، التقييمات، الأسئلة والأجوبة | — |
| **Reporting** | لوحات المؤشرات، التقارير، التصدير | — |

**قاعدة صارمة:** التواصل بين الموديولات يتم عبر **Events** و**Contracts** فقط — لا استدعاء مباشر لموديل من موديول آخر.

مثال على التدفق الكامل لشراء كورس:
```
OrderPaid (Commerce)
   └─▶ EnrollUserInCourse (Lms)
   └─▶ SendPurchaseReceipt (Core)
   └─▶ GrantCommunityAccess (Community)
   └─▶ RecordRevenue (Reporting)
   └─▶ CreateInstructorCommission (Commerce)
```

---

## 3.3 نموذج البيانات — الجداول الأساسية

### أ. المستخدمون والصلاحيات
```
users
  id · name · email · phone · password · avatar_id · locale(ar|en)
  timezone · bio(json translatable) · headline(json) · country
  email_verified_at · phone_verified_at · two_factor_secret
  status(active|suspended|pending) · last_seen_at
  wp_user_id  ← مفتاح الترحيل
  meta(json) · timestamps

roles / permissions / model_has_roles      (spatie/laravel-permission)
  الأدوار: super_admin · admin · editor · instructor · assistant
           · support · student · customer

instructor_profiles
  user_id · slug · title(json) · about(json) · social(json)
  commission_rate · payout_method(json) · rating_avg · students_count
  is_verified · approved_at
```

### ب. الكورسات (Lms)
```
courses
  id · slug(json) · title(json) · excerpt(json) · description(json)
  cover_id · promo_video_id · instructor_id · category_id · level_id
  language · duration_minutes · status(draft|pending|published|archived)
  visibility(public|private|hidden) · enrollment_type(free|paid|invite)
  passing_percentage · certificate_template_id · badge_id
  drip_enabled · drip_strategy(sequential|by_date|by_days)
  access_days (0 = مدى الحياة) · max_students · starts_at · ends_at
  requirements(json) · outcomes(json) · target_audience(json)
  seo(json) · rating_avg · students_count · published_at
  wp_post_id ← الترحيل

course_sections
  id · course_id · title(json) · description(json) · position · is_free_preview

course_items                ← جدول موحّد لعناصر المنهج (polymorphic)
  id · course_id · section_id · position
  itemable_type(Lesson|Quiz|Assignment) · itemable_id
  is_preview · available_after_days · available_at

lessons
  id · title(json) · content(json) · type(video|audio|text|pdf|slides|live|scorm|embed)
  video_provider(bunny|youtube|vimeo|file) · video_id · duration_seconds
  attachments(json) · transcript(json) · is_downloadable · wp_post_id

quizzes
  id · title(json) · description(json) · type(static|dynamic)
  time_limit_minutes · max_attempts · passing_percentage
  shuffle_questions · shuffle_answers · show_answers(never|after_submit|after_pass)
  questions_count (للديناميكي) · question_pool(json)
  negative_marking · retake_delay_hours · wp_post_id

questions
  id · title(json) · body(json) · type · marks · negative_marks
  explanation(json) · category_id · difficulty(easy|medium|hard)
  options(json) · correct(json) · media_id · wp_post_id
  -- الأنواع: single_choice · multiple_choice · true_false · match
  --          sort · dropdown · fill_blank · short_text · essay · file_upload

quiz_question   (pivot: quiz_id · question_id · position · marks_override)

assignments
  id · title(json) · instructions(json) · attachments(json)
  max_marks · passing_marks · due_days · allow_late · max_file_mb
  allowed_extensions(json) · wp_post_id
```

### ج. التسجيل والتقدّم
```
enrollments
  id · user_id · course_id · order_item_id
  source(purchase|manual|bundle|subscription|import|free)
  status(active|completed|expired|suspended|refunded)
  progress_percent · started_at · completed_at · expires_at
  last_item_id · grade · certificate_id · wp_meta_ref

lesson_progress
  id · enrollment_id · lesson_id · status(not_started|in_progress|completed)
  watched_seconds · last_position_seconds · completed_at
  (فهرس فريد: enrollment_id + lesson_id)

quiz_attempts
  id · enrollment_id · quiz_id · attempt_no · status(in_progress|submitted|graded)
  started_at · submitted_at · time_spent_seconds
  score · max_score · percentage · passed · evaluated_by · evaluated_at
  answers(json)          ← نسخة كاملة للتدقيق
  snapshot(json)         ← نسخة من الأسئلة وقت المحاولة (مهم: الأسئلة قد تتغيّر لاحقاً)

quiz_answers
  id · attempt_id · question_id · answer(json) · is_correct
  marks_awarded · instructor_note(json)

assignment_submissions
  id · enrollment_id · assignment_id · attempt_no
  content · files(json) · submitted_at · status(pending|graded|resubmit)
  marks · feedback · corrected_file_id · graded_by · graded_at

certificates
  id · user_id · course_id · enrollment_id · template_id
  code (فريد, يُستخدم في صفحة التحقق العامة)
  issued_at · expires_at · pdf_path · revoked_at · data(json)

badges / user_badges
certificate_templates
  id · name · design(json)  ← تصميم بالسحب والإفلات: طبقات، حقول ديناميكية، خلفية
  page_size · orientation · locale
```

### د. التجارة (Commerce)
```
products                     ← المظلة الموحّدة لكل ما يُباع
  id · type(course|bundle|subscription|service|digital|physical)
  purchasable_type · purchasable_id      ← polymorphic
  sku · title(json) · slug(json) · short_desc(json) · description(json)
  price · sale_price · sale_starts_at · sale_ends_at · currency
  tax_class · is_taxable · manage_stock · stock_qty
  weight · dimensions(json)   ← للمنتجات المادية
  status · featured · seo(json) · wp_product_id

product_variants          (المقاسات/الألوان للمنتجات المادية)
carts / cart_items
orders
  id · number · user_id · status(pending|awaiting_payment|paid|processing
                                 |completed|cancelled|refunded|failed)
  currency · subtotal · discount · tax · shipping · total
  coupon_id · billing(json) · shipping(json) · notes
  placed_at · paid_at · ip · user_agent · wp_order_id

order_items
  id · order_id · product_id · purchasable_type · purchasable_id
  title_snapshot(json) · unit_price · qty · discount · tax · total
  fulfilled_at · instructor_id · commission_amount

payments
  id · order_id · gateway · gateway_ref · amount · currency
  status(pending|authorized|captured|failed|refunded)
  raw_request(json) · raw_response(json) · paid_at

refunds · invoices · coupons · coupon_usages · tax_rates · shipping_zones · shipments

subscriptions
  id · user_id · plan_id · status(trialing|active|past_due|cancelled|expired)
  current_period_start · current_period_end · cancel_at · gateway_ref
  renewal_attempts · next_retry_at

plans
  id · name(json) · interval(day|week|month|year) · interval_count
  price · trial_days · features(json) · included_courses(json)
  max_courses · grace_days

instructor_payouts · commission_rules
```

### هـ. الخدمات والحجوزات (Services)
```
services
  id · title(json) · description(json) · category_id · owner_id
  pricing_type(fixed|hourly|quote) · price · duration_minutes
  delivery_days · requires_booking · form_schema(json)
  cover_id · gallery(json) · faq(json) · status

service_packages          (باقات: أساسي/متقدم/احترافي)
  id · service_id · name(json) · price · delivery_days · features(json) · revisions

bookings
  id · service_id · package_id · user_id · order_item_id · provider_id
  starts_at · ends_at · timezone · status(pending|confirmed|in_progress
                                          |delivered|completed|cancelled|refunded)
  meeting_link · answers(json) · notes

availability_slots · service_requests · service_deliverables · service_revisions
```

### و. المحتوى (Content)
```
posts (المدونة)
  id · title(json) · slug(json) · excerpt(json) · content(json)
  cover_id · author_id · category_id · status · published_at
  reading_minutes · views · seo(json) · wp_post_id

pages
  id · title(json) · slug(json) · template
  blocks(json)     ← مخرجات الـ Page Builder
  status · seo(json) · parent_id · position · wp_post_id

blocks_library      (البلوكات الجاهزة والقوالب المحفوظة)
categories · tags · taggables   (polymorphic لكل الأنواع)
menus / menu_items  (قوائم متعدّدة اللغات مع تقييد بالصلاحية)
media
  id · disk · path · mime · size · width · height
  alt(json) · caption(json) · folder_id · uploaded_by · conversions(json)
forms / form_fields / form_submissions
redirects           ← جدول 301 القادم من الترحيل
seo_meta            ← polymorphic: title, description, og, canonical, robots, schema
```

### ز. المجتمع والإشعارات (Community / Core)
```
reviews             (polymorphic: course/product/service) · rating · comment · status
comments            (polymorphic, متداخلة, مع اعتدال)
discussions / discussion_replies      (نقاش على مستوى الدرس أو الكورس)
questions_answers   (اسأل المدرّس)
conversations / messages / participants
notifications       (Laravel القياسي) + notification_preferences (لكل مستخدم لكل قناة)
push_subscriptions  (Web Push)
announcements       (إعلانات على مستوى الكورس أو الموقع)
activity_log        (spatie)
```

### ح. النظام (Core)
```
settings            ← group · key · value(json) · is_translatable · locale
modules             ← key · name · enabled · version · settings(json)
translations        ← group · key · text(json)   [محرّر الترجمة من اللوحة]
languages           ← code · name · native_name · direction · is_default · is_active · flag
currencies          ← code · symbol · rate · is_default · position · decimals
email_templates     ← key · subject(json) · body(json) · variables(json) · enabled
scheduled_jobs      ← سجل تشغيل المهام
api_tokens · webhooks · integrations
```

---

## 3.4 قرارات تصميم مهمة (ولماذا)

| القرار | السبب |
|--------|-------|
| `course_items` جدول موحّد polymorphic | يسمح بترتيب حر لدروس واختبارات وواجبات في نفس القسم، ويسهّل السحب والإفلات |
| `quiz_attempts.snapshot` نسخة من الأسئلة | لو عدّل المدرّس السؤال بعد شهر، تبقى المحاولة القديمة قابلة للمراجعة بعدل |
| `order_items.title_snapshot` | الفاتورة يجب أن تعكس ما اشتراه العميل وقت الشراء لا ما تغيّر بعده |
| `products` كمظلة فوق كل شيء يُباع | سلة واحدة، كوبون واحد، تقرير مبيعات واحد لكل الأنواع |
| `wp_*_id` في كل جدول مُرحَّل | يسمح بترحيل تدريجي وإعادة تشغيل السكربت بأمان (idempotent) |
| الحقول المترجمة كـ JSON | يتجنّب JOINs في كل استعلام، ويبسّط الكاش |
| فصل `enrollments` عن `orders` | التسجيل قد يأتي من هدية أو منحة أو اشتراك أو استيراد، لا من طلب فقط |
| فهارس مركّبة على `(enrollment_id, lesson_id)` و`(user_id, course_id)` | أثقل استعلامين في أي LMS |

---

## 3.5 واجهات الـ API

```
/api/v1
  auth: register · login · logout · refresh · forgot · verify · social · passkey
  catalog: courses · categories · instructors · search · filters
  learning: my-courses · course/{id}/curriculum · lesson/{id}
            progress · quiz/{id}/start · quiz/attempt/{id}/answer · submit
            assignment/{id}/submit · certificates
  commerce: cart · checkout · orders · payments/{gateway}/callback
            subscriptions · invoices
  services: services · availability · bookings
  content: posts · pages · menus · settings/public
  account: profile · notifications · preferences · devices
```
- **Sanctum** للمصادقة (SPA cookies للويب، Tokens للموبايل)
- إصدارات `/v1` + `API Resources` + Rate limiting + توثيق **Scramble/OpenAPI** تلقائي
- كل استجابة تحترم `Accept-Language`

---

## 3.6 الأداء — الأهداف والوسائل

| المؤشّر | الهدف |
|---------|------|
| TTFB (صفحة مؤرشفة) | < 200ms |
| LCP | < 2.5s على 4G |
| بدء تشغيل الفيديو | < 2s |
| حفظ تقدّم الدرس | لا يحجب الواجهة (طابور خلفي) |

**الوسائل:** Octane/FrankenPHP · كاش صفحات الكتالوج · Eager loading صارم (منع N+1 عبر `preventLazyLoading`) ·
عدّادات مُجمّعة (`students_count`, `rating_avg`) بدل `COUNT()` لحظي · CDN للأصول · Lazy loading للصور ·
Meilisearch للبحث والفلترة بدل `LIKE` · معالجة الفيديو والشهادات والبريد في **طوابير**.
