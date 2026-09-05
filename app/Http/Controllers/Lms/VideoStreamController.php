<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Modules\Lms\LessonAccess;
use App\Modules\Lms\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * بثّ فيديو الدرس من خادمنا — محروساً.
 *
 * ## لماذا لا يُعطى الرابط مباشرةً
 *
 * كان درس «ملف مرفوع» يُعطي الطالب رابط الملفّ كما هو: يُنسَخ من
 * «مصدر الصفحة» في ثانية، ويُلصَق في مجموعةٍ فيفتحه من لم يدفع —
 * ويبقى يعمل إلى الأبد. فكل ما بُني من حماية (علامة مائية، منع
 * تنزيل، حدّ أجهزة) كان زينةً على بابٍ مفتوح.
 *
 * فالرابط الآن موقَّعٌ ينتهي بساعات، ويُفحص التسجيل عند كل طلب:
 * من انتهى اشتراكه يُردّ ولو كان الرابط بيده.
 *
 * ## والتوقيع لا يُغني عن فحص التسجيل
 *
 * الرابط الموقَّع يمنع من لم نُعطِه، ولا يمنع من أعطيناه ثم شاركه
 * قبل أن ينتهي. فالفحصان معاً: التوقيع للمدة، والتسجيل للشخص.
 *
 * ## ودعم Range شرطٌ لا تحسين
 *
 * المتصفّح يطلب مقاطع الفيديو بالبايتات ليقفز في الشريط. وردٌّ بلا
 * `Accept-Ranges` يجعل السحب يُعيد التحميل من أوّله — أو لا يعمل
 * أصلاً في سفاري.
 */
final class VideoStreamController
{
    /** ما يُقرأ من القرص دفعةً — أكبر منه يأكل الذاكرة، وأصغر يُكثر النداءات */
    private const CHUNK = 262_144; // ٢٥٦ ك.ب

    public function __invoke(Request $request, int $lessonId, LessonAccess $access): StreamedResponse
    {
        $lesson = Lesson::find($lessonId);

        abort_unless($access->grants($request->user(), $lesson), 403);
        abort_unless($lesson->video_provider === 'file' && filled($lesson->video_id), 404);

        $path = ltrim(str_replace('\\', '/', (string) $lesson->video_id), '/');

        // مسارٌ داخل التخزين وحده — لا رابطٌ خارجي ولا خروجٌ عن الجذر
        abort_if(str_contains($path, '..') || str_starts_with($path, 'http'), 404);

        $disk = Storage::disk(config('filesystems.default'));

        abort_unless($disk->exists($path), 404);

        $size = (int) $disk->size($path);
        [$start, $end] = $this->range($request, $size);

        $status = $request->headers->has('Range') ? 206 : 200;

        $headers = [
            'Content-Type' => $disk->mimeType($path) ?: 'video/mp4',
            'Content-Length' => (string) ($end - $start + 1),
            'Accept-Ranges' => 'bytes',
            'X-Content-Type-Options' => 'nosniff',

            /*
             | لا تخزين وسيط: النسخة المخبَّأة تبقى مقروءةً بعد
             | انتهاء اشتراك صاحبها، وهي أوّل ما يُنسى.
             */
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        ];

        if ($status === 206) {
            $headers['Content-Range'] = 'bytes '.$start.'-'.$end.'/'.$size;
        }

        return response()->stream(function () use ($disk, $path, $start, $end): void {
            $stream = $disk->readStream($path);

            if ($stream === null) {
                return;
            }

            fseek($stream, $start);

            $remaining = $end - $start + 1;

            while ($remaining > 0 && ! feof($stream)) {
                $chunk = fread($stream, (int) min(self::CHUNK, $remaining));

                if ($chunk === false) {
                    break;
                }

                echo $chunk;
                flush();

                $remaining -= strlen($chunk);
            }

            fclose($stream);
        }, $status, $headers);
    }

    /**
     * يقرأ ترويسة Range — وما لم يُفهم منها يُقرأ الملفّ كاملاً.
     *
     * @return array{0:int, 1:int}
     */
    private function range(Request $request, int $size): array
    {
        $header = (string) $request->headers->get('Range', '');

        if (! preg_match('/^bytes=(\d*)-(\d*)$/', $header, $m)) {
            return [0, $size - 1];
        }

        $start = $m[1] === '' ? null : (int) $m[1];
        $end = $m[2] === '' ? null : (int) $m[2];

        // `bytes=-500` تعني آخر خمسمئة بايت لا أوّلها
        if ($start === null) {
            $start = max(0, $size - (int) $end);
            $end = $size - 1;
        }

        $end ??= $size - 1;

        $start = max(0, min($start, $size - 1));
        $end = max($start, min($end, $size - 1));

        return [$start, $end];
    }
}
