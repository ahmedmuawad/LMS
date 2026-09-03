<?php

declare(strict_types=1);

return [
    'default' => 'solo-academy',

    /*
     | ما يستطيع المشترك ضبطه من لوحته بلا كود.
     | القيم تُحقن كمتغيّرات CSS تعيد تعريف الطبقة الدلالية فقط،
     | فيستحيل أن يكسر تخصيصُه التباينَ أو الاتساق (ADR-013).
     */
    'customizable' => [
        'primary_color' => ['type' => 'color',  'default' => '#12707E'],
        'accent_color' => ['type' => 'color',  'default' => '#C08A2E'],
        'font_ar' => ['type' => 'select', 'default' => 'IBM Plex Sans Arabic',
            'options' => ['IBM Plex Sans Arabic', 'Tajawal', 'Cairo', 'Almarai']],
        'font_en' => ['type' => 'select', 'default' => 'Inter',
            'options' => ['Inter', 'IBM Plex Sans', 'Poppins']],
        'radius' => ['type' => 'select', 'default' => 'md',
            'options' => ['sharp', 'md', 'round']],
        'density' => ['type' => 'select', 'default' => 'comfortable',
            'options' => ['comfortable', 'compact']],
        'default_scheme' => ['type' => 'select', 'default' => 'system',
            'options' => ['system', 'light', 'dark']],
        'numerals' => ['type' => 'select', 'default' => 'arabic',
            'options' => ['arabic', 'hindi']],
        'custom_css' => ['type' => 'code',   'default' => '', 'feature' => 'custom_css'],
    ],
];
