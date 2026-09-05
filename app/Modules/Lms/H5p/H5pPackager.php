<?php

declare(strict_types=1);

namespace App\Modules\Lms\H5p;

use App\Modules\Lms\Packaging\SafeZip;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * فكّ حزمة H5P وقراءة بيانها.
 *
 * الحزمة ملفّ ZIP امتداده `.h5p` يحوي:
 * - `h5p.json` — العنوان والمكتبة الرئيسة وتبعيّاتها
 * - `content/content.json` — المحتوى نفسه
 * - مجلّدُ مكتبةٍ لكل تبعية، وفيها شيفرتها وأنماطها
 *
 * فالحزمة تحمل مشغّلها معها؛ ولا نحتاج خادم H5P ولا مكتبةً مركزية —
 * وهذا ما يجعل تشغيلها ممكناً في منصّةٍ متعدّدة المستأجرين بلا أن
 * تتصادم مكتبات مشترِكٍ بمكتبات آخر.
 */
final class H5pPackager
{
    public function __construct(private readonly SafeZip $zip) {}

    /**
     * @return array{path:string, title:?string, main_library:?string, size:int}
     *
     * @throws RuntimeException
     */
    public function extract(UploadedFile $file): array
    {
        $folder = 'h5p/'.Str::random(24);
        $root = storage_path('app/public/'.$folder);

        $total = $this->zip->into((string) $file->getRealPath(), $root);

        $manifest = $this->readManifest($root);

        if ($manifest === null) {
            File::deleteDirectory($root);

            throw new RuntimeException(__('لا يوجد h5p.json في الحزمة — هذه ليست حزمة H5P.'));
        }

        if (! File::exists($root.'/content/content.json')) {
            File::deleteDirectory($root);

            throw new RuntimeException(__('الحزمة بلا محتوى (content/content.json مفقود).'));
        }

        return [...$manifest, 'path' => $folder, 'size' => $total];
    }

    /**
     * @return array{title:?string, main_library:?string}|null
     */
    private function readManifest(string $root): ?array
    {
        $file = $root.'/h5p.json';

        if (! File::exists($file)) {
            return null;
        }

        $json = json_decode((string) File::get($file), true);

        if (! is_array($json)) {
            return null;
        }

        return [
            'title' => filled($json['title'] ?? null) ? mb_substr((string) $json['title'], 0, 255) : null,
            'main_library' => filled($json['mainLibrary'] ?? null)
                ? mb_substr((string) $json['mainLibrary'], 0, 255)
                : null,
        ];
    }
}
