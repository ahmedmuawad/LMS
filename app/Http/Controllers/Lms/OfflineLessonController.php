<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Modules\Lms\LessonAccess;
use App\Modules\Lms\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ملفّ الدرس بعنوانٍ ثابت — ليُحفَظ في مخزن المتصفّح.
 *
 * ## لماذا لا يُحفَظ الرابط الموقَّع
 *
 * رابط المشاهدة يُوقَّع لكل طلب وينتهي بساعات، وحفظُه في المخزن
 * يجعل النسخة المحفوظة تُفتح اليوم وتُرفَض غداً — والطالب لا يفهم
 * لماذا «اختفى» ما حفظه.
 *
 * فهذا عنوانٌ ثابت لا ينتهي، وحراستُه في الخادم لا في الرابط:
 * التسجيل يُفحص عند كل طلب، والمخزن نفسه محصورٌ بنطاق المشترك
 * فلا يُقرأ من موقعٍ آخر.
 *
 * ## وهو غير التنزيل
 *
 * يُقدَّم `inline` ولا يُقترَح اسم ملفّ للحفظ: المقصود مشاهدةٌ بلا
 * اتصال داخل المنصة، لا نسخةٌ تُوزَّع.
 */
final class OfflineLessonController
{
    public function __invoke(Request $request, int $lessonId, LessonAccess $access): StreamedResponse
    {
        $lesson = Lesson::find($lessonId);

        abort_unless($access->grants($request->user(), $lesson), 403);

        // الميزة في الباقة، والإتاحة بيد المدرّس — وكلاهما يُفحص هنا
        abort_unless(tenant()?->allows('offline_download') ?? false, 402);
        abort_unless((bool) $lesson->is_offline, 404);

        /*
         | الملفّ المرفوع وحده.
         |
         | Bunny وYouTube وVimeo تُقدَّم من خوادمها بمشغّلاتها، ولا
         | نملك بايتاتها لنحفظها — ولو حفظنا رابطها لحُفظت صفحةُ
         | مشغّلٍ لا تعمل بلا اتصال.
         */
        abort_unless($lesson->video_provider === 'file' && filled($lesson->video_id), 404);

        $disk = Storage::disk(config('filesystems.default'));
        $path = ltrim((string) $lesson->video_id, '/');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Content-Type' => $disk->mimeType($path) ?: 'video/mp4',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
