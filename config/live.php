<?php

declare(strict_types=1);

/*
 | مزوّدو الحصص المباشرة.
 |
 | كان الموجود حقلَ رابطٍ يلصق فيه المدرّس رابطاً أنشأه بنفسه خارج
 | المنصة: لا إنشاء غرفة، ولا رابطاً لكل حصة، ولا سجلّ دخول. وأربعة
 | مفاتيح في الباقات تَعِد بتكاملٍ لم يكن موجوداً.
 |
 | Jitsi أوّلاً عمداً: يعمل بلا حساب ولا مفاتيح ولا اشتراك، فيصير
 | «الحصص المباشرة» ميزةً تعمل من أول يوم لكل مشترك — ثم يرقّي من
 | يريد Zoom إلى حسابه هو.
 */

return [

    'default' => 'jitsi',

    'providers' => [

        'jitsi' => [
            'name' => ['ar' => 'Jitsi Meet', 'en' => 'Jitsi Meet'],
            'summary' => ['ar' => 'يعمل فوراً بلا حساب ولا مفاتيح.', 'en' => 'Works instantly — no account, no keys.'],

            // الخادم العام افتراضاً، ويُستبدل بخادم المشترك من الإعدادات
            'domain' => env('JITSI_DOMAIN', 'meet.jit.si'),

            'needs_keys' => false,
        ],

        'zoom' => [
            'name' => ['ar' => 'Zoom', 'en' => 'Zoom'],
            'summary' => ['ar' => 'يحتاج ربط حسابك على Zoom.', 'en' => 'Requires connecting your Zoom account.'],
            'needs_keys' => true,
        ],

        'meet' => [
            'name' => ['ar' => 'Google Meet', 'en' => 'Google Meet'],
            'summary' => ['ar' => 'يحتاج ربط حساب Google.', 'en' => 'Requires connecting a Google account.'],
            'needs_keys' => true,
        ],

        'bbb' => [
            'name' => ['ar' => 'BigBlueButton', 'en' => 'BigBlueButton'],
            'summary' => ['ar' => 'لخادم BigBlueButton خاص بك.', 'en' => 'For your own BigBlueButton server.'],
            'needs_keys' => true,
        ],

        'manual' => [
            'name' => ['ar' => 'رابط يدوي', 'en' => 'Manual link'],
            'summary' => ['ar' => 'تلصق رابط الاجتماع بنفسك.', 'en' => 'You paste the meeting link yourself.'],
            'needs_keys' => false,
        ],
    ],

    /*
     | نافذة فتح الرابط قبل الموعد وبعد انتهائه (بالدقائق).
     |
     | الرابط الظاهر طوال الأسبوع يُنسَخ ويُتداول خارج المشتركين،
     | والظاهر في اللحظة وحدها يُفوّت من تأخّر دقيقةً على اتصاله.
     */
    'opens_before_minutes' => 30,
    'closes_after_minutes' => 30,
];
