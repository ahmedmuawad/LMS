<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Core\Access\Ability;
use App\Modules\Lms\Models\Lesson;
use App\Modules\Lms\Models\LessonChapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * فصول الفيديو ونصّه المكتوب.
 *
 * ## في شاشةٍ واحدة
 *
 * الاثنان يخدمان الغرض نفسه: أن يجد الطالب موضعاً بعينه في محاضرةٍ
 * طويلة. والفصل يُوصله بالضغط، والنصّ يُوصله بالبحث. وفصلُهما في
 * شاشتين يجعل المدرّس يملأ إحداهما وينسى الأخرى.
 */
final class ChapterController
{
    public function index(Request $request, int $lessonId): View
    {
        $this->authorise($request);

        $lesson = Lesson::findOrFail($lessonId);

        return view('admin.chapters', [
            'lesson' => $lesson,
            'chapters' => LessonChapter::where('lesson_id', $lesson->getKey())
                ->orderBy('at_second')->get(),
            'transcript' => $this->transcriptText($lesson),
        ]);
    }

    public function store(Request $request, int $lessonId): RedirectResponse
    {
        $this->authorise($request);

        $lesson = Lesson::findOrFail($lessonId);

        $input = $request->validate([
            'at' => ['required', 'string', 'max:12'],
            'title' => ['required', 'string', 'max:180'],
        ]);

        $seconds = $this->seconds((string) $input['at']);

        if ($seconds === null) {
            return back()->withErrors(['at' => __('اكتب التوقيت هكذا: 5:30 أو 1:05:30 أو بالثواني.')]);
        }

        LessonChapter::updateOrCreate(
            ['lesson_id' => $lesson->getKey(), 'at_second' => $seconds],
            ['title' => $input['title']],
        );

        return back()->with('status', __('أُضيف الفصل.'));
    }

    public function destroy(Request $request, int $lessonId, int $id): RedirectResponse
    {
        $this->authorise($request);

        LessonChapter::where('lesson_id', $lessonId)->whereKey($id)->delete();

        return back()->with('status', __('حُذف الفصل.'));
    }

    /**
     * النصّ المكتوب — يُحفظ نصّاً لا مصفوفة مقاطع.
     *
     * والسطر الذي يبدأ بتوقيت يصير قابلاً للضغط عند الطالب. فمن
     * لديه نصٌّ من أداة تفريغ يلصقه كما هو، ومن يكتبه بنفسه يكتب
     * فقراتٍ عادية — وكلاهما يعمل.
     */
    public function transcript(Request $request, int $lessonId): RedirectResponse
    {
        $this->authorise($request);

        $lesson = Lesson::findOrFail($lessonId);

        $input = $request->validate([
            'transcript' => ['nullable', 'string', 'max:200000'],
        ]);

        $text = trim((string) ($input['transcript'] ?? ''));

        $lesson->forceFill([
            'transcript' => $text === '' ? null : ['ar' => $text],
        ])->save();

        return back()->with('status', $text === ''
            ? __('حُذف النصّ المكتوب.')
            : __('حُفظ النصّ المكتوب.'));
    }

    private function transcriptText(Lesson $lesson): string
    {
        $value = $lesson->transcript;

        if (is_array($value)) {
            return (string) ($value[app()->getLocale()] ?? $value['ar'] ?? reset($value) ?? '');
        }

        return (string) ($value ?? '');
    }

    /**
     * يقرأ `5:30` و`1:05:30` و`330`.
     *
     * المدرّس يكتب ما يراه في مشغّله، وإلزامُه بالثواني يجعله يحسب
     * في رأسه — ويُخطئ.
     */
    private function seconds(string $given): ?int
    {
        $given = trim($given);

        if (preg_match('/^\d+$/', $given)) {
            return (int) $given;
        }

        if (! preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{1,2})$/', $given, $m)) {
            return null;
        }

        return ((int) ($m[1] ?? 0)) * 3600 + ((int) $m[2]) * 60 + (int) $m[3];
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::LESSONS_MANAGE), 403);
    }
}
