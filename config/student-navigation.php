<?php

declare(strict_types=1);

/*
 | قائمة لوحة الطالب.
 |
 | مصدر واحد يبني القائمة الجانبية وقائمة الموبايل معاً — وشريط
 | الموقع العام يقرأ منه كذلك، فلا يفترق ما يراه الطالب في الترويسة
 | عمّا يراه في لوحته.
 |
 |   module  → لا يُعرض إن كان الموديول مطفأً لهذا المشترك
 |   feature → لا يُعرض إن كانت الميزة خارج باقة المشترك
 |   setting → إعدادٌ يجب أن يكون مفعّلاً
 |
 | كل عنصر هنا يجب أن يفتح شاشة تعمل: رابط ٤٠٤ في قائمة يهدم الثقة
 | أسرع من غياب الرابط.
 */

return [

    'groups' => [

        [
            'label' => 'تعلّمي',
            'items' => [
                ['key' => 'dashboard', 'label' => 'لوحتي', 'icon' => '◧', 'url' => '/me'],
                ['key' => 'my-courses', 'label' => 'كورساتي', 'icon' => '▤', 'url' => '/my-courses', 'module' => 'lms'],
                ['key' => 'my-classes', 'label' => 'حصصي', 'icon' => '◷', 'url' => '/my-classes', 'module' => 'center'],
                ['key' => 'progress', 'label' => 'تقدّمي', 'icon' => '◔', 'url' => '/my-progress', 'module' => 'lms'],
                ['key' => 'grades', 'label' => 'درجاتي', 'icon' => '▦', 'url' => '/my-grades', 'module' => 'lms'],
                ['key' => 'notes', 'label' => 'ملاحظاتي', 'icon' => '✎', 'url' => '/my-notes', 'module' => 'lms'],
                ['key' => 'review', 'label' => 'مراجعتي', 'icon' => '↻', 'url' => '/my-review', 'module' => 'lms', 'setting' => 'lms.review_enabled', 'setting_default' => true],
                ['key' => 'certificates', 'label' => 'شهاداتي', 'icon' => '◈', 'url' => '/my-certificates', 'module' => 'certificates'],
                ['key' => 'badges', 'label' => 'شاراتي', 'icon' => '★', 'url' => '/my-badges', 'module' => 'gamification', 'feature' => 'gamification'],
                ['key' => 'challenges', 'label' => 'التحدّيات', 'icon' => '◎', 'url' => '/challenges', 'module' => 'gamification', 'feature' => 'gamification'],
            ],
        ],

        [
            'label' => 'مشترياتي',
            'items' => [
                ['key' => 'orders', 'label' => 'طلباتي', 'icon' => '◨', 'url' => '/my-orders', 'module' => 'commerce'],
                ['key' => 'wallet', 'label' => 'محفظتي', 'icon' => '⛁', 'url' => '/wallet', 'module' => 'commerce'],
                ['key' => 'memberships', 'label' => 'اشتراكاتي', 'icon' => '◉', 'url' => '/my-memberships', 'module' => 'subscriptions', 'feature' => 'subscriptions'],
                ['key' => 'wishlist', 'label' => 'قائمة الأمنيات', 'icon' => '♡', 'url' => '/wishlist', 'module' => 'lms'],
                ['key' => 'bookings', 'label' => 'حجوزاتي', 'icon' => '◷', 'url' => '/my-bookings', 'module' => 'bookings'],
                ['key' => 'service-requests', 'label' => 'طلبات خدماتي', 'icon' => '◇', 'url' => '/my-services', 'module' => 'services'],
            ],
        ],

        [
            'label' => 'حسابي',
            'items' => [
                ['key' => 'notifications', 'label' => 'الإشعارات', 'icon' => '◕', 'url' => '/notifications'],
                ['key' => 'discussions', 'label' => 'النقاشات', 'icon' => '❝', 'url' => '/discussions', 'module' => 'community'],
                ['key' => 'affiliate', 'label' => 'التسويق بالعمولة', 'icon' => '⇢', 'url' => '/affiliate', 'module' => 'affiliates', 'setting' => 'growth.affiliates_enabled'],
                ['key' => 'account', 'label' => 'بياناتي', 'icon' => '☺', 'url' => '/account'],
                ['key' => 'security', 'label' => 'الأمان والدخول', 'icon' => '⛨', 'url' => '/account/two-factor'],
            ],
        ],
    ],
];
