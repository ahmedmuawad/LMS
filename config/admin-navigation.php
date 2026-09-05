<?php

declare(strict_types=1);
use App\Core\Access\Ability;

/*
 | قوائم لوحة المشترك.
 |
 | كل عنصر مرتبط بموديول و/أو ميزة باقة:
 |   module  → لا يظهر إن كان الموديول غير مفعّل لهذا النمط (ADR-010)
 |   feature → يظهر مقفولاً مع زر ترقية إن كان خارج الباقة (ADR-011)
 |
 | الفرق مقصود: ما لا يخصّ نمطه يُخفى، وما تمنعه باقته يُعرض مقفولاً —
 | الميزة المخفية لا تُباع.
 */

/*
 | ملاحظة صيانة: كل عنصر هنا يجب أن يفتح شاشة تعمل.
 |
 | اختبار «لا رابط مكسور في قائمة المشترك» يطرق كل رابط في النمطين
 | ويفشل على أول 404 — فلا يُضاف عنصر قبل شاشته، ولا تُحذف شاشة
 | ويُترك عنصرها. العناصر المرفوعة (الحصص المباشرة · المدرّسون ·
 | المجتمع · المظهر · الموديولات · أولياء الأمور · المخزون · SCORM)
 | تعود مع شاشاتها.
 */

