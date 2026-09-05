<?php

declare(strict_types=1);

namespace App\Modules\Lms\Scorm;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * فكّ حزمة SCORM وقراءة بيانها.
 *
 * ## الأمان أوّلاً
 *
 * الحزمة ملفّ ZIP يرفعه المشترك، وفكُّه بلا فحص ثغرةٌ معروفة
 * (Zip Slip): مدخلٌ اسمه `../../../.env` يكتب خارج المجلد المقصود.
 * فكلّ مسارٍ يُطبَّع ويُقارَن بالجذر قبل الكتابة، وما خرج عنه
 * يُرفَض والحزمة كلّها تُرمى — لا يُتخطّى المدخل ويُكمَل، لأن
 * حزمةً تحاول ذلك لا يُوثق ببقيّتها.
 *
 * وما يُفكّ يُقدَّم من مسارٍ عام؛ فالملفّات التنفيذية (php, phtml,
 * htaccess) تُمنَع كذلك — حزمةُ تعلّمٍ لا تحتاجها، ووجودها يعني
 * محاولة.
 */
final class ScormPackager
{
    /** امتدادات لا تدخل الحزمة مهما كانت */
    private const FORBIDDEN = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'htaccess', 'sh', 'exe'];

    /** أقصى حجمٍ بعد الفكّ — حزمةٌ تنتفخ تملأ القرص (Zip bomb) */
    private const MAX_BYTES = 524_288_000; // ٥٠٠ ميجا

    /**
     * @return array{path:string, entry:string, title:?string, version:string, size:int}
     *
     * @throws RuntimeException
     */
    public function extract(UploadedFile $file, string $disk): array
    {
        $zip = new ZipArchive;

        if ($zip->open($file->getRealPath()) !== true) {
            throw new RuntimeException(__('تعذّر فتح الحزمة — تأكّد أنها ملف ZIP صالح.'));
        }

        $folder = 'scorm/'.Str::random(24);
        $root = storage_path('app/public/'.$folder);

        File::ensureDirectoryExists($root, 0755);

        $total = 0;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                $stat = $zip->statIndex($i);

                if (str_ends_with($name, '/')) {
                    continue;
                }

                $total += (int) ($stat['size'] ?? 0);

                if ($total > self::MAX_BYTES) {
                    throw new RuntimeException(__('الحزمة أكبر من الحدّ المسموح بعد الفكّ.'));
                }

                $target = $this->safePath($root, $name);
                $extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if (in_array($extension, self::FORBIDDEN, true)) {
                    throw new RuntimeException(__('الحزمة تحتوي ملفّاً غير مسموح: :name', ['name' => $name]));
                }

                File::ensureDirectoryExists(dirname($target), 0755);
                file_put_contents($target, $zip->getFromIndex($i));
            }
        } catch (RuntimeException $e) {
            $zip->close();
            File::deleteDirectory($root);

            throw $e;
        }

        $zip->close();

        $manifest = $this->readManifest($root);

        if ($manifest === null) {
            File::deleteDirectory($root);

            throw new RuntimeException(__('لا يوجد imsmanifest.xml في الحزمة — هذه ليست حزمة SCORM.'));
        }

        return [...$manifest, 'path' => $folder, 'size' => $total];
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

    /**
     * يقرأ البيان: النسخة ونقطة البداية والعنوان.
     *
     * @return array{entry:string, title:?string, version:string}|null
     */
    private function readManifest(string $root): ?array
    {
        $file = $root.'/imsmanifest.xml';

        if (! File::exists($file)) {
            return null;
        }

        /*
         | تعطيل الكيانات الخارجية.
         |
         | XML يرفعه المشترك، وكيانٌ خارجي فيه يقرأ ملفّات الخادم
         | ويُرسلها (XXE). و`LIBXML_NONET` تمنع الشبكة كذلك.
         */
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($file, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOENT);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return null;
        }

        $entry = $this->firstResourceHref($xml);

        if ($entry === null) {
            return null;
        }

        $schemaVersion = (string) ($xml->metadata->schemaversion ?? '');

        return [
            'entry' => $entry,
            'title' => trim((string) ($xml->organizations->organization->title ?? '')) ?: null,
            'version' => str_contains($schemaVersion, '2004') ? '2004' : '1.2',
        ];
    }

    private function firstResourceHref(SimpleXMLElement $xml): ?string
    {
        foreach ($xml->resources->resource ?? [] as $resource) {
            $href = (string) ($resource['href'] ?? '');

            if ($href !== '') {
                return ltrim($href, './');
            }
        }

        return null;
    }
}
