<?php

declare(strict_types=1);

/*
 | ADR-010 — أنماط المنصة.
 |
 | اختيار المشترك في معالج التهيئة يفعّل هذه المجموعة من الموديولات،
 | ويضبط إعداداته الافتراضية، ويختار ثيمه، ويبني قوائم لوحته —
 | فيرى ما يخصّه فقط، لا قوائم فارغة.
 |
 | كل موديول مبني بالكامل في النظام؛ هذا الملف يقرّر ما يُعرض،
 | وطبقة الصلاحيات (ADR-011) تقرّر ما هو مسموح بالباقة.
 */

$core = ['users', 'media', 'settings', 'localization', 'seo', 'notifications', 'reports'];
$lms = ['lms', 'quizzes', 'assignments', 'certificates', 'video', 'gamification'];
$shop = ['commerce', 'payments', 'coupons', 'invoices'];
$web = ['content', 'blog', 'page-builder', 'forms'];

return [

    'default' => 'solo',

    'modes' => [

        'solo' => [
            'name' => ['ar' => 'مدرّس فردي', 'en' => 'Solo instructor'],
            'summary' => ['ar' => 'أكاديمية باسمك أنت وحدك.', 'en' => 'A personal academy under your name.'],
            'icon' => '👤',
            'theme' => 'solo-academy',
            'modules' => [...$core, ...$lms, ...$shop, ...$web, 'subscriptions', 'affiliates'],
            'settings' => [
                'lms.instructor_signup' => false,
                'commerce.guest_checkout' => true,
                'seo.enabled' => true,
            ],
        ],

        'marketplace' => [
            'name' => ['ar' => 'منصة متعددة المدرّسين', 'en' => 'Multi-instructor platform'],
            'summary' => ['ar' => 'مدرّسون يبيعون كورساتهم بعمولة.', 'en' => 'Instructors sell with revenue share.'],
            'icon' => '🏛️',
            'theme' => 'marketplace',
            'modules' => [...$core, ...$lms, ...$shop, ...$web,
                'instructors', 'commissions', 'payouts', 'reviews', 'community',
                'subscriptions', 'affiliates',
            ],
            'settings' => [
                'lms.instructor_signup' => true,
                'lms.require_course_approval' => true,
                'commerce.guest_checkout' => true,
                'instructors.commission_rate' => 70,
            ],
        ],

        'center' => [
            'name' => ['ar' => 'سنتر تعليمي', 'en' => 'Learning center'],
            'summary' => ['ar' => 'مجموعات وجداول وحضور وأقساط وأولياء أمور.', 'en' => 'Groups, schedules, attendance, fees, parents.'],
            'icon' => '🏫',
            'theme' => 'center',
            'modules' => [...$core, ...$lms, ...$shop,
                'center', 'attendance', 'center-finance', 'parent-portal',
                'inventory', 'instructors', 'content',
            ],
            'settings' => [
                'center.enabled' => true,
                'center.attendance_methods' => ['manual', 'code', 'qr'],
                'center.notify_guardian_on_absence' => true,
                'center.week_start' => 6,
                'commerce.guest_checkout' => false,
            ],
        ],

        'hybrid' => [
            'name' => ['ar' => 'شامل', 'en' => 'All-in-one'],
            'summary' => ['ar' => 'كل شيء: أونلاين وحضوري وخدمات ومنتجات.', 'en' => 'Everything: online, on-site, services, products.'],
            'icon' => '🌐',
            'theme' => 'hybrid',
            'modules' => [...$core, ...$lms, ...$shop, ...$web,
                'instructors', 'commissions', 'payouts', 'reviews', 'community',
                'center', 'attendance', 'center-finance', 'parent-portal', 'inventory',
                'services', 'bookings', 'subscriptions', 'affiliates', 'funnels',
            ],
            'settings' => [
                'lms.instructor_signup' => true,
                'center.enabled' => true,
                'services.enabled' => true,
            ],
        ],
    ],

    /*
     | نوع التقديم — بُعد مستقل عن النمط، يحدّد الحقول واللوحات الظاهرة.
     */
    'delivery' => [
        'recorded' => [
            'name' => ['ar' => 'كورسات مسجّلة', 'en' => 'Recorded courses'],
            'modules' => [],
        ],
        'live' => [
            'name' => ['ar' => 'حصص مباشرة', 'en' => 'Live classes'],
            'modules' => ['live'],
        ],
        'blended' => [
            'name' => ['ar' => 'مدمج', 'en' => 'Blended'],
            'modules' => ['live'],
        ],
    ],
];
