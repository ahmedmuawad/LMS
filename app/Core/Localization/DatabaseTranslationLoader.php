<?php

declare(strict_types=1);

namespace App\Core\Localization;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * محمّلُ ترجماتٍ يقرأ من قاعدة المشترك بعد الملفّات.
 *
 * ## لماذا يُغلَّف المحمّل لا تُستبدَل `__()`
 *
 * `__()` تُنادى في آلاف المواضع وفي حزم لارافيل نفسها. واستبدالها
 * يعني موضعاً يُنسى، ورسالةَ تحقّقٍ تخرج بالإنجليزية وسط شاشةٍ عربية.
 * والمحمّل نقطةٌ واحدة يمرّ بها كلُّ نصّ.
 *
 * ## ومغلِّفٌ لا وارث
 *
 * الوراثةُ من `FileLoader` تعني تسجيلَ صنفٍ بديل في الحاوية، وترتيبُ
 * المزوّدين يحسم أيّهما يبقى — وقد جرّبتُها فبقي محمّل لارافيل
 * وذهبت ترجماتُ المشترك بلا خطأ ولا أثر.
 *
 * والتغليف بـ`extend` يُطبَّق لحظة الطلب لا لحظة التسجيل، فلا يهمّ
 * ترتيبُ أحد. ويحتفظ بالمحمّل الأصلي كما هو بمساراته ونطاقاته
 * المسجَّلة من الحزم.
 *
 * ## والقراءة مرّةً لكل طلب لا لكل نصّ
 *
 * لارافيل تنادي `load()` مرّةً لكل لغة ثم تحتفظ بالناتج؛ فالاستعلام
 * واحدٌ لا ألف. ومع ذلك يُخزَّن مؤقتاً: صفحةٌ فيها أربعمئة نصّ لا
 * تستحقّ استعلاماً في كل زيارة.
 *
 * ## وما لم يُترجَم يبقى عربياً
 *
 * لا نُرجع فراغاً لمفتاحٍ بلا ترجمة: صفحةٌ نصفها فارغ أسوأ من صفحةٍ
 * نصفها بلغةٍ أخرى — الثانية تُقرأ، والأولى تبدو معطوبة.
 */
final class DatabaseTranslationLoader implements Loader
{
    /** كم يبقى الكاش — والتحرير يُبطله فوراً على أي حال */
    private const TTL_MINUTES = 60;

    public function __construct(private readonly Loader $inner) {}

    /**
     * @param  string  $locale
     * @param  string  $group
     * @param  string|null  $namespace
     * @return array<string, mixed>
     */
    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->inner->load($locale, $group, $namespace);

        /*
         | نصوص JSON وحدها.
         |
         | `$group === '*'` هي نداء لارافيل لنصوص `__('نصّ كامل')`؛
         | أمّا المجموعات (`validation.php` وأخواتها) فمفاتيحُها رموزٌ
         | لا نصوص، ولا يحرّرها المشترك من الشاشة.
         */
        if ($group !== '*' || $namespace !== '*') {
            return $lines;
        }

        // ما كتبه المشترك يعلو على الملفّات: كلمتُه هي الأخيرة
        return array_merge($lines, $this->fromDatabase((string) $locale));
    }

    /** @return array<string, string> */
    private function fromDatabase(string $locale): array
    {
        $tenant = tenant();

        if ($tenant === null) {
            return [];
        }

        try {
            return Cache::remember(
                'translations:'.$tenant->getTenantKey().':'.$locale,
                now()->addMinutes(self::TTL_MINUTES),
                fn (): array => DB::table('translations')
                    ->where('locale', $locale)
                    ->whereNotNull('value')
                    ->where('value', '!=', '')
                    ->pluck('value', 'source')
                    ->all(),
            );
        } catch (Throwable) {
            /*
             | مشتركٌ لم تصله الهجرة بعد يجب أن يعمل موقعه.
             |
             | وسقوطُ كل نصٍّ في المنصّة لأجل جدولٍ غائب ثمنٌ لا يُحتمل.
             */
            return [];
        }
    }

    /** يُنسى المحفوظ بعد كل تحرير — وإلا بقي القديم ساعة */
    public static function forget(string $locale): void
    {
        $tenant = tenant();

        if ($tenant !== null) {
            Cache::forget('translations:'.$tenant->getTenantKey().':'.$locale);
        }
    }

    /**
     * @param  string  $namespace
     * @param  string  $hint
     */
    public function addNamespace($namespace, $hint): void
    {
        $this->inner->addNamespace($namespace, $hint);
    }

    /** @param  string  $path */
    public function addJsonPath($path): void
    {
        $this->inner->addJsonPath($path);
    }

    /** @return array<string, string> */
    public function namespaces()
    {
        return $this->inner->namespaces();
    }
}
