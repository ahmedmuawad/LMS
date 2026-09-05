<?php

declare(strict_types=1);

namespace App\Core\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * إبطال كاش الصفحات حين يتغيّر ما فيها.
 *
 * ## بلاه يصير الكاش عطباً لا تحسيناً
 *
 * مشتركٌ يصحّح سعر كورسٍ أو ينشر مقالاً، ثم يفتح موقعه فيرى القديم
 * ساعةً كاملة. فيظنّ الحفظ لم ينجح، فيحفظ ثانيةً وثالثة — ثم يفتح
 * لنا تذكرة. وهذا أسوأ من موقعٍ بطيء: البطء يُحتمَل، والكذب لا.
 *
 * ## والإبطال بالجملة لا بالصفحة
 *
 * تتبّعُ أيّ صفحةٍ يظهر فيها الكورس (الرئيسية؟ الكتالوج؟ صفحته؟
 * صفحة تصنيفه؟) دفترٌ يتخلّف عن الكود. ومسحُ كاش المشترك كلّه عند
 * أيّ نشرٍ أرخص وأصدق: النشر نادر، والصفحة تُبنى مرّةً بعده.
 *
 * ## ولا يمسّ كاش غيره
 *
 * `Cache::flush()` تمسح كل شيء للمنصّة كلّها — الجلسات والحدود
 * والإعدادات ولكل المشتركين. والوسم يجعل المسح يقف عند حدّ صاحبه.
 */
final class PageCache
{
    /**
     * الرقم الذي يدخل في كل مفتاح.
     *
     * ورفعُه يُبطل كل ما قبله بلا مرورٍ على المفاتيح واحداً واحداً —
     * وهو ما لا يستطيعه مخزّنٌ مثل Redis بلا مسحٍ شامل.
     */
    private const VERSION_KEY = 'page-cache:version:';

    public static function version(): int
    {
        $tenant = tenant();

        if ($tenant === null) {
            return 0;
        }

        try {
            return (int) Cache::get(self::VERSION_KEY.$tenant->getTenantKey(), 1);
        } catch (Throwable) {
            return 0;
        }
    }

    /** يُبطل كل صفحات هذا المشترك */
    public static function flush(): void
    {
        $tenant = tenant();

        if ($tenant === null) {
            return;
        }

        try {
            Cache::forever(self::VERSION_KEY.$tenant->getTenantKey(), self::version() + 1);
        } catch (Throwable) {
            // عطلٌ في مخزّن الكاش لا يمنع الحفظ نفسه
        }
    }
}
