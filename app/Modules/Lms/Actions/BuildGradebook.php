<?php

declare(strict_types=1);

namespace App\Modules\Lms\Actions;

use App\Models\User;
use App\Modules\Lms\Models\Assignment;
use App\Modules\Lms\Models\AssignmentSubmission;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseItem;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\Quiz;
use App\Modules\Lms\Models\QuizAttempt;
use Illuminate\Support\Collection;

/**
 * دفتر الدرجات: كل طالب في صفّ، وكل مُقيَّم في عمود.
 *
 * ## لماذا يُبنى ولا يُخزَّن
 *
 * الدرجات موجودة كلّها: في `quiz_attempts` وفي
 * `assignment_submissions`. وجدولٌ ثالث يجمعها يعني ثلاثة مصادر
 * للدرجة الواحدة — يُصحّح المدرّس ورقةً فتتغيّر في مكانين ولا
 * تتغيّر في الثالث.
 *
 * فيُبنى عند الطلب. والحجم يحتمله: كورسٌ فيه ألف طالب وعشرون
 * مُقيَّماً استعلامان لا ألفان.
 *
 * ## وأفضل محاولة لا آخرها
 *
 * الطالب يعيد الاختبار ليتحسّن؛ فأخذُ الأخيرة يعاقب من جرّب ثم
 * تعثّر. وهي القاعدة نفسها التي يعمل بها إكمال الكورس.
 */
final class BuildGradebook
{
    /**
     * @return array{
     *     columns: Collection<int, array{key:string, title:string, max:float, type:string}>,
     *     rows: Collection<int, array{student:?User, cells:array<string, ?float>, total:float, max:float, percent:float}>,
     *     max: float
     * }
     */
    public function handle(Course $course): array
    {
        $columns = $this->columns($course);
        $max = (float) $columns->sum('max');

        $enrollments = Enrollment::where('course_id', $course->getKey())
            ->with('user')
            ->orderBy('id')
            ->get();

        $quizScores = $this->quizScores($enrollments->pluck('id'));
        $assignmentScores = $this->assignmentScores($enrollments->pluck('id'));

        $rows = $enrollments->map(function (Enrollment $enrollment) use ($columns, $quizScores, $assignmentScores, $max): array {
            $cells = [];
            $total = 0.0;

            foreach ($columns as $column) {
                $source = $column['type'] === 'quiz' ? $quizScores : $assignmentScores;
                $score = $source[$enrollment->id][$column['id']] ?? null;

                $cells[$column['key']] = $score;
                $total += (float) ($score ?? 0);
            }

            return [
                'student' => $enrollment->user,
                'cells' => $cells,
                'total' => round($total, 2),
                'max' => $max,
                'percent' => $max > 0 ? round($total / $max * 100, 1) : 0.0,
            ];
        });

        return ['columns' => $columns, 'rows' => $rows, 'max' => $max];
    }

    /**
     * الدفتر لطالبٍ واحد — لشاشته هو.
     *
     * الطالب يرى صفّه وحده: درجاته في كل مُقيَّم ومجموعه ونسبته.
     * ولا يرى درجات زملائه — الترتيب بين الطلبة في لوحة الصدارة
     * بالنقاط، وهي مسابقةٌ اختيارية؛ أمّا الدرجات فبينه وبين مدرّسه.
     *
     * @return array{
     *     columns: Collection<int, array<string, mixed>>,
     *     cells: array<string, ?float>, total: float, max: float, percent: float
     * }
     */
    public function forStudent(Enrollment $enrollment): array
    {
        $course = $enrollment->course;

        if ($course === null) {
            return ['columns' => collect(), 'cells' => [], 'total' => 0.0, 'max' => 0.0, 'percent' => 0.0];
        }

        $columns = $this->columns($course);
        $max = (float) $columns->sum('max');

        $ids = collect([$enrollment->getKey()]);
        $quizScores = $this->quizScores($ids);
        $assignmentScores = $this->assignmentScores($ids);

        $cells = [];
        $total = 0.0;

        foreach ($columns as $column) {
            $source = $column['type'] === 'quiz' ? $quizScores : $assignmentScores;
            $score = $source[$enrollment->getKey()][$column['id']] ?? null;

            $cells[$column['key']] = $score;
            $total += (float) ($score ?? 0);
        }

        return [
            'columns' => $columns,
            'cells' => $cells,
            'total' => round($total, 2),
            'max' => $max,
            'percent' => $max > 0 ? round($total / $max * 100, 1) : 0.0,
        ];
    }

