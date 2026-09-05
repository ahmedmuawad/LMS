<?php

declare(strict_types=1);

use App\Core\Settings\SettingsRepository;
use App\Core\Support\Dates;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Carbon;

/*
| التقويم والأرقام — عرضٌ يتبع إعداد المشترك.
|
| والاختبار هنا لأن الخطأ صامت: تقويمٌ لا يُقرأ يعرض ميلادياً لمن
| اختار الهجري، ولا يشتكي أحدٌ في اللوج — يشتكي المشترك بعد شهر.
*/

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);

    // الإعدادات جدولٌ في قاعدة المشترك — فلا قراءة لها خارج سياقه
    tenancy()->initialize(provision());
});

function useLocaleSettings(string $calendar, string $numerals): void
{
    $settings = app(SettingsRepository::class);
    $settings->set('locale.calendar', $calendar);
    $settings->set('locale.numerals', $numerals);
    $settings->flush();
}

it('shows the Gregorian calendar by default', function () {
    useLocaleSettings('gregorian', 'western');

    expect(app(Dates::class)->fromPhpPattern(Carbon::parse('2026-03-15'), 'j F Y'))
        ->toContain('2026');
});

it('shows the Hijri year when the tenant asks for it', function () {
    useLocaleSettings('hijri', 'western');

    $shown = app(Dates::class)->fromPhpPattern(Carbon::parse('2026-03-15'), 'j F Y');

    // ١٥ مارس ٢٠٢٦ يوافق رمضان ١٤٤٧
    expect($shown)->toContain('1447')->not->toContain('2026');
});

it('shows both calendars together when asked', function () {
    useLocaleSettings('both', 'western');

    expect(app(Dates::class)->fromPhpPattern(Carbon::parse('2026-03-15'), 'j F Y'))
        ->toContain('1447')->toContain('2026');
});

it('converts digits only when the tenant chose eastern numerals', function () {
    useLocaleSettings('gregorian', 'western');
    expect(app(Dates::class)->digits('2026'))->toBe('2026');

    useLocaleSettings('gregorian', 'eastern');
    expect(app(Dates::class)->digits('2026'))->toBe('٢٠٢٦');
});

it('keeps time patterns intact when translating them to ICU', function () {
    useLocaleSettings('gregorian', 'western');

    // `g:i a` كانت تخرج بأرقام الربع لو مُرِّر الحرف بلا اقتباس
    expect(app(Dates::class)->fromPhpPattern(Carbon::parse('2026-03-15 17:05'), 'j M · g:i a'))
        ->toContain('5:05');
});

it('returns nothing for a missing date so callers can fall back', function () {
    expect(display_date(null))->toBeNull();
});
