<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 | الجدولة.
 |
 | كل مهمة withoutOverlapping: دورة تتأخّر لا يجوز أن تتراكب مع
 | التي بعدها، وإلا أُرسلت الرسالة نفسها مرتين.
 */

Schedule::command('growth:run')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground();

/*
 | دورة الفوترة — مرة كل يوم، لا كل ربع ساعة.
 |
 | تُغيّر حالات مشتركين وتُصدر فواتير: ما يُقاس باليوم يُنفَّذ باليوم،
 | وتكرارُه أكثر يزيد فرص إصدار فاتورة مرتين ولا يزيد دقّة.
 |
 | الفجر توقيت مقصود: التعليق يقع قبل أن يبدأ دوام السناتر، فلا
 | يُقفل الباب على مشترك في منتصف حصّته.
 */
Schedule::command('billing:run')
    ->dailyAt('03:30')
    ->withoutOverlapping(60)
    ->runInBackground();
