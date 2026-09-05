<?php

declare(strict_types=1);

/*
 | مزوّدو الذكاء الاصطناعي.
 |
 | ثلاثة مفاتيح في الباقات (`ai_assistant`, `ai_course_builder`,
 | `ai_exam_from_pdf`) بلا سطر كود.
 |
 | ## المفتاح مفتاح المنصة لا المشترك
 |
 | مدرّسٌ لا يملك حساب OpenAI ولا يريد أن يملكه، فالمنصة تحمل
 | المفتاح وتحاسب على الاستعمال بحدٍّ في الباقة — كما تفعل مع
 | الإيميلات ودقائق الفيديو. ومن أراد مفتاحه الخاص وضعه في
 | الإعدادات فيُستعمل بدلاً عن مفتاحنا ولا يُحسب عليه حدّ.
 |
 | ## ولماذا لا نُخزّن ما يُرسَل
 |
 | المحتوى الذي يُرسَل ملكُ المشترك: أسئلة امتحاناته ومناهجه. فلا
 | يُحفظ عندنا نصُّ الطلب ولا الجواب — يُحفظ عدد الرموز للمحاسبة
 | وحده.
 */

return [

    'default' => env('AI_PROVIDER', 'openai'),

    'providers' => [

        'openai' => [
            'name' => 'OpenAI',
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        ],

        'anthropic' => [
            'name' => 'Anthropic',
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
        ],
    ],

    /*
     | حدود الطلب الواحد.
     |
     | المنهج الطويل يُقصّ قبل الإرسال: صفحةٌ كاملة من كتابٍ تُنتج
     | أسئلةً أفضل من عشرين صفحة، والعشرون تكلّف عشرة أضعاف.
     */
    'max_input_chars' => 24_000,
    'max_output_tokens' => 4_000,
    'timeout_seconds' => 90,
];
