<?php

declare(strict_types=1);

namespace App\Core\Localization;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\FileLoader;
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
final class DatabaseTranslationLoader extends FileLoader
{
    /** كم يبقى الكاش — والتحرير يُبطله فوراً على أي حال */
    private const TTL_MINUTES = 60;

    /**
     * @param  string  $locale
     * @return array<string, string>
     */
    protected function loadJsonPaths($locale)
    {
        // الملفّات أولاً، ثم يعلوها ما كتبه المشترك: كلمتُه هي الأخيرة
        return array_merge(parent::loadJsonPaths($locale), $this->fromDatabase($locale));
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
}
