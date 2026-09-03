<?php

declare(strict_types=1);
use App\Core\Admin\Resources\Central\InvoiceResource;
use App\Core\Admin\Resources\Central\SubscriptionResource;
use App\Core\Admin\Resources\Central\TenantResource;
use App\Core\Admin\Resources\UserResource;

/*
 | خريطة الموارد لكل سياق. القائمة مغلقة عمداً:
 | لا يصل مفتاح من المستخدم إلى الحاوية ليُحلّ كصنف.
 */

return [

    // لوحة المشترك — داخل قاعدته
    'tenant' => [
        'users' => UserResource::class,
    ],

    // اللوحة العليا — القاعدة المركزية
    'central' => [
        'tenants' => TenantResource::class,
        'subscriptions' => SubscriptionResource::class,
        'invoices' => InvoiceResource::class,
    ],
];
