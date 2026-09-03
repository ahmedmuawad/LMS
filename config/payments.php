<?php

declare(strict_types=1);

/*
 | ADR-002 — البوابات التسع. كل بوابة بلجن مستقل، وهذه بطاقة تعريفه:
 | ما يحتاجه من مفاتيح، وأين يعمل، وبأي عملات.
 |
 | التعريف هنا تقني ثابت؛ أما التفعيل والمفاتيح والحدود فمن إعدادات
 | كل مشترك، لأن لكل واحد حسابه لدى البوابة.
 */

return [

    'gateways' => [

        [
            'key' => 'paymob',
            'label' => 'Paymob — بطاقات ومحافظ وأقساط',
            'note' => 'الأوسع انتشاراً في مصر: بطاقات · محافظ إلكترونية · أقساط · دفع كاش عبر أكسبت.',
            'credentials' => [
                'api_key' => 'مفتاح الـ API',
                'public_key' => 'المفتاح العام',
                'secret_key' => 'المفتاح السرّي',
                'hmac_secret' => 'سرّ التحقق HMAC',
                'integration_card' => 'رقم تكامل البطاقات',
                'integration_wallet' => 'رقم تكامل المحافظ',
                'integration_kiosk' => 'رقم تكامل أكسبت',
            ],
            'currencies' => ['EGP'],
            'countries' => ['EG'],
        ],

        [
            'key' => 'fawry',
            'label' => 'Fawry — كود دفع بالمنافذ',
            'note' => 'يخدم من لا يملك بطاقة: يدفع بالكود في أي منفذ فوري.',
            'credentials' => [
                'merchant_code' => 'كود التاجر',
                'security_key' => 'مفتاح الأمان',
            ],
            'currencies' => ['EGP'],
            'countries' => ['EG'],
        ],

        [
            'key' => 'stripe',
            'label' => 'Stripe — بطاقات دولية',
            'credentials' => [
                'publishable_key' => 'المفتاح المنشور',
                'secret_key' => 'المفتاح السرّي',
                'webhook_secret' => 'سرّ الـ Webhook',
            ],
            'currencies' => ['USD', 'EUR', 'GBP', 'AED', 'SAR'],
            'countries' => [],
        ],

        [
            'key' => 'paypal',
            'label' => 'PayPal',
            'credentials' => [
                'client_id' => 'معرّف العميل',
                'client_secret' => 'سرّ العميل',
                'webhook_id' => 'معرّف الـ Webhook',
            ],
            'currencies' => ['USD', 'EUR', 'GBP'],
            'countries' => [],
        ],

        [
            'key' => 'tap',
            'label' => 'Tap — الخليج',
            'note' => 'KNET · مدى · بنفت · بطاقات — أوسع تغطية خليجية.',
            'credentials' => [
                'secret_key' => 'المفتاح السرّي',
                'publishable_key' => 'المفتاح المنشور',
            ],
            'currencies' => ['SAR', 'AED', 'KWD', 'BHD', 'QAR', 'OMR', 'EGP'],
            'countries' => ['SA', 'AE', 'KW', 'BH', 'QA', 'OM'],
        ],

        [
            'key' => 'moyasar',
            'label' => 'Moyasar — السعودية',
            'note' => 'مدى · Apple Pay · STC Pay.',
            'credentials' => [
                'publishable_key' => 'المفتاح المنشور',
                'secret_key' => 'المفتاح السرّي',
            ],
            'currencies' => ['SAR'],
            'countries' => ['SA'],
        ],

        [
            'key' => 'hyperpay',
            'label' => 'HyperPay',
            'credentials' => [
                'access_token' => 'رمز الوصول',
                'entity_id' => 'معرّف الكيان',
            ],
            'currencies' => ['SAR', 'AED', 'EGP', 'JOD'],
            'countries' => ['SA', 'AE', 'EG', 'JO'],
        ],

        [
            'key' => 'bank_transfer',
            'label' => 'تحويل بنكي',
            'note' => 'يعتمده كثير من السناتر وأولياء الأمور — الطلب يبقى معلّقاً حتى تعتمد الإيصال.',
            'manual' => true,
            'credentials' => [],
            'currencies' => ['EGP', 'SAR', 'AED', 'USD'],
            'countries' => [],
        ],

        [
            'key' => 'cash_on_delivery',
            'label' => 'الدفع عند الاستلام',
            'note' => 'للمنتجات المادية والاشتراك الحضوري في السنتر.',
            'manual' => true,
            'credentials' => [],
            'currencies' => ['EGP', 'SAR', 'AED'],
            'countries' => [],
        ],

        [
            'key' => 'wallet',
            'label' => 'محفظة الموقع',
            'note' => 'رصيد داخلي يُشحن بأكواد الشحن أو بأي بوابة أخرى.',
            'manual' => true,
            'credentials' => [],
            'currencies' => ['EGP', 'SAR', 'AED', 'USD'],
            'countries' => [],
        ],

        [
            'key' => 'tabby',
            'label' => 'تابي — قسّمها على 4',
            'credentials' => [
                'public_key' => 'المفتاح العام',
                'secret_key' => 'المفتاح السرّي',
            ],
            'currencies' => ['SAR', 'AED', 'KWD', 'BHD'],
            'countries' => ['SA', 'AE', 'KW', 'BH'],
        ],

        [
            'key' => 'tamara',
            'label' => 'تمارا — ادفع لاحقاً',
            'credentials' => [
                'api_token' => 'رمز الـ API',
                'notification_token' => 'رمز الإشعارات',
            ],
            'currencies' => ['SAR', 'AED', 'KWD'],
            'countries' => ['SA', 'AE', 'KW'],
        ],
    ],
];
