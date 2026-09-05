<?php

declare(strict_types=1);

use App\Core\Localization\DatabaseTranslationLoader;
use App\Core\Localization\StringScanner;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\DB;

/*
| محرّر نصوص الواجهة.
|
| والاختبار هنا لأن الميزة كلّها سطرٌ واحد: لو لم يمرّ المحمّل على
| القاعدة لبقيت الشاشة تحفظ ترجماتٍ لا تظهر — والمشترك يترجم مئة
| نصّ ثم يكتشف أن شيئاً لم يتغيّر.
*/

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);

    $this->tenant = provision();
});

function storeTranslation(string $locale, string $source, string $value): void
{
    DB::table('translations')->updateOrInsert(
        ['locale' => $locale, 'source_hash' => sha1($source)],
        ['source' => $source, 'value' => $value, 'created_at' => now(), 'updated_at' => now()],
    );

    DatabaseTranslationLoader::forget($locale);

    // المترجِم يحتفظ بما حمّله؛ ونسخةٌ جديدة تُجبره على القراءة ثانيةً
    app()->forgetInstance('translator');
    app()->forgetInstance('translation.loader');
}

it('actually changes what __() returns', function () {
    $this->tenant->run(function (): void {
        storeTranslation('en', 'المجموعات', 'Groups');

        app()->setLocale('en');

        expect(__('المجموعات'))->toBe('Groups');
    });
});

it('lets a tenant reword its own language, not only translate', function () {
    $this->tenant->run(function (): void {
        /*
         | «كورس» عند مدرّس و«دورة» عند مركز تدريب و«مقرّر» عند جامعة.
         |
         | ومنصّةٌ تفرض كلمتها تُقرأ غريبةً على طلبة كلٍّ منهم.
         */
        storeTranslation('ar', 'الكورسات', 'المقرّرات');

        app()->setLocale('ar');

        expect(__('الكورسات'))->toBe('المقرّرات');
    });
});

it('leaves an untranslated string as it was written', function () {
    $this->tenant->run(function (): void {
        storeTranslation('en', 'المجموعات', 'Groups');

        app()->setLocale('en');

        /*
         | ولا يُرجَع فراغ.
         |
         | صفحةٌ نصفها فارغ أسوأ من صفحةٍ نصفها بلغةٍ أخرى: الثانية
         | تُقرأ، والأولى تبدو معطوبة.
         */
        expect(__('نصٌّ لم يُترجَم أبداً'))->toBe('نصٌّ لم يُترجَم أبداً');
    });
});

it('keeps one tenant translations out of another', function () {
    $other = provision(['name' => 'أكاديمية أخرى', 'owner_email' => 'other@example.test']);

    $this->tenant->run(fn () => storeTranslation('en', 'المجموعات', 'Groups'));

    $other->run(function (): void {
        app()->setLocale('en');

        // جدول الترجمات في قاعدة كل مشترك — وكلمةُ جارِه ليست كلمتَه
        expect(__('المجموعات'))->toBe('المجموعات');
    });
});

it('finds the strings that are actually written in the code', function () {
    $strings = app(StringScanner::class)->all(fresh: true);

    /*
     | ولا تُكتب قائمةٌ بيدنا.
     |
     | النصوص بالآلاف وتتغيّر مع كل شاشة؛ وقائمةٌ مكتوبة تتخلّف عن
     | الكود في أسبوع، فيبحث المشترك عن نصٍّ يراه ولا يجده.
     */
    expect(count($strings))->toBeGreaterThan(1000)
        ->and($strings)->toContain('المجموعات');
});

it('keeps placeholders visible so a translator does not drop them', function () {
    $strings = app(StringScanner::class)->all(fresh: true);

    $withPlaceholder = array_filter($strings, fn (string $s): bool => str_contains($s, ':'));

    // مترجِمٌ يحذف `:name` يُنتج جملةً بلا اسم الطالب، ولا خطأ يُنبّهه
    expect($withPlaceholder)->not->toBeEmpty();
});
