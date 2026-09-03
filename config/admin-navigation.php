<?php

declare(strict_types=1);

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

return [

    'groups' => [

        [
            'label' => 'نظرة عامة',
            'items' => [
                ['key' => 'dashboard', 'label' => 'اللوحة', 'icon' => '◧', 'route' => 'admin.dashboard'],
                ['key' => 'reports', 'label' => 'التقارير', 'icon' => '◔', 'module' => 'reports'],
            ],
        ],

        [
            'label' => 'التعليم',
            'items' => [
                ['key' => 'courses', 'label' => 'الكورسات', 'icon' => '▤', 'module' => 'lms'],
                ['key' => 'lessons', 'label' => 'الدروس', 'icon' => '▶', 'module' => 'lms'],
                ['key' => 'quizzes', 'label' => 'الاختبارات', 'icon' => '◫', 'module' => 'quizzes'],
                ['key' => 'questions', 'label' => 'بنك الأسئلة', 'icon' => '❓', 'module' => 'quizzes'],
                ['key' => 'assignments', 'label' => 'الواجبات', 'icon' => '✎', 'module' => 'assignments'],
                ['key' => 'grading', 'label' => 'التصحيح', 'icon' => '✔', 'module' => 'lms', 'route' => 'admin.grading.index'],
                ['key' => 'certificates', 'label' => 'الشهادات', 'icon' => '◈', 'module' => 'certificates'],
                ['key' => 'live', 'label' => 'الحصص المباشرة', 'icon' => '◉', 'module' => 'live'],
                ['key' => 'enrollments', 'label' => 'التسجيلات', 'icon' => '☑', 'module' => 'lms'],
                ['key' => 'taxonomies', 'label' => 'التصنيفات', 'icon' => '◱', 'module' => 'lms'],
                ['key' => 'standards', 'label' => 'SCORM و H5P', 'icon' => '◩', 'module' => 'lms', 'feature' => 'scorm'],
            ],
        ],

        [
            'label' => 'السنتر',
            'items' => [
                ['key' => 'groups', 'label' => 'المجموعات', 'icon' => '▩', 'module' => 'center'],
                ['key' => 'center-students', 'label' => 'طلاب السنتر', 'icon' => '☺', 'module' => 'center'],
                ['key' => 'schedule', 'label' => 'جدول الحصص', 'icon' => '▦', 'module' => 'center', 'route' => 'admin.center.schedule'],
                ['key' => 'attendance', 'label' => 'الحضور', 'icon' => '✓', 'module' => 'attendance', 'route' => 'admin.center.attendance'],
                ['key' => 'fees', 'label' => 'الأقساط والمتأخرات', 'icon' => '⛁', 'module' => 'center-finance', 'route' => 'admin.center.arrears'],
                ['key' => 'cashboxes', 'label' => 'الخزنة', 'icon' => '⛃', 'module' => 'center-finance', 'route' => 'admin.center.cashboxes'],
                ['key' => 'center-invoices', 'label' => 'فواتير السنتر', 'icon' => '◨', 'module' => 'center-finance'],
                ['key' => 'branches', 'label' => 'الفروع', 'icon' => '⌂', 'module' => 'center'],
                ['key' => 'rooms', 'label' => 'القاعات', 'icon' => '▢', 'module' => 'center'],
                ['key' => 'guardians', 'label' => 'أولياء الأمور', 'icon' => '☗', 'module' => 'parent-portal'],
                ['key' => 'inventory', 'label' => 'المخزون', 'icon' => '◰', 'module' => 'inventory'],
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
                ['key' => 'instructors', 'label' => 'المدرّسون', 'icon' => '✦', 'module' => 'instructors', 'feature' => 'multi_instructor'],
            ],
        ],

        [
            'label' => 'النمو',
            'items' => [
                ['key' => 'campaigns', 'label' => 'التسلسلات التسويقية', 'icon' => '⌄', 'module' => 'funnels', 'feature' => 'funnels', 'route' => 'admin.campaigns.index'],
                ['key' => 'affiliates', 'label' => 'التسويق بالعمولة', 'icon' => '⇢', 'module' => 'affiliates', 'feature' => 'affiliates', 'route' => 'admin.affiliates.index'],
                ['key' => 'community', 'label' => 'المجتمع', 'icon' => '◍', 'module' => 'community', 'feature' => 'community'],
                ['key' => 'reviews', 'label' => 'التقييمات', 'icon' => '★', 'module' => 'reviews', 'route' => 'admin.reviews.queue'],
                ['key' => 'badges', 'label' => 'الشارات', 'icon' => '◆', 'module' => 'gamification', 'feature' => 'gamification'],
            ],
        ],

        [
            'label' => 'النظام',
            'items' => [
                ['key' => 'billing', 'label' => 'الاشتراك والفواتير', 'icon' => '◨', 'route' => 'admin.billing'],
                ['key' => 'notifications', 'label' => 'الإشعارات', 'icon' => '◔', 'route' => 'admin.notifications.matrix'],
                ['key' => 'settings', 'label' => 'الإعدادات', 'icon' => '⚙', 'route' => 'admin.settings.index'],
                ['key' => 'appearance', 'label' => 'المظهر والثيم', 'icon' => '◐', 'route' => 'admin.appearance'],
                ['key' => 'modules', 'label' => 'الموديولات', 'icon' => '◱', 'route' => 'admin.modules'],
            ],
        ],
    ],
];
