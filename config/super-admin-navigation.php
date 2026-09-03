<?php

declare(strict_types=1);

/*
 | قوائم اللوحة العليا — ما نراه نحن عن أعمالنا،
 | لا ما يراه المشترك عن منصّته.
 */

return [
    'groups' => [
        [
            'label' => 'الأعمال',
            'items' => [
                ['key' => 'overview', 'label' => 'نظرة عامة', 'icon' => '◧', 'url' => '/admin'],
                ['key' => 'tenants', 'label' => 'المشتركون', 'icon' => '☗', 'url' => '/admin/tenants'],
                ['key' => 'usage', 'label' => 'الاستهلاك والحدود', 'icon' => '◔', 'url' => '/admin/usage'],
            ],
        ],
        [
            'label' => 'الفوترة',
            'items' => [
                ['key' => 'plans', 'label' => 'الباقات والمزايا', 'icon' => '◫', 'url' => '/admin/plans'],
                ['key' => 'subscriptions', 'label' => 'الاشتراكات', 'icon' => '⇄', 'url' => '/admin/subscriptions'],
                ['key' => 'invoices', 'label' => 'الفواتير', 'icon' => '◨', 'url' => '/admin/invoices'],
            ],
        ],
        [
            'label' => 'المنصة',
            'items' => [
                ['key' => 'themes', 'label' => 'الثيمات', 'icon' => '◐', 'url' => '/admin/themes'],
                ['key' => 'countries', 'label' => 'الدول والعملات', 'icon' => '◍', 'url' => '/admin/countries'],
                ['key' => 'audit', 'label' => 'سجلّ التدخّلات', 'icon' => '☰', 'url' => '/admin/audit'],
                ['key' => 'health', 'label' => 'صحة النظام', 'icon' => '✓', 'url' => '/admin/health'],
            ],
        ],
    ],
];
