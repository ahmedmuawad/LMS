<?php

declare(strict_types=1);

use App\Core\Settings\SettingsRepository;
use App\Core\Support\Dates;
use Illuminate\Support\Carbon;

if (! function_exists('setting')) {
    /**
     * setting()                          → المستودع نفسه
     * setting('lms.passing_percentage')  → القيمة
     */
    function setting(?string $path = null, mixed $default = null): mixed
    {
        $repository = app(SettingsRepository::class);

        return $path === null ? $repository : $repository->get($path, $default);
    }
}

if (! function_exists('site_name')) {
    /**
     * اسم الموقع كما ضبطه المشترك.
     *
     * ثلاثة مصادر بترتيب صحيح: إعداد المشترك أولاً — لأنه ما اختاره
     * هو — ثم اسمه في سجلّ الاشتراك، ثم اسم التطبيق. كان التعبير
     * مكرّراً في عشرة مواضع، فبقيت ثلاثة منها تقرأ سجلّ الاشتراك
     * وحده: يغيّر المشترك اسم موقعه فيتغيّر في الفواتير والبريد
     * ولا يتغيّر في قائمة لوحته ولا في شاشة دخوله.
     */
    function site_name(): string
    {
        return (string) (setting()->translated('general.site_name')
            ?: (tenant('name') ?? config('app.name')));
    }
}

if (! function_exists('site_description')) {
    /** الوصف المختصر — للسيو ولبطاقات المشاركة ولترويسة البريد. */
    function site_description(): string
    {
        return (string) (setting()->translated('general.tagline') ?: '');
    }
}

if (! function_exists('display_date')) {
    /**
     * تاريخٌ بتقويم المشترك وأرقامه — بديل `translatedFormat`.
     *
     * ## لماذا دالّةٌ عامّة لا مكوّن
     *
     * التواريخ في المنصّة تُكتب في اثنين وأربعين موضعاً، ونصفُها
     * داخل سمةٍ أو داخل `__(':date')` — ولا يُوضع مكوّنٌ في سمة.
     * فدالّةٌ واحدة تُبدَّل بها كلّها ويتغيّر التقويم من الإعدادات
     * في كل شاشة معاً.
     *
     * ويقبل نمط PHP لا ICU: هو المكتوب في المواضع كلّها، وترجمتُه
     * أرخص من إعادة كتابتها.
     */
    function display_date(mixed $date, string $pattern = 'j F Y'): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $carbon = $date instanceof Carbon
            ? $date
            : Carbon::parse((string) $date);

        return app(Dates::class)->fromPhpPattern($carbon, $pattern);
    }
}
