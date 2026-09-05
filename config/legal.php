<?php

declare(strict_types=1);

/*
 | هوية الجهة القانونية وبيانات الوثائق.
 |
 | مصدر واحد: الاسم والعنوان والبريد وتاريخ السريان تُقرأ من هنا في
 | كل صفحة قانونية. نسخُها في ثلاث صفحات يعني أن تغيير عنوان يترك
 | عنواناً قديماً في وثيقة يُحتجّ بها.
 |
 | تنبيه: هذه صياغة عملية لا استشارة قانونية — تُراجَع من محامٍ قبل
 | أول عميل يدفع، خصوصاً بنود المسؤولية والاسترداد.
 */

return [

    'entity' => [
        // الاسم التجاري كما يُتعاقد به. يُملأ بالسجلّ التجاري عند وجوده.
        'name' => env('LEGAL_ENTITY_NAME', 'أُسُس'),
        'legal_form' => env('LEGAL_ENTITY_FORM', ''),
        'registration' => env('LEGAL_ENTITY_REGISTRATION', ''),
        'tax_id' => env('LEGAL_ENTITY_TAX_ID', ''),
        'address' => env('LEGAL_ENTITY_ADDRESS', ''),
        'country' => env('LEGAL_ENTITY_COUNTRY', 'مصر'),
        'email' => env('LEGAL_ENTITY_EMAIL', 'stop4web.agency@gmail.com'),
        'phone' => env('LEGAL_ENTITY_PHONE', ''),
    ],

    /** القانون الواجب التطبيق وجهة الاختصاص. */
    'jurisdiction' => env('LEGAL_JURISDICTION', 'جمهورية مصر العربية'),

    /** تاريخ سريان النسخة الحالية — يتغيّر مع كل تعديل جوهري. */
    'effective_from' => env('LEGAL_EFFECTIVE_FROM', '2026-09-05'),

    /*
     | مدد يُحتجّ بها في الوثائق، ومكانها هنا لأنها تُنفَّذ في الكود:
     | تغييرها في النصّ وحده يجعل الوثيقة تكذب على النظام.
     */
    'trial_days' => 14,
    'grace_days' => env('PLATFORM_GRACE_DAYS', 7),
    'refund_days' => env('LEGAL_REFUND_DAYS', 14),
    'export_days' => env('LEGAL_EXPORT_DAYS', 30),
    'retention_days' => env('LEGAL_RETENTION_DAYS', 90),

    /*
     | من نشاركه بيانات بالضرورة التقنية. الإفصاح شرطٌ في أي سياسة
     | خصوصية تُحترم، وإخفاؤه لا يُخفي الحقيقة عمّن يفحص الطلبات.
     */
    'processors' => [
        ['name' => 'Hetzner', 'role' => 'استضافة الخوادم', 'region' => 'ألمانيا'],
        ['name' => 'Cloudflare', 'role' => 'شبكة توصيل وحماية', 'region' => 'عالمي'],
        ['name' => 'Google (Gmail SMTP)', 'role' => 'إرسال البريد', 'region' => 'عالمي'],
    ],
];
