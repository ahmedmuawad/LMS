<?php

declare(strict_types=1);

namespace App\Modules\Lms\Packaging;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * فكُّ حزمةٍ يرفعها المشترك، بلا أن تخرج عن مجلّدها.
 *
 * ## لماذا صنفٌ مشترك
 *
 * SCORM وH5P كلاهما ملفّ ZIP يرفعه المشترك ويُقدَّم بعد فكّه من
 * مسارٍ عام. وحراستُهما واحدة، فلو نُسخت لصنفين افترقتا: تُسدّ ثغرةٌ
 * في أحدهما وتبقى في الآخر. فالحراسة هنا وحدها، والصنفان يقرآن
 * بيانَهما بعدها.
 *
 * ## ما يُحرَس
 *
 * - **Zip Slip**: مدخلٌ اسمه `../../../.env` يكتب خارج المجلد. فكلّ
 *   مسارٍ يُطبَّع، وما فيه `..` يُرفَض والحزمة كلّها تُرمى — لا
 *   يُتخطّى المدخل ويُكمَل، لأن حزمةً تحاول ذلك لا يُوثق ببقيّتها.
 * - **ملفّات تنفيذية**: ما يُفكّ يُقدَّم من مسارٍ عام، فـ`php` و
 *   `htaccess` ممنوعة — حزمةُ تعلّمٍ لا تحتاجها، ووجودها يعني محاولة.
 * - **Zip bomb**: حزمةٌ صغيرة تنتفخ إلى جيجابايتات فتملأ القرص.
 *   فالحجم يُجمع أثناء الفكّ ويُوقَف عند الحدّ.
 */
final class SafeZip
{
    /** امتدادات لا تدخل الحزمة مهما كانت */
    private const FORBIDDEN = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'htaccess', 'sh', 'exe'];

    /** أقصى حجمٍ بعد الفكّ */
    public const MAX_BYTES = 524_288_000; // ٥٠٠ ميجا

    /**
     * يفكّ الحزمة في `$root` ويُعيد مجموع الأحجام.
     *
     * وعند أيّ خطأ يُحذف المجلّد كلّه قبل رمي الاستثناء: نصفُ حزمةٍ
     * على القرص لا يُشغَّل ولا يُنظَّف لاحقاً.
     *
     * @throws RuntimeException
     */
    public function into(string $archivePath, string $root, int $maxBytes = self::MAX_BYTES): int
    {
        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException(__('تعذّر فتح الحزمة — تأكّد أنها ملف ZIP صالح.'));
        }

        File::ensureDirectoryExists($root, 0755);

        $total = 0;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);

                if (str_ends_with($name, '/')) {
                    continue;
                }

                $total += (int) ($zip->statIndex($i)['size'] ?? 0);

                if ($total > $maxBytes) {
                    throw new RuntimeException(__('الحزمة أكبر من الحدّ المسموح بعد الفكّ.'));
                }

                if (in_array(mb_strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::FORBIDDEN, true)) {
                    throw new RuntimeException(__('الحزمة تحتوي ملفّاً غير مسموح: :name', ['name' => $name]));
                }

                $target = $this->safePath($root, $name);

                File::ensureDirectoryExists(dirname($target), 0755);
                file_put_contents($target, $zip->getFromIndex($i));
            }
        } catch (RuntimeException $e) {
            $zip->close();
            File::deleteDirectory($root);

            throw $e;
        }

        $zip->close();

        return $total;
    }

    /**
     * يمنع الكتابة خارج جذر الحزمة.
     *
     * `realpath` لا يصلح هنا لأن الملفّ لم يُكتب بعد، فيُبنى المسار
     * يدوياً ويُقارَن نصّياً بالجذر — والمقارنة بعد إزالة `..` لا
     * قبلها.
     */
    private function safePath(string $root, string $name): string
    {
        $clean = str_replace('\\', '/', $name);
        $parts = [];

        foreach (explode('/', $clean) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new RuntimeException(__('الحزمة تحاول الكتابة خارج مجلّدها.'));
            }

            $parts[] = $segment;
        }

        if ($parts === []) {
            throw new RuntimeException(__('اسم ملفّ غير صالح داخل الحزمة.'));
        }

        return $root.'/'.implode('/', $parts);
    }
}
