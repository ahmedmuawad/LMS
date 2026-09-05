<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Core\Access\Ability;
use App\Models\User;
use App\Modules\Lms\Models\AttachmentView;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\LessonAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تقديم مرفقات الدروس — محروسةً لا بروابط عامة.
 *
 * كانت المرفقات روابط تخزين عامة: رابطٌ واحد يُنسخ إلى مجموعة
 * واتساب فيقرؤه مئةٌ لم يدفعوا. وهنا لا يمرّ بايتٌ واحد قبل أن
 * يُتحقّق من أن هذا المستخدم مسجَّلٌ في هذا الكورس فعلاً.
 *
 * ## ما تَعِد به هذه الحماية وما لا تَعِد
 *
 * لا يمنع متصفّحٌ تصوير الشاشة منعاً تامّاً، ومن يزعم ذلك يكذب.
 * فالوعد هنا ثلاثة أشياء تتحقّق فعلاً:
 *
 *   ١) لا يفتح الملفّ إلا مشترك — فالرابط المسرَّب لا يفيد ناسخه.
 *   ٢) كل صفحة تحمل اسم قارئها ورقمه — فالصورة المسرَّبة تدلّ عليه.
 *   ٣) كل فتحة تُسجَّل — فمن سرّب يُعرَف بعد التسريب لا قبله.
 */
final class AttachmentController
{
    public function show(Request $request, int $id): View
    {
        $attachment = $this->authorised($request, $id);

        $this->log($request, $attachment, 'view');

        return view('lms.attachment', [
            'attachment' => $attachment,
            'lesson' => $attachment->lesson,
            'stamp' => $this->stamp($request->user()),
        ]);
    }

    /**
     * البايتات نفسها — تُبثّ ولا تُعاد توجيهاً إلى رابط التخزين.
     *
     * إعادة التوجيه تكشف الرابط العام في شريط العنوان وفي سجلّ
     * الشبكة، فيصير كل ما سبق زينةً على باب مفتوح.
     */
    public function stream(Request $request, int $id): StreamedResponse
    {
        $attachment = $this->authorised($request, $id);
        $media = $attachment->media;

        abort_if($media === null, 404);

        $disk = Storage::disk((string) $media->disk);

        abort_unless($disk->exists((string) $media->path), 404);

        $download = $request->boolean('download');

        // التنزيل يُمنع هنا لا في الواجهة فقط: زرٌّ مخفيّ ليس منعاً
        abort_if($download && ! $attachment->is_downloadable, 403, __('هذا الملف للعرض فقط.'));

        $this->log($request, $attachment, $download ? 'download' : 'view');

        return $disk->response(
            (string) $media->path,
            $attachment->name(),
            [
                'Content-Type' => (string) $media->mime,
                /*
                 | لا تخزين وسيط ولا في المتصفّح: النسخة المخبَّأة
                 | تبقى مقروءةً بعد انتهاء اشتراك صاحبها.
                 */
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $download ? 'attachment' : 'inline',
        );
    }

    /**
     * الوصول: المرفق يتبع درساً يتبع كورساً — والتسجيل هو الإذن.
     *
     * ويمرّ صاحب المنصة ومدرّس الكورس بلا تسجيل: من يرفع الملفّ
     * يجب أن يراه.
     */
    private function authorised(Request $request, int $id): LessonAttachment
    {
        $attachment = LessonAttachment::with(['media', 'lesson'])->findOrFail($id);
        $user = $request->user();

        abort_if($user === null, 403);

        if ($user->allows(Ability::LESSONS_MANAGE)) {
            return $attachment;
        }

        // الدرس يصل الكورس عبر عناصر المنهج، وقد يكون في أكثر من كورس
        $courseIds = $attachment->lesson?->items()->pluck('course_id')->filter()->unique() ?? collect();

        abort_if($courseIds->isEmpty(), 403, __('هذا المرفق غير مرتبط بكورس بعد.'));

        $enrolled = Enrollment::where('user_id', $user->getKey())
            ->whereIn('course_id', $courseIds)
            ->get()
            ->contains(fn (Enrollment $e): bool => $e->hasAccess());

        abort_unless($enrolled, 403, __('سجّل في الكورس أولاً لتفتح مرفقاته.'));

        return $attachment;
    }

    /**
     * وسم القارئ: اسمه، وآخر أربعة من هاتفه، ورقمه، ووقت الفتح.
     *
     * الاسم وحده يتكرّر بين الطلبة؛ والرقم وحده لا يُقرأ في صورة.
     * فاجتماعهما يجعل الوسم دالّاً ومقروءاً معاً.
     */
    private function stamp(User $user): string
    {
        $phone = (string) ($user->phone ?? '');
        $tail = $phone === '' ? '' : ' ⋅ '.mb_substr($phone, -4);

        return trim((string) $user->name).$tail.' ⋅ #'.$user->getKey()
            .' ⋅ '.now()->translatedFormat('j/n/Y g:i a');
    }

    private function log(Request $request, LessonAttachment $attachment, string $action): void
    {
        AttachmentView::create([
            'attachment_id' => $attachment->getKey(),
            'user_id' => $request->user()->getKey(),
            'action' => $action,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
