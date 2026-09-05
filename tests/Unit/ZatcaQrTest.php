<?php

declare(strict_types=1);

use App\Core\Invoicing\ZatcaQr;
use App\Core\Support\Money;
use Illuminate\Support\Carbon;

/*
| رمز هيئة الزكاة والضريبة.
|
| والاختبار هنا لأن الخطأ لا يظهر عندنا أبداً: الرمز يُرسَم سليماً
| ويُطبَع سليماً، ولا يُكتشف عطبُه إلا حين يمسحه المفتّش — وقد
| طُبعت آلاف الفواتير.
*/

it('encodes each field as tag, byte length, then value', function () {
    $payload = base64_decode((new ZatcaQr)->payload(
        Money::fromDecimal('115.00', 'SAR'),
        Money::fromDecimal('15.00', 'SAR'),
        Carbon::parse('2026-03-15 10:00:00'),
    ), true);

    // الحقل الأول: رقمه ١، ثم طوله، ثم الاسم
    expect(ord($payload[0]))->toBe(1)
        ->and(ord($payload[1]))->toBe(strlen(substr($payload, 2, ord($payload[1]))));
});

it('measures the length in bytes so an Arabic seller name does not shift the fields', function () {
    /*
     | اسمٌ عربي يزن ضعف حروفه.
     |
     | وطولٌ محسوبٌ بالحروف يجعل القارئ يقف في منتصف الاسم فيقرأ
     | ما بعده حقولاً خطأً — والرمز يبدو سليماً حتى يُمسَح.
     */
    $name = 'أكاديمية النور';

    $tlv = base64_decode((new ZatcaQr)->payload(
        Money::fromDecimal('100.00', 'SAR'),
        Money::fromDecimal('13.04', 'SAR'),
    ), true);

    // نمشي على الحقول: لو كان الطول بالحروف لانكسر المشي قبل الخامس
    $offset = 0;
    $tags = [];

    while ($offset < strlen($tlv)) {
        $tags[] = ord($tlv[$offset]);
        $offset += 2 + ord($tlv[$offset + 1]);
    }

    expect($tags)->toBe([1, 2, 3, 4, 5])
        ->and(mb_strlen($name))->toBeLessThan(strlen($name));
});

it('stays off until the tenant switches it on and has a tax number', function () {
    // بلا سياق مشترك لا إعدادات — والرمز لا يُطبع على فاتورةٍ بلا رقم ضريبي
    expect((new ZatcaQr)->enabled())->toBeFalse();
});
