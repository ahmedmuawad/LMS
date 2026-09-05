<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Core\Access\Ability;
use App\Modules\Content\Actions\StoreMedia;
use App\Modules\Lms\Models\Lesson;
use App\Modules\Lms\Models\LessonAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * إدارة مرفقات درس — رفعها وضبط إتاحتها وحذفها.
 *
 * شاشة مستقلّة لا حقلٌ في نموذج الدرس: المرفق له إعداداته الخاصة
 * (يُنزَّل؟ يُوسَم؟) وسجلّ فتحاته، وحشرُ ذلك في نموذج الدرس يجعل
 * النموذج صفحتين لا يُقرأ أيّهما.
 */
final class LessonAttachmentController
{
    /** ما يُقبل مرفقاً: قائمة مغلقة أضيق من قائمة الوسائط العامة */
    private const ACCEPTED = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function index(Request $request, int $lessonId): View
    {
        $this->authorise($request);

        $lesson = Lesson::findOrFail($lessonId);

        return view('admin.lesson-attachments', [
            'lesson' => $lesson,
            'attachments' => LessonAttachment::where('lesson_id', $lesson->getKey())
                ->with(['media', 'views'])
                ->orderBy('position')->orderBy('id')
                ->get(),
        ]);
    }

    public function store(Request $request, int $lessonId, StoreMedia $store): RedirectResponse
    {
        $this->authorise($request);

        $lesson = Lesson::findOrFail($lessonId);

        $input = $request->validate([
            'file' => ['required', 'file', 'max:51200'], // ٥٠ ميجا
            'title' => ['nullable', 'string', 'max:150'],
            'is_downloadable' => ['nullable', 'boolean'],
            'watermark' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('file');

        /*
         | النوع يُفحص هنا أضيقَ من فحص مكتبة الوسائط.
         |
         | المكتبة تقبل الصور والفيديو، وهي لا تصلح مرفقَ قراءة —
         | ورفعُ صورةٍ هنا يعطي الطالب زرّ «عرض» يفتح صفحةً فارغة.
         */
        if (! in_array((string) $file->getMimeType(), self::ACCEPTED, true)) {
            return back()->withErrors(['file' => __('المسموح: PDF أو Word فقط.')]);
        }

        try {
            $media = $store->handle($file, $request->user(), 'attachments');
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        LessonAttachment::create([
            'lesson_id' => $lesson->getKey(),
            'media_id' => $media->getKey(),
            'title' => $input['title'] ?? null,
            'is_downloadable' => $request->boolean('is_downloadable'),
            'watermark' => $request->boolean('watermark', true),
            'position' => (int) LessonAttachment::where('lesson_id', $lesson->getKey())->max('position') + 1,
        ]);

        return back()->with('status', __('أُضيف المرفق.'));
    }

    public function update(Request $request, int $lessonId, int $id): RedirectResponse
    {
        $this->authorise($request);

        $attachment = LessonAttachment::where('lesson_id', $lessonId)->findOrFail($id);

        $attachment->update([
            'title' => $request->validate(['title' => ['nullable', 'string', 'max:150']])['title'] ?? null,
            'is_downloadable' => $request->boolean('is_downloadable'),
            'watermark' => $request->boolean('watermark'),
        ]);

        return back()->with('status', __('حُدّث المرفق.'));
    }

    public function destroy(Request $request, int $lessonId, int $id): RedirectResponse
    {
        $this->authorise($request);

        // الملفّ في المكتبة يبقى: قد يشترك فيه أكثر من درس
        LessonAttachment::where('lesson_id', $lessonId)->findOrFail($id)->delete();

        return back()->with('status', __('حُذف المرفق.'));
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::LESSONS_MANAGE), 403);
    }
}
