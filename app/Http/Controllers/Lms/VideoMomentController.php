<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Core\Access\Ability;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\Lesson;
use App\Modules\Lms\Models\Question;
use App\Modules\Lms\Models\VideoMoment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * نقاط التفاعل داخل الفيديو — إدارتها والإجابة عليها.
 *
 * الطالب يشاهد عشرين دقيقة ثم ينتقل، ولا يعرف المدرّس أفَهِم أم
 * مرّت الصورة أمامه. والسؤال في منتصف الفيديو يكشف ذلك في ثانيته
 * لا في امتحان آخر الوحدة.
 */
final class VideoMomentController
{
    public function index(Request $request, int $lessonId): View
    {
        $this->authorise($request);

        $lesson = Lesson::findOrFail($lessonId);

        return view('admin.video-moments', [
            'lesson' => $lesson,
            'moments' => VideoMoment::where('lesson_id', $lesson->getKey())
                ->with(['question', 'responses'])
                ->orderBy('at_second')->get(),
            'questions' => Question::query()->latest('id')->limit(200)->get(),
        ]);
    }

    public function store(Request $request, int $lessonId): RedirectResponse
    {
        $this->authorise($request);

        $lesson = Lesson::findOrFail($lessonId);

        $input = $request->validate([
            'at_second' => ['required', 'integer', 'min:0', 'max:86400'],
            'kind' => ['required', 'string', 'in:'.implode(',', array_keys(VideoMoment::KINDS))],
            'question_id' => ['nullable', 'integer', 'exists:questions,id'],
            'body' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'url', 'max:2000'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        /*
         | السؤال شرطٌ لنقطة السؤال.
         |
         | نقطةٌ من نوع «سؤال» بلا سؤال توقف الفيديو ولا تعرض شيئاً،
         | فيقف الطالب أمام شاشةٍ لا مخرج منها.
         */
        if ($input['kind'] === 'question' && blank($input['question_id'])) {
            return back()->withErrors(['question_id' => __('اختر سؤالاً من البنك.')])->withInput();
        }

        if ($input['kind'] === 'note' && blank($input['body'])) {
            return back()->withErrors(['body' => __('اكتب نصّ الملاحظة.')])->withInput();
        }

        VideoMoment::create([
            'lesson_id' => $lesson->getKey(),
            'at_second' => $input['at_second'],
            'kind' => $input['kind'],
            'question_id' => $input['kind'] === 'question' ? $input['question_id'] : null,
            'body' => $input['body'] ?? null,
            'url' => $input['url'] ?? null,
            'is_required' => $request->boolean('is_required'),
        ]);

        return back()->with('status', __('أُضيفت نقطة التفاعل.'));
    }

    public function destroy(Request $request, int $lessonId, int $id): RedirectResponse
    {
        $this->authorise($request);

        VideoMoment::where('lesson_id', $lessonId)->findOrFail($id)->delete();

        return back()->with('status', __('حُذفت نقطة التفاعل.'));
    }

    /**
     * إجابة الطالب — تُقيَّم فوراً ويُعاد الصواب.
     *
     * الفائدة كلّها في أن يعرف الآن: مراجعةٌ بعد أسبوع لا تُصحّح
     * فهماً بُني خطأً في تلك اللحظة.
     */
    public function respond(Request $request, int $momentId): JsonResponse
    {
        $moment = VideoMoment::with('question')->findOrFail($momentId);

        /*
         | التسجيل شرطٌ قبل أن نردّ بشيء.
         |
         | الردّ يحمل الإجابة الصحيحة، فبلا هذا الفحص يستطيع أي
         | مستخدمٍ أن يمرّ على المعرّفات واحداً واحداً فيستخرج إجابات
         | كل الكورسات — بما فيها ما لم يشترِه.
         */
        $this->assertEnrolled($request, $moment);

        $input = $request->validate(['answer' => ['required', 'string', 'max:2000']]);

        $correct = $moment->question?->correct;
        $isCorrect = $correct === null ? null : $this->matches($input['answer'], $correct);

        $request->user()->momentResponses()->updateOrCreate(
            ['moment_id' => $moment->getKey()],
            ['answer' => $input['answer'], 'is_correct' => $isCorrect],
        );

        return response()->json([
            'correct' => $isCorrect,
            'expected' => $isCorrect === false ? $correct : null,
            'explanation' => $moment->question?->explanation,
        ]);
    }

    /**
     * هل هذا المستخدم مسجَّل في كورسٍ يضمّ درس هذه النقطة؟
     *
     * ومن يملك إدارة الدروس يمرّ: من يضع السؤال يجرّبه.
     */
    private function assertEnrolled(Request $request, VideoMoment $moment): void
    {
        $user = $request->user();

        abort_if($user === null, 403);

        if ($user->allows(Ability::LESSONS_MANAGE)) {
            return;
        }

        $courseIds = $moment->lesson?->items()->pluck('course_id')->filter()->unique() ?? collect();

        abort_if($courseIds->isEmpty(), 403);

        $enrolled = Enrollment::where('user_id', $user->getKey())
            ->whereIn('course_id', $courseIds)
            ->get()
            ->contains(fn (Enrollment $e): bool => $e->hasAccess());

        abort_unless($enrolled, 403, __('سجّل في الكورس أولاً.'));
    }

    private function matches(string $given, mixed $correct): bool
    {
        $normalise = fn (string $v): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $v)));

        if (is_array($correct)) {
            return in_array($normalise($given), array_map(
                fn ($c): string => $normalise((string) $c),
                $correct,
            ), true);
        }

        return $normalise($given) === $normalise((string) $correct);
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::LESSONS_MANAGE), 403);
    }
}
