<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Core\Entitlements\Quota;
use App\Models\User;
use App\Modules\Content\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * رفع ملف إلى مكتبة الوسائط.
 *
 * البصمة تمنع تخزين الملف نفسه مرتين: مساحة المشترك محدودة بباقته،
 * ولا معنى لأن يدفع ثمن نسخة مكرّرة من شعاره.
 */
final class StoreMedia
{
    /** ما نقبله فعلاً — قائمة مغلقة، لا امتدادات يقرّرها المستخدم. */
    private const ALLOWED = [
        'image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif', 'image/svg+xml',
        'application/pdf', 'video/mp4', 'audio/mpeg', 'audio/mp4',
        'application/zip', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function handle(UploadedFile $file, ?User $uploader = null, ?string $folder = null): Media
    {
        if (! in_array($file->getMimeType(), self::ALLOWED, true)) {
            throw new RuntimeException(__('نوع الملف غير مسموح: :type', ['type' => $file->getMimeType()]));
        }

        $limit = (int) setting('general.max_upload_mb', 50) * 1024 * 1024;

        if ($file->getSize() > $limit) {
            throw new RuntimeException(__('حجم الملف يتجاوز الحد المسموح.'));
        }

        if ($file->getMimeType() === 'image/svg+xml') {
            $this->assertSvgIsInert($file);
        }

        $hash = hash_file('sha256', $file->getRealPath());
        $existing = Media::where('hash', $hash)->first();

        if ($existing !== null) {
            return $existing;
        }

        /*
         | حدّ مساحة الباقة — بعد فحص التكرار لا قبله.
         |
         | الملف المكرّر لا يشغل مساحة جديدة، فمنعُه عند بلوغ الحدّ
         | يمنع ما لا يكلّف شيئاً. والترتيب هنا هو الفرق بين حدٍّ
         | عادل وحدٍّ يبدو عشوائياً لمن يرفع شعاره مرتين.
         */
        app(Quota::class)->enforceStorage((int) $file->getSize());

        $disk = (string) setting('integrations.storage_driver', 'public');
        $disk = Storage::getDefaultDriver() === $disk ? $disk : 'public';

        $path = $file->store($folder ?? 'library', $disk);

        $dimensions = $this->dimensions($file);

        return Media::create([
            'disk' => $disk,
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
            'folder' => $folder,
            'hash' => $hash,
            'uploaded_by' => $uploader?->getKey(),
        ]);
    }

    /**
     * ملف SVG نصّ لا صورة: يُنفَّذ في المتصفّح على نطاق المشترك نفسه.
     *
     * شعار مرفوع من محرّر محتوى قد يحمل <script> فيسرق جلسة كل من
     * يفتح الصفحة. نرفض ما يحمل تنفيذاً بدل أن نثق بالامتداد.
     */
    private function assertSvgIsInert(UploadedFile $file): void
    {
        $content = (string) file_get_contents($file->getRealPath());

        $dangerous = ['<script', '<foreignobject', 'javascript:', '<!entity', '<iframe', '<embed'];

        foreach ($dangerous as $needle) {
            if (str_contains(mb_strtolower($content), $needle)) {
                throw new RuntimeException(__('ملف SVG يحتوي كوداً قابلاً للتنفيذ — ارفعه بصيغة PNG أو نظّفه أولاً.'));
            }
        }

        // on… تلتقط onload وonclick وأخواتها في وسم أو خاصية
        if (preg_match('/\son[a-z]+\s*=/i', $content) === 1) {
            throw new RuntimeException(__('ملف SVG يحتوي مُعالِج حدث — ارفعه بصيغة PNG أو نظّفه أولاً.'));
        }
    }

    /** @return array{0:?int, 1:?int} */
    private function dimensions(UploadedFile $file): array
    {
        if (! str_starts_with((string) $file->getMimeType(), 'image/')) {
            return [null, null];
        }

        $size = @getimagesize($file->getRealPath());

        return $size === false ? [null, null] : [(int) $size[0], (int) $size[1]];
    }
}
