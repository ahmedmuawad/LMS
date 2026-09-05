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
     | القائمة مغلقة، وبياناتها في إعدادات المنصّة لا هنا: رقم حساب
     | أو عنوان إنستاباي يغيّره صاحب المنصّة من لوحته، لا المبرمج بنشرٍ
     | جديد. وما يظهر منها للعميل هو ما اكتملت بياناته وحده.
     |
     | الطرائق اليدوية الثلاث صنفٌ واحد بمعاملٍ مختلف: مسارها واحد،
     | والفرق بيانات تُعرض.
     */
    'gateways' => [
        'instapay' => ManualTransferGateway::class,
        'wallet' => ManualTransferGateway::class,
        'bank' => ManualTransferGateway::class,
        'stripe' => StripeCheckoutGateway::class,
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
