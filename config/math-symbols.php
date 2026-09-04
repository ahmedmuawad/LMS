<?php

declare(strict_types=1);

/*
 | لوحة رموز محرّر المعادلات.
 |
 | المدرّس لا يحفظ صياغة TeX، ولا يجوز أن نطلب منه ذلك: يضغط الرمز
 | فيُكتب. لذلك كل زرّ هنا يحمل ثلاثة أشياء —
 |
 |   tex     ما يُكتب في الحقل (وفيه \square لكل فراغ يُملأ)
 |   preview ما يُعرَض على الزرّ نفسه، مرسوماً لا مكتوباً
 |   label   اسمه بالعربية، يُقرأ بقارئ الشاشة ويظهر عند التحويم
 |
 | الفراغ `\square` مقصود: يُرسَم مربّعاً في المعاينة، ويُحدَّد بعد
 | الإدراج فيكتب المدرّس فوقه مباشرة. بغيره يُدرَج `\frac{}{}`
 | والمؤشّر في آخرها فيبحث صاحبها عن مكان الكتابة.
 */

return [

    'groups' => [

        'basic' => [
            'label' => 'أساسيات',
            'icon' => '+',
            'symbols' => [
                ['tex' => ' + ', 'preview' => '+', 'label' => 'زائد'],
                ['tex' => ' - ', 'preview' => '-', 'label' => 'ناقص'],
                ['tex' => ' \times ', 'preview' => '\times', 'label' => 'ضرب'],
                ['tex' => ' \div ', 'preview' => '\div', 'label' => 'قسمة'],
                ['tex' => ' \cdot ', 'preview' => '\cdot', 'label' => 'نقطة ضرب'],
                ['tex' => ' = ', 'preview' => '=', 'label' => 'يساوي'],
                ['tex' => ' \pm ', 'preview' => '\pm', 'label' => 'زائد أو ناقص'],
                ['tex' => ' \mp ', 'preview' => '\mp', 'label' => 'ناقص أو زائد'],
                ['tex' => '\%', 'preview' => '\%', 'label' => 'نسبة مئوية'],
                ['tex' => '\square : \square', 'preview' => 'a : b', 'label' => 'نسبة'],
                ['tex' => '(\square)', 'preview' => '(\square)', 'label' => 'قوسان'],
                ['tex' => '|\square|', 'preview' => '|\square|', 'label' => 'قيمة مطلقة'],
            ],
        ],

        'fractions' => [
            'label' => 'كسور وأسس',
            'icon' => '\frac{a}{b}',
            'symbols' => [
                ['tex' => '\frac{\square}{\square}', 'preview' => '\frac{a}{b}', 'label' => 'كسر'],
                ['tex' => '\square\frac{\square}{\square}', 'preview' => '2\frac{1}{3}', 'label' => 'عدد كسري'],
                ['tex' => '\square^{\square}', 'preview' => 'a^{n}', 'label' => 'أُسّ'],
                ['tex' => '\square^2', 'preview' => 'a^2', 'label' => 'تربيع'],
                ['tex' => '\square^3', 'preview' => 'a^3', 'label' => 'تكعيب'],
                ['tex' => '\square_{\square}', 'preview' => 'a_{n}', 'label' => 'دليل سفلي'],
                ['tex' => '\sqrt{\square}', 'preview' => '\sqrt{a}', 'label' => 'جذر تربيعي'],
                ['tex' => '\sqrt[\square]{\square}', 'preview' => '\sqrt[n]{a}', 'label' => 'جذر نوني'],
                ['tex' => '\square \times 10^{\square}', 'preview' => 'a\times10^{n}', 'label' => 'صيغة علمية'],
            ],
        ],

        'compare' => [
            'label' => 'مقارنات',
            'icon' => '\leq',
            'symbols' => [
                ['tex' => ' < ', 'preview' => '<', 'label' => 'أصغر من'],
                ['tex' => ' > ', 'preview' => '>', 'label' => 'أكبر من'],
                ['tex' => ' \leq ', 'preview' => '\leq', 'label' => 'أصغر من أو يساوي'],
                ['tex' => ' \geq ', 'preview' => '\geq', 'label' => 'أكبر من أو يساوي'],
                ['tex' => ' \neq ', 'preview' => '\neq', 'label' => 'لا يساوي'],
                ['tex' => ' \approx ', 'preview' => '\approx', 'label' => 'يقارب'],
                ['tex' => ' \equiv ', 'preview' => '\equiv', 'label' => 'يطابق'],
                ['tex' => ' \propto ', 'preview' => '\propto', 'label' => 'يتناسب مع'],
            ],
        ],

        'geometry' => [
            'label' => 'هندسة',
            'icon' => '\angle',
            'symbols' => [
                ['tex' => '^\circ', 'preview' => '90^\circ', 'label' => 'درجة'],
                ['tex' => '\angle \square', 'preview' => '\angle', 'label' => 'زاوية'],
                ['tex' => '\triangle \square', 'preview' => '\triangle', 'label' => 'مثلث'],
                ['tex' => ' \perp ', 'preview' => '\perp', 'label' => 'عمودي على'],
                ['tex' => ' \parallel ', 'preview' => '\parallel', 'label' => 'موازٍ لـ'],
                ['tex' => ' \cong ', 'preview' => '\cong', 'label' => 'مطابق'],
                ['tex' => ' \sim ', 'preview' => '\sim', 'label' => 'مشابه'],
                ['tex' => '\pi', 'preview' => '\pi', 'label' => 'باي'],
                ['tex' => '\overline{\square}', 'preview' => '\overline{AB}', 'label' => 'قطعة مستقيمة'],
                ['tex' => '\overrightarrow{\square}', 'preview' => '\overrightarrow{AB}', 'label' => 'شعاع'],
                ['tex' => '\,\text{cm}', 'preview' => '\text{cm}', 'label' => 'سنتيمتر'],
                ['tex' => '\,\text{cm}^2', 'preview' => '\text{cm}^2', 'label' => 'سنتيمتر مربّع'],
            ],
        ],

        'greek' => [
            'label' => 'حروف يونانية',
            'icon' => '\alpha',
            'symbols' => [
                ['tex' => '\alpha', 'preview' => '\alpha', 'label' => 'ألفا'],
                ['tex' => '\beta', 'preview' => '\beta', 'label' => 'بيتا'],
                ['tex' => '\gamma', 'preview' => '\gamma', 'label' => 'جاما'],
                ['tex' => '\theta', 'preview' => '\theta', 'label' => 'ثيتا'],
                ['tex' => '\lambda', 'preview' => '\lambda', 'label' => 'لامدا'],
                ['tex' => '\mu', 'preview' => '\mu', 'label' => 'ميو'],
                ['tex' => '\sigma', 'preview' => '\sigma', 'label' => 'سيجما'],
                ['tex' => '\phi', 'preview' => '\phi', 'label' => 'فاي'],
                ['tex' => '\omega', 'preview' => '\omega', 'label' => 'أوميجا'],
                ['tex' => '\Delta', 'preview' => '\Delta', 'label' => 'دلتا كبيرة'],
                ['tex' => '\Sigma', 'preview' => '\Sigma', 'label' => 'سيجما كبيرة'],
                ['tex' => '\Omega', 'preview' => '\Omega', 'label' => 'أوميجا كبيرة'],
            ],
        ],

        'sets' => [
            'label' => 'مجموعات ومنطق',
            'icon' => '\in',
            'symbols' => [
                ['tex' => ' \in ', 'preview' => '\in', 'label' => 'ينتمي إلى'],
                ['tex' => ' \notin ', 'preview' => '\notin', 'label' => 'لا ينتمي'],
                ['tex' => ' \subset ', 'preview' => '\subset', 'label' => 'مجموعة جزئية'],
                ['tex' => ' \subseteq ', 'preview' => '\subseteq', 'label' => 'جزئية أو مساوية'],
                ['tex' => ' \cup ', 'preview' => '\cup', 'label' => 'اتحاد'],
                ['tex' => ' \cap ', 'preview' => '\cap', 'label' => 'تقاطع'],
                ['tex' => '\emptyset', 'preview' => '\emptyset', 'label' => 'المجموعة الخالية'],
                ['tex' => '\{\square\}', 'preview' => '\{a\}', 'label' => 'مجموعة'],
                ['tex' => ' \Rightarrow ', 'preview' => '\Rightarrow', 'label' => 'يستلزم'],
                ['tex' => ' \Leftrightarrow ', 'preview' => '\Leftrightarrow', 'label' => 'يكافئ'],
                ['tex' => '\mathbb{N}', 'preview' => '\mathbb{N}', 'label' => 'الأعداد الطبيعية'],
                ['tex' => '\mathbb{R}', 'preview' => '\mathbb{R}', 'label' => 'الأعداد الحقيقية'],
            ],
        ],

        'functions' => [
            'label' => 'دوال وحساب',
            'icon' => '\sum',
            'symbols' => [
                ['tex' => '\sin(\square)', 'preview' => '\sin', 'label' => 'جيب'],
                ['tex' => '\cos(\square)', 'preview' => '\cos', 'label' => 'جيب تمام'],
                ['tex' => '\tan(\square)', 'preview' => '\tan', 'label' => 'ظل'],
                ['tex' => '\log_{\square}(\square)', 'preview' => '\log', 'label' => 'لوغاريتم'],
                ['tex' => '\ln(\square)', 'preview' => '\ln', 'label' => 'لوغاريتم طبيعي'],
                ['tex' => '\sum_{\square}^{\square}', 'preview' => '\sum', 'label' => 'مجموع'],
                ['tex' => '\prod_{\square}^{\square}', 'preview' => '\prod', 'label' => 'حاصل ضرب'],
                ['tex' => '\int_{\square}^{\square}', 'preview' => '\int', 'label' => 'تكامل'],
                ['tex' => '\lim_{\square \to \square}', 'preview' => '\lim', 'label' => 'نهاية'],
                ['tex' => '\infty', 'preview' => '\infty', 'label' => 'ما لا نهاية'],
                ['tex' => ' \to ', 'preview' => '\to', 'label' => 'يؤول إلى'],
                ['tex' => 'f(\square)', 'preview' => 'f(x)', 'label' => 'دالة'],
            ],
        ],

        'layout' => [
            'label' => 'تخطيطات',
            'icon' => '\begin{smallmatrix}a&b\\\\c&d\end{smallmatrix}',
            'symbols' => [
                [
                    'tex' => '\begin{cases} \square \\\\ \square \end{cases}',
                    'preview' => '\begin{cases}a\\\\b\end{cases}',
                    'label' => 'نظام معادلات',
                ],
                [
                    'tex' => '\begin{pmatrix} \square & \square \\\\ \square & \square \end{pmatrix}',
                    'preview' => '\begin{pmatrix}a&b\\\\c&d\end{pmatrix}',
                    'label' => 'مصفوفة',
                ],
                ['tex' => '\binom{\square}{\square}', 'preview' => '\binom{n}{k}', 'label' => 'توفيقة'],
                ['tex' => '\underbrace{\square}_{\square}', 'preview' => '\underbrace{ab}_{c}', 'label' => 'قوس سفلي'],
                ['tex' => '\overset{\square}{\square}', 'preview' => '\overset{a}{b}', 'label' => 'فوق'],
                ['tex' => '\text{\square}', 'preview' => '\text{نصّ}', 'label' => 'نصّ داخل المعادلة'],
            ],
        ],
    ],

    /*
     | قوالب جاهزة: معادلة كاملة بضغطة واحدة.
     |
     | المدرّس يكتب المعادلة التربيعية عشرين مرة في الترم — فالقالب
     | يوفّر عليه عشرين كتابة، لا ضغطة واحدة.
     */
    'templates' => [
        ['tex' => '$\square x^2 + \square x + \square = 0$', 'preview' => 'ax^2+bx+c=0', 'label' => 'معادلة تربيعية'],
        ['tex' => '$x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}$', 'preview' => 'x=\frac{-b\pm\sqrt{b^2-4ac}}{2a}', 'label' => 'القانون العام'],
        ['tex' => '$c^2 = a^2 + b^2$', 'preview' => 'c^2=a^2+b^2', 'label' => 'فيثاغورس'],
        ['tex' => '$\frac{\square}{\square} = \frac{\square}{\square}$', 'preview' => '\frac{a}{b}=\frac{c}{d}', 'label' => 'تناسب'],
        ['tex' => '$\text{المساحة} = \square \times \square$', 'preview' => 'A=l\times w', 'label' => 'مساحة مستطيل'],
        ['tex' => '$\text{المحيط} = 2\pi r$', 'preview' => 'P=2\pi r', 'label' => 'محيط دائرة'],
    ],
];
