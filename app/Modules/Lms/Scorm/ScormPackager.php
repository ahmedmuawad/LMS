<?php

declare(strict_types=1);

namespace App\Modules\Lms\Scorm;

use App\Modules\Lms\Packaging\SafeZip;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;

/**
 * فكّ حزمة SCORM وقراءة بيانها.
 *
 * والفكّ نفسه في [SafeZip]: حراسةُ الحزم واحدة لـSCORM وH5P، ولو
 * نُسخت لصنفين افترقتا — تُسدّ ثغرةٌ في أحدهما وتبقى في الآخر.
 */
final class ScormPackager
{
    public function __construct(private readonly SafeZip $zip) {}

    /**
     * @return array{path:string, entry:string, title:?string, version:string, size:int}
     *
     * @throws RuntimeException
     */
    public function extract(UploadedFile $file, string $disk): array
    {
        $folder = 'scorm/'.Str::random(24);
        $root = storage_path('app/public/'.$folder);

        $total = $this->zip->into((string) $file->getRealPath(), $root);

        $manifest = $this->readManifest($root);

        if ($manifest === null) {
            File::deleteDirectory($root);

            throw new RuntimeException(__('لا يوجد imsmanifest.xml في الحزمة — هذه ليست حزمة SCORM.'));
        }

        return [...$manifest, 'path' => $folder, 'size' => $total];
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
