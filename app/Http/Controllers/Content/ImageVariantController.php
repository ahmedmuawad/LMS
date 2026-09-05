<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use App\Core\Media\ImageVariants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * يخدم نسخةً من صورة بمقاسٍ وصيغةٍ محدّدين.
 *
 * ## لماذا مسارٌ لا ملفّاتٌ ثابتة
 *
 * ملفّات المشتركين تحت `storage/` لا `public/`، وكلٌّ في قرصه.
 * ونسخُها إلى مجلّدٍ عام يعني نسختين من كل صورة ومزامنةً بينهما.
 *
 * ## والمقاسات مغلقة
 *
 * `?w=` مفتوحٌ يعني أن أيَّ زائرٍ يطلب ألف مقاسٍ في الدقيقة، فيشغل
 * المعالج ويملأ القرص بصورٍ لا يراها أحد. والقائمة المغلقة تجعل
 * أسوأ ما يستطيعه ستّ صورٍ لكل أصل.
 */
final class ImageVariantController
{
    public function __invoke(Request $request, int $width, string $format, string $path): Response
    {
        abort_unless(in_array($width, ImageVariants::WIDTHS, true), 404);
        abort_unless(in_array($format, ['webp', 'avif'], true), 404);

        /*
         | المسار يُنظَّف قبل استعماله.
         |
         | `..` في مسارٍ يأتي من الرابط يقرأ ملفّاتٍ خارج مجلّد
         | الوسائط — ومنها `.env`.
         */
        abort_if(str_contains($path, '..'), 404);

        $disk = (string) setting('integrations.storage_driver', 'public');
        $disk = Storage::getDefaultDriver() === $disk ? $disk : 'public';

        $variant = app(ImageVariants::class)->variant($disk, $path, $width, $format);

        // تعذّر التحويل: يُحوَّل إلى الأصل فلا تبقى الصفحة بفراغ
        if ($variant === null) {
            abort_unless(Storage::disk($disk)->exists($path), 404);

            return redirect(Storage::disk($disk)->url($path), 302);
        }

        return response()->file(Storage::disk($disk)->path($variant), [
            'Content-Type' => 'image/'.$format,

            /*
             | سنةٌ كاملة، وغير قابلٍ للتغيير.
             |
             | النسخة مشتقّةٌ من أصلٍ ثابت بمقاسٍ ثابت بصيغةٍ ثابتة،
             | فلا شيء فيها يتغيّر. وصورةٌ تُطلب في كل زيارة تُبطل
             | نصف فائدة تصغيرها.
             */
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
