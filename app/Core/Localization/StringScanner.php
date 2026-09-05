<?php

declare(strict_types=1);

namespace App\Core\Localization;

use Illuminate\Support\Facades\Cache;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * يجمع نصوص الواجهة من الكود.
 *
 * ## لماذا لا تُكتب قائمةٌ بيدنا
 *
 * النصوص بالآلاف وتتغيّر مع كل شاشة؛ وقائمةٌ مكتوبة تتخلّف عن الكود
 * في أسبوع، فيبحث المشترك عن نصٍّ يراه في شاشته ولا يجده في المحرِّر.
 *
 * ## والمصدر هو المفتاح
 *
 * `__()` في هذا المشروع تأخذ العربية نفسها مفتاحاً — لا رمزاً مثل
 * `courses.title`. فما يُلتقط هو النصّ كما كُتب، وهو ما يُبحث عنه.
 *
 * ## ويُخزَّن الناتج مؤقتاً
 *
 * المسح يقرأ ألفَي ملف؛ وفعلُه في كل فتحةٍ للشاشة يجعلها تستغرق
 * ثوانيَ. ويُبطَل من زرٍّ في الشاشة نفسها بعد كل نشر.
 */
final class StringScanner
{
    /** أين تُكتب نصوص الواجهة */
    private const PATHS = ['app', 'resources/views', 'config', 'themes'];

    /**
     * ما يُلتقط: `__('…')` و`@lang('…')` و`trans('…')`.
     *
     * ولا يلتقط ما بين قوسين مزدوجين إن كان فيه متغيّر: `__("مرحبا $name")`
     * نصٌّ يتغيّر بتغيّر المتغيّر، ولا يصلح مفتاحاً.
     */
    private const PATTERN = '/(?:__|trans|@lang)\(\s*\'((?:[^\'\\\\]|\\\\.)+)\'/u';

    /** ساعةٌ — والزرّ يُبطلها فوراً */
    private const TTL_MINUTES = 60;

    /** @return list<string> */
    public function all(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget($this->key());
        }

        return Cache::remember($this->key(), now()->addMinutes(self::TTL_MINUTES), fn (): array => $this->scan());
    }

    /** @return list<string> */
    private function scan(): array
    {
        $found = [];

        foreach (self::PATHS as $path) {
            $full = base_path($path);

            if (! is_dir($full)) {
                continue;
            }

            $finder = (new Finder)->files()->in($full)->name(['*.php', '*.blade.php']);

            foreach ($finder as $file) {
                $this->fromFile($file, $found);
            }
        }

        /*
         | تُرتَّب أبجدياً لا بترتيب الملفّات.
         |
         | من يبحث عن نصٍّ يتصفّح؛ وترتيبٌ يتبع بنية المجلّدات يجعل
         | الجارَ في القائمة لا علاقة له بالجار.
         */
        $found = array_values(array_unique($found));
        sort($found, SORT_NATURAL | SORT_FLAG_CASE);

        return $found;
    }

    /** @param  list<string>  $found */
    private function fromFile(SplFileInfo $file, array &$found): void
    {
        $contents = (string) file_get_contents($file->getRealPath());

        if (! preg_match_all(self::PATTERN, $contents, $matches)) {
            return;
        }

        foreach ($matches[1] as $string) {
            // ما هرّبه الكود يُفكّ: `\'` في المصدر هو `'` في النصّ
            $string = str_replace(["\\'", '\\\\'], ["'", '\\'], $string);

            /*
             | ما فيه نائبٌ (`:name`) يُلتقط كما هو.
             |
             | هو مفتاحٌ صالح، والنائب يُملأ عند العرض — والمترجِم
             | يجب أن يبقيه في ترجمته أو ضاعت القيمة.
             */
            if (mb_strlen($string) > 1 && ! str_starts_with($string, '/')) {
                $found[] = $string;
            }
        }
    }

    public function forget(): void
    {
        Cache::forget($this->key());
    }

    private function key(): string
    {
        return 'i18n:strings:'.(tenant()?->getTenantKey() ?? 'central');
    }
}
