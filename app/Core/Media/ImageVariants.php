<?php

declare(strict_types=1);

namespace App\Core\Media;

use Illuminate\Support\Facades\Storage;
use Imagick;
use Throwable;

/**
 * نسخُ الصورة: مقاساتُها وصيغُها.
 *
 * ## إعدادٌ كان يَعِد ولا يفعل
 *
 * «نسخ متعدّدة المقاسات» و«صيغة الصور» و«جودة الضغط» حقولٌ في شاشة
 * الأداء منذ البداية ولا سطرَ يقرؤها. فترفع المدرّسة غلافاً بعرض
 * ٣٠٠٠ بكسل، ويُرسَل كما هو إلى هاتفٍ يعرضه في ٣٦٠ — ميجابايتان
 * من باقة طالبٍ في مصر ليرى صورةً واحدة.
 *
 * ## والتوليد عند الطلب لا عند الرفع
 *
 * توليدُ ستّ نسخٍ لكل صورة عند رفعها يجعل الرفع يستغرق دقيقة،
 * ويملأ القرص بمقاساتٍ لا يطلبها أحد. والمقاس يُبنى مرّة عند أوّل
 * طلب ثم يُقرأ من القرص.
 *
 * ## والصيغة تتبع ما يستطيعه الخادم
 *
 * AVIF أصغر من WebP بالثلث، لكن ليس كل خادمٍ يكتبها: GD عندنا
 * يكتبها محلّياً ولا يكتبها على السيرفر، وImagick يكتبها هناك.
 * ومنصّةٌ تَعِد بـAVIF ثم ترسل صورةً مكسورة أسوأ من منصّةٍ ترسل
 * WebP بلا وعد.
 */
final class ImageVariants
{
    /**
     * المقاسات المسموحة — قائمة مغلقة.
     *
     * وبلا إغلاقها يستطيع أيٌّ كان أن يطلب ألف مقاسٍ في الدقيقة،
     * فيُشغل المعالج ويملأ القرص بصورٍ لا يراها أحد.
     */
    public const WIDTHS = [320, 480, 640, 960, 1280, 1920];

    /** ما يُصغَّر: ما عداه يُرسل كما هو */
    private const RESIZABLE = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];

    /** أقرب مقاسٍ مسموح لما طُلب */
    public static function nearestWidth(int $width): int
    {
        foreach (self::WIDTHS as $allowed) {
            if ($width <= $allowed) {
                return $allowed;
            }
        }

        return (int) end(self::WIDTHS);
    }

    /** هل يستطيع هذا الخادم كتابة هذه الصيغة؟ */
    public function supports(string $format): bool
    {
        if ($format === 'webp') {
            return function_exists('imagewebp') || $this->imagick('WEBP');
        }

        if ($format === 'avif') {
            return function_exists('imageavif') || $this->imagick('AVIF');
        }

        return false;
    }

    /**
     * الصيغ التي تُعرض فعلاً، بترتيب الأفضلية.
     *
     * وتُقاطَع مع ما يستطيعه الخادم: إعدادُ المشترك رغبةٌ لا قدرة.
     *
     * @return list<string>
     */
    public function formats(): array
    {
        $wanted = match ((string) setting('performance.image_format', 'both')) {
            'avif' => ['avif'],
            'webp' => ['webp'],
            default => ['avif', 'webp'],
        };

        return array_values(array_filter($wanted, fn (string $f): bool => $this->supports($f)));
    }

    /**
     * مسار النسخة على القرص — يُبنى إن لم يكن موجوداً.
     *
     * @return string|null مسارٌ نسبي على القرص العام، أو null إن تعذّر
     */
    public function variant(string $disk, string $path, int $width, string $format): ?string
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            return null;
        }

        $target = 'derivatives/'.$width.'/'.$format.'/'.$path;

        if ($storage->exists($target)) {
            return $target;
        }

        try {
            $binary = $this->render($storage->path($path), $width, $format);
        } catch (Throwable) {
            /*
             | فشلُ التحويل يعيد الأصل لا خطأً.
             |
             | صورةٌ تالفة أو صيغةٌ لم يتوقّعها المحوّل يجب ألّا تترك
             | فراغاً في الصفحة — الأصل يُعرض ويُقرأ.
             */
            return null;
        }

        if ($binary === null) {
            return null;
        }

        $storage->put($target, $binary);

        return $target;
    }

    /** يُنشئ الصورة بالحجم والصيغة المطلوبين */
    private function render(string $source, int $width, string $format): ?string
    {
        $info = @getimagesize($source);

        if ($info === false || ! in_array($info['mime'] ?? '', self::RESIZABLE, true)) {
            return null;
        }

        $quality = max(40, min(100, (int) setting('performance.image_quality', 82)));

        // صورةٌ أصغر من المطلوب لا تُكبَّر: التكبير يزيد الحجم ويُنقص الوضوح
        $targetWidth = min($width, (int) $info[0]);

        return extension_loaded('imagick')
            ? $this->withImagick($source, $targetWidth, $format, $quality)
            : $this->withGd($source, $targetWidth, $format, $quality);
    }

    private function withImagick(string $source, int $width, string $format, int $quality): ?string
    {
        $image = new Imagick($source);
        $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);

        // البيانات الوصفية تُحذف: موضع التصوير في صورةٍ يرفعها مدرّس ليس شأن أحد
        $image->stripImage();

        $image->resizeImage($width, 0, Imagick::FILTER_LANCZOS, 1);
        $image->setImageFormat($format);
        $image->setImageCompressionQuality($quality);

        $binary = $image->getImageBlob();
        $image->clear();

        return $binary;
    }

    private function withGd(string $source, int $width, string $format, int $quality): ?string
    {
        $image = @imagecreatefromstring((string) file_get_contents($source));

        if ($image === false) {
            return null;
        }

        $scaled = imagescale($image, $width);
        imagedestroy($image);

        if ($scaled === false) {
            return null;
        }

        ob_start();

        $written = match ($format) {
            'avif' => function_exists('imageavif') && imageavif($scaled, null, $quality),
            'webp' => function_exists('imagewebp') && imagewebp($scaled, null, $quality),
            default => false,
        };

        $binary = (string) ob_get_clean();
        imagedestroy($scaled);

        return $written ? $binary : null;
    }

    private function imagick(string $format): bool
    {
        if (! extension_loaded('imagick')) {
            return false;
        }

        try {
            return (new Imagick)->queryFormats($format) !== [];
        } catch (Throwable) {
            return false;
        }
    }
}
