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
