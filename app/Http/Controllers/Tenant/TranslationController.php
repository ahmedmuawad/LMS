<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Core\Access\Ability;
use App\Core\Localization\DatabaseTranslationLoader;
use App\Core\Localization\StringScanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * محرّر نصوص الواجهة.
 *
 * ## يحلّ مشكلتين بشاشةٍ واحدة
 *
 * الأولى: المنصّة تُوجّه `/en/` وتكتب `lang="en"` ولا ملفَّ ترجمةٍ
 * واحد فيها — فزائرٌ اختار الإنجليزية يرى صفحةً إنجليزية الاتجاه
 * عربية النصّ.
 *
 * والثانية: «كورس» عند مدرّس و«دورة» عند مركز تدريب و«مقرّر» عند
 * جامعة. ومنصّةٌ تفرض كلمتها تُقرأ غريبةً على طلبة كلٍّ منهم.
 *
 * ## و«غير المترجَم» هو الفلتر الافتراضي
 *
 * أربعة آلاف نصّ لا يجلس أحدٌ يمرّ عليها؛ ومن يفتح الشاشة يريد
 * ما ينقصه لا ما تمّ.
 */
final class TranslationController
{
    /** كم سطراً في الصفحة — والقائمة بالآلاف */
    private const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $this->authorise($request);

        $locale = $this->locale($request);
        $saved = $this->saved($locale);

        $strings = collect(app(StringScanner::class)->all())
            ->filter(fn (string $source): bool => $this->matches($request, $source, $saved));

        return view('tenant.translations', [
            'locale' => $locale,
            'locales' => $this->locales(),
            'filter' => (string) $request->query('filter', 'missing'),
            'search' => (string) $request->query('q', ''),
            'saved' => $saved,
            'rows' => $this->paginate($request, $strings),

            /*
             | العدّادات تُحسب على القائمة كلّها لا على الصفحة.
             |
             | «٤٠ من ٤٠٨٣» جوابٌ عن سؤالٍ لم يُطرح؛ والمشترك يسأل
             | كم بقي عليه.
             */
            'total' => count(app(StringScanner::class)->all()),
            'done' => $saved->filter(fn (?string $v): bool => filled($v))->count(),
        ]);
    }

    /** حفظ صفحةٍ كاملة دفعةً واحدة */
    public function store(Request $request): RedirectResponse
    {
        $this->authorise($request);

        $locale = $this->locale($request);

        $input = $request->validate([
            'values' => ['array'],
            'values.*' => ['nullable', 'string', 'max:4000'],
        ]);

        foreach ((array) ($input['values'] ?? []) as $encoded => $value) {
            /*
             | المفتاح يصل مُرمَّزاً بـbase64.
             |
             | اسمُ الحقل في HTML هو النصّ العربي نفسه، وفيه نقاطٌ
             | وأقواسٌ ومسافات — وPHP تُحوّل النقطة في اسم الحقل إلى
             | شرطةٍ سفلية، فيعود مفتاحٌ غير الذي أُرسل.
             */
            $source = base64_decode((string) $encoded, true);

            if ($source === false || $source === '') {
                continue;
            }

            $this->save($locale, $source, $value === null ? null : trim($value));
        }

        DatabaseTranslationLoader::forget($locale);

        return back()->with('status', __('حُفظت الترجمات.'));
    }

    /** يُعيد المسح بعد نشرٍ جديد */
    public function rescan(Request $request): RedirectResponse
    {
        $this->authorise($request);

        app(StringScanner::class)->all(fresh: true);

        return back()->with('status', __('أُعيد مسح النصوص.'));
    }

    private function save(string $locale, string $source, ?string $value): void
    {
        $hash = sha1($source);

        if (blank($value)) {
            /*
             | الفراغ يحذف ولا يحفظ فراغاً.
             |
             | صفٌّ بقيمةٍ فارغة يجعل النصّ يظهر فارغاً في الشاشة —
             | ومن مسح ترجمةً يريد العودة إلى الأصل لا إلى الفراغ.
             */
            DB::table('translations')->where('locale', $locale)->where('source_hash', $hash)->delete();

            return;
        }

        DB::table('translations')->updateOrInsert(
            ['locale' => $locale, 'source_hash' => $hash],
            ['source' => $source, 'value' => $value, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /** @return Collection<string, ?string> */
    private function saved(string $locale): Collection
    {
        return DB::table('translations')->where('locale', $locale)->pluck('value', 'source');
    }

    private function matches(Request $request, string $source, Collection $saved): bool
    {
        $search = trim((string) $request->query('q', ''));

        if ($search !== ''
            && ! str_contains(mb_strtolower($source), mb_strtolower($search))
            && ! str_contains(mb_strtolower((string) $saved->get($source)), mb_strtolower($search))) {
            return false;
        }

        return match ((string) $request->query('filter', 'missing')) {
            'done' => filled($saved->get($source)),
            'all' => true,
            default => blank($saved->get($source)),
        };
    }

    /** @param  Collection<int, string>  $strings */
    private function paginate(Request $request, Collection $strings): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));

        return new LengthAwarePaginator(
            $strings->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->values(),
            $strings->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    /**
     * اللغة المحرَّرة — وليست لغة الشاشة.
     *
     * من يحرّر الإنجليزية يفعل ذلك ولوحتُه عربية؛ وربطُهما يجبره
     * على تبديل لغة لوحته ليترجم.
     */
    private function locale(Request $request): string
    {
        $locale = (string) $request->query('locale', 'en');

        return array_key_exists($locale, $this->locales()) ? $locale : 'en';
    }

    /** @return array<string, string> */
    private function locales(): array
    {
        $supported = (array) config('locales.supported', []);
        $out = [];

        foreach ($supported as $code => $meta) {
            $code = is_string($code) ? $code : (string) ($meta['code'] ?? $meta);
            $out[$code] = is_array($meta) ? (string) ($meta['native'] ?? $meta['name'] ?? $code) : (string) $meta;
        }

        return $out !== [] ? $out : ['ar' => 'العربية', 'en' => 'English'];
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::SETTINGS_MANAGE), 403);
    }
}