    /**
     * الأعمدة: اختبارات الكورس وواجباته بترتيب المنهج.
     *
     * @return Collection<int, array{key:string, id:int, title:string, max:float, type:string}>
     */
    private function columns(Course $course): Collection
    {
        $items = CourseItem::where('course_id', $course->getKey())
            ->whereIn('itemable_type', [Quiz::class, Assignment::class])
            ->with('itemable')
            ->orderBy('position')
            ->get()
            ->filter(fn (CourseItem $item): bool => $item->itemable !== null);

        /*
         | نهاية درجة الاختبار من المحاولات لا من مجموع أسئلته.
         |
         | الاختبار «الديناميكي» لا أسئلة ثابتة له: يُسحب من البنك
         | عند كل محاولة، فمجموعُ أسئلته صفر. والمحاولة تحفظ
         | `max_score` وقتها — وهي النهاية الحقيقية التي امتُحن
         | عليها الطلبة.
         */
        $quizMax = QuizAttempt::whereIn('quiz_id', $items
            ->filter(fn (CourseItem $i): bool => $i->itemable instanceof Quiz)
            ->map(fn (CourseItem $i) => $i->itemable->getKey()))
            ->selectRaw('quiz_id, max(max_score) as top')
            ->groupBy('quiz_id')
            ->pluck('top', 'quiz_id');

        return $items
            ->map(function (CourseItem $item) use ($quizMax): array {
                $isQuiz = $item->itemable instanceof Quiz;
                $id = (int) $item->itemable->getKey();

                $max = $isQuiz
                    ? (float) ($quizMax[$id] ?? $item->itemable->questions()->sum('marks'))
                    : (float) $item->itemable->max_marks;

                return [
                    'key' => ($isQuiz ? 'q' : 'a').$id,
                    'id' => $id,
                    'title' => (string) $item->itemable->title,
                    'max' => $max,
                    'type' => $isQuiz ? 'quiz' : 'assignment',
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, int>  $enrollmentIds
     * @return array<int, array<int, float>>
     */
    private function quizScores(Collection $enrollmentIds): array
    {
        $out = [];

        QuizAttempt::whereIn('enrollment_id', $enrollmentIds)
            ->whereIn('status', ['submitted', 'graded'])
            ->get(['enrollment_id', 'quiz_id', 'score'])
            ->each(function (QuizAttempt $attempt) use (&$out): void {
                $best = $out[$attempt->enrollment_id][$attempt->quiz_id] ?? null;

                if ($best === null || (float) $attempt->score > $best) {
                    $out[$attempt->enrollment_id][$attempt->quiz_id] = (float) $attempt->score;
                }
            });

        return $out;
    }

    /**
     * @param  Collection<int, int>  $enrollmentIds
     * @return array<int, array<int, float>>
     */
    private function assignmentScores(Collection $enrollmentIds): array
    {
        $out = [];

        AssignmentSubmission::whereIn('enrollment_id', $enrollmentIds)
            ->whereNotNull('marks')
            ->get(['enrollment_id', 'assignment_id', 'marks'])
            ->each(function (AssignmentSubmission $submission) use (&$out): void {
                $best = $out[$submission->enrollment_id][$submission->assignment_id] ?? null;

                if ($best === null || (float) $submission->marks > $best) {
                    $out[$submission->enrollment_id][$submission->assignment_id] = (float) $submission->marks;
                }
            });

        return $out;
    }
}
