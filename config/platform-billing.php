<?php

declare(strict_types=1);

use App\Core\Billing\Gateways\ManualTransferGateway;
use App\Core\Billing\Gateways\StripeCheckoutGateway;

/*
 | فوترة اشتراكاتنا نحن — لا مبيعات المشترك.
 |
 | بوابات المشترك (`config/payments.php`) تقرأ مفاتيحها من **إعدادات
 | المشترك**، وهي لبيعه لطلابه. أمّا اشتراك المشترك عندنا فيُدفع قبل
 | أن يوجد مشترك أصلاً — لا سياق ولا جدول إعدادات. فمفاتيحنا هنا من
 | البيئة، والقائمة مغلقة كسائر قوائم النظام.
 */

return [

    /*
     | التحويل البنكي متاح دائماً: لا يحتاج مفتاحاً، وهو ما يستعمله
     | كثير من السناتر فعلاً. وسترايب لا يظهر إلا إن كان مفتاحه مضبوطاً.
     */
    'gateways' => [
        'manual' => ManualTransferGateway::class,
        'stripe' => StripeCheckoutGateway::class,
    ],

    'manual' => [
        'enabled' => (bool) env('PLATFORM_BANK_ENABLED', true),
        'bank' => env('PLATFORM_BANK_NAME'),
        'account_name' => env('PLATFORM_BANK_ACCOUNT_NAME'),
        'account_number' => env('PLATFORM_BANK_ACCOUNT'),
        'iban' => env('PLATFORM_BANK_IBAN'),
        'wallet' => env('PLATFORM_WALLET_NUMBER'),
        'instructions' => env('PLATFORM_BANK_INSTRUCTIONS'),
    ],

    'stripe' => [
        'secret' => env('PLATFORM_STRIPE_SECRET'),
        'webhook_secret' => env('PLATFORM_STRIPE_WEBHOOK_SECRET'),
    ],

    /*
     | التجربة بلا بطاقة.
     |
     | إلزام البطاقة قبل التجربة يقطع نصف المسجّلين، ولا يحمينا من
     | شيء: القاعدة تُجهَّز ثم تُقفل عند انتهاء التجربة إن لم يُدفع.
     */
    'trial_without_card' => (bool) env('PLATFORM_TRIAL_WITHOUT_CARD', true),

    /** أيام السماح بعد استحقاق الفاتورة قبل تعليق المشترك. */
    'grace_days' => (int) env('PLATFORM_GRACE_DAYS', 7),
];