return [

    'groups' => [

        [
            'label' => 'نظرة عامة',
            'items' => [
                ['key' => 'dashboard', 'label' => 'اللوحة', 'icon' => '◧', 'route' => 'admin.dashboard'],
                ['key' => 'reports', 'label' => 'التقارير', 'icon' => '◔', 'module' => 'reports', 'route' => 'admin.reports.index'],
                ['key' => 'statistics', 'label' => 'الإحصاءات', 'icon' => '◕', 'module' => 'lms', 'route' => 'admin.instructor.statistics', 'ability' => Ability::STATISTICS_VIEW],
            ],
        ],

        [
            'label' => 'التعليم',
            'items' => [
                /*
                 | الكورس هو المنهج المرتَّب الذي يشتريه الطالب؛ ومكتبة الدروس
                 | هي المحتوى الخام (فيديو · نصّ · ملف) الذي يُركَّب منه
                 | أكثر من كورس. التسمية تقول ذلك لا التخمين.
                 */
                ['key' => 'courses', 'label' => 'الكورسات', 'icon' => '▤', 'module' => 'lms'],
                ['key' => 'lessons', 'label' => 'مكتبة الدروس', 'icon' => '▶', 'module' => 'lms'],
                ['key' => 'quizzes', 'label' => 'الاختبارات', 'icon' => '◫', 'module' => 'quizzes'],
                ['key' => 'questions', 'label' => 'بنك الأسئلة', 'icon' => '❓', 'module' => 'quizzes'],
                ['key' => 'assignments', 'label' => 'الواجبات', 'icon' => '✎', 'module' => 'assignments'],
                ['key' => 'grading', 'label' => 'التصحيح', 'icon' => '✔', 'module' => 'lms', 'route' => 'admin.grading.index'],
                ['key' => 'certificates', 'label' => 'الشهادات', 'icon' => '◈', 'module' => 'certificates'],
                ['key' => 'enrollments', 'label' => 'التسجيلات', 'icon' => '☑', 'module' => 'lms'],
                ['key' => 'taxonomies', 'label' => 'التصنيفات', 'icon' => '◱', 'module' => 'lms'],
                /*
                 | طلاب الكورسات المسجّلة. حين تكون إدارة الحصص مفعّلة يصير
                 | «الطلاب» هم طلاب المجموعات، وهذه الشاشة فارغة بالبنية —
                 | فتُخفى بدل أن تعرض «لا طلاب» لمدرسة فيها ١١٢ طالباً.
                 */
                ['key' => 'students', 'label' => 'الطلاب', 'icon' => '☻', 'module' => 'lms', 'unless_module' => 'center', 'route' => 'admin.instructor.students', 'ability' => Ability::STUDENTS_VIEW],
                ['key' => 'discussions', 'label' => 'الأسئلة والردود', 'icon' => '❓', 'module' => 'community', 'route' => 'admin.instructor.discussions', 'ability' => Ability::DISCUSSIONS_MODERATE],
                ['key' => 'announcements', 'label' => 'الإعلانات', 'icon' => '◈', 'module' => 'lms', 'route' => 'admin.instructor.announcements', 'ability' => Ability::ANNOUNCEMENTS_MANAGE],
            ],
        ],

        [
            /*
             | ترتيب اليوم لا ترتيب القاعدة: ما يفتحه المدرّس صباحاً
             | (حصص اليوم والحضور) أولاً، ثم من يُدرّسهم، ثم ما يُحاسِب
             | عليه. والفروع والقاعات ومدرّسو السنتر أدوات صاحب السنتر
             | (`center-premises` · `center-staff`)، لا تظهر لمدرّس مستقل.
             */
            'label' => 'الحصص والمجموعات',
            'items' => [
                ['key' => 'schedule', 'label' => 'جدول الحصص', 'icon' => '▦', 'module' => 'center', 'route' => 'admin.center.schedule'],
                ['key' => 'attendance', 'label' => 'الحضور', 'icon' => '✓', 'module' => 'attendance', 'route' => 'admin.center.attendance'],
                ['key' => 'devices', 'label' => 'أجهزة الحضور', 'icon' => '⌘', 'module' => 'attendance', 'feature' => 'attendance_devices', 'route' => 'admin.center.devices', 'ability' => Ability::ATTENDANCE_TAKE],
                ['key' => 'groups', 'label' => 'المجموعات', 'icon' => '▩', 'module' => 'center'],
                ['key' => 'center-students', 'label' => 'الطلاب', 'icon' => '☺', 'module' => 'center'],
                // ما تُبنى عليه المجموعة: لم يكن له شاشة، فكان يُزرع من الأوامر وحدها
                ['key' => 'subjects', 'label' => 'المواد', 'icon' => '∑', 'module' => 'center'],
                ['key' => 'grades', 'label' => 'الصفوف', 'icon' => '▤', 'module' => 'center'],
                ['key' => 'stages', 'label' => 'المراحل', 'icon' => '▥', 'module' => 'center'],
                ['key' => 'fees', 'label' => 'الأقساط والمتأخرات', 'icon' => '⛁', 'module' => 'center-finance', 'route' => 'admin.center.arrears'],
                ['key' => 'center-invoices', 'label' => 'فواتير الطلاب', 'icon' => '◨', 'module' => 'center-finance'],
                ['key' => 'cashboxes', 'label' => 'الخزنة', 'icon' => '⛃', 'module' => 'center-premises', 'route' => 'admin.center.cashboxes'],
                ['key' => 'rooms-occupancy', 'label' => 'إشغال القاعات', 'icon' => '◫', 'module' => 'center-premises', 'route' => 'admin.center.rooms'],
                ['key' => 'center-teachers', 'label' => 'مدرّسو السنتر', 'icon' => '☰', 'module' => 'center-staff', 'route' => 'admin.center.teachers'],
                ['key' => 'branches', 'label' => 'الفروع', 'icon' => '⌂', 'module' => 'center-premises'],
                ['key' => 'rooms', 'label' => 'القاعات', 'icon' => '▢', 'module' => 'center-premises'],
            ],
        ],

        [
            'label' => 'التجارة',
            'items' => [
                ['key' => 'orders', 'label' => 'الطلبات', 'icon' => '◨', 'module' => 'commerce'],
                ['key' => 'products', 'label' => 'المنتجات', 'icon' => '◪', 'module' => 'commerce'],
                ['key' => 'services', 'label' => 'الخدمات', 'icon' => '◇', 'module' => 'services', 'feature' => 'services_module'],
                ['key' => 'bookings', 'label' => 'الحجوزات', 'icon' => '◷', 'module' => 'bookings', 'feature' => 'services_module'],
                ['key' => 'coupons', 'label' => 'الكوبونات', 'icon' => '％', 'module' => 'coupons'],
                ['key' => 'recharge-codes', 'label' => 'أكواد الشحن', 'icon' => '⌗', 'module' => 'commerce', 'feature' => 'recharge_codes'],
                ['key' => 'refunds', 'label' => 'طلبات الاسترداد', 'icon' => '↩', 'module' => 'commerce'],
                ['key' => 'payouts', 'label' => 'تحويلات المدرّسين', 'icon' => '⇄', 'module' => 'payouts'],
                ['key' => 'earnings', 'label' => 'الأرباح والعمولات', 'icon' => '⛁', 'module' => 'payouts', 'route' => 'admin.instructor.earnings', 'ability' => Ability::EARNINGS_VIEW],
            ],
        ],

        [
            'label' => 'المحتوى',
            'items' => [
                ['key' => 'posts', 'label' => 'المدونة', 'icon' => '✎', 'module' => 'blog', 'feature' => 'blog'],
                ['key' => 'comments', 'label' => 'التعليقات', 'icon' => '❝', 'module' => 'blog', 'feature' => 'blog'],
                ['key' => 'pages', 'label' => 'الصفحات', 'icon' => '▭', 'module' => 'content'],
                ['key' => 'page-builder', 'label' => 'محرّر الصفحات', 'icon' => '◫', 'module' => 'page-builder', 'feature' => 'page_builder', 'route' => 'admin.page-builder.index'],
                ['key' => 'media', 'label' => 'الوسائط', 'icon' => '◲', 'module' => 'media', 'route' => 'admin.media.index'],
                ['key' => 'forms', 'label' => 'النماذج', 'icon' => '▤', 'module' => 'forms'],
                ['key' => 'redirects', 'label' => 'تحويلات الروابط', 'icon' => '↪', 'module' => 'content'],
            ],
        ],

        [
            'label' => 'الناس',
            'items' => [
                ['key' => 'users', 'label' => 'المستخدمون', 'icon' => '☺', 'module' => 'users'],
            ],
        ],

        [
            'label' => 'النمو',
            'items' => [
                ['key' => 'campaigns', 'label' => 'التسلسلات التسويقية', 'icon' => '⌄', 'module' => 'funnels', 'feature' => 'funnels', 'route' => 'admin.campaigns.index'],
                ['key' => 'affiliates', 'label' => 'التسويق بالعمولة', 'icon' => '⇢', 'module' => 'affiliates', 'feature' => 'affiliates', 'route' => 'admin.affiliates.index'],
                ['key' => 'reviews', 'label' => 'التقييمات', 'icon' => '★', 'module' => 'reviews', 'route' => 'admin.reviews.queue'],
                ['key' => 'badges', 'label' => 'الشارات', 'icon' => '◆', 'module' => 'gamification', 'feature' => 'gamification'],
            ],
        ],

        [
            'label' => 'النظام',
            'items' => [
                ['key' => 'billing', 'label' => 'الاشتراك والفواتير', 'icon' => '◨', 'route' => 'admin.billing'],
                ['key' => 'usage', 'label' => 'استهلاك باقتك', 'icon' => '◑', 'route' => 'admin.usage', 'ability' => Ability::BILLING_MANAGE],
                ['key' => 'api', 'label' => 'الواجهة البرمجية', 'icon' => '⚯', 'route' => 'admin.api', 'feature' => 'api_access', 'ability' => Ability::SETTINGS_MANAGE],
                ['key' => 'notifications', 'label' => 'الإشعارات', 'icon' => '◔', 'route' => 'admin.notifications.matrix'],
                ['key' => 'platform-mode', 'label' => 'نمط المنصة', 'icon' => '◎', 'route' => 'admin.platform-mode', 'ability' => Ability::SETTINGS_MANAGE],
                ['key' => 'settings', 'label' => 'الإعدادات', 'icon' => '⚙', 'route' => 'admin.settings.index'],
            ],
        ],
    ],
];
