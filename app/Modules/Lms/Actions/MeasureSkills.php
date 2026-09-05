<?php

declare(strict_types=1);

namespace App\Modules\Lms\Actions;

use App\Models\User;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\Skill;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * يقيس إتقان المهارات من إجابات الطالب.
 *
 * ## يُحسب ولا يُخزَّن
 *
 * كل إجابةٍ مسجّلة في `quiz_answers` بصوابها، وكل سؤالٍ موسومٌ
 * بمهاراته. فالإتقان استعلامٌ على ما هو مكتوب — وعدّادٌ ثانٍ
 * يُحدَّث مع كل إجابة يفترق عن مصدره حين يُصحّح المدرّس ورقةً
 * يدوياً أو يُعاد تقدير سؤال.
 *
 * ## وما لم يُقَس لا يُعرَض صفراً
 *
 * مهارةٌ لم يُسأل عنها الطالب ليست «ضعيفاً فيها» — هي مجهولة.
 * وعرضُها صفراً يجعل لوحته مليئةً بحُمرةٍ لم يستحقّها، فتُهمَل
 * اللوحة كلّها.
 */
final class MeasureSkills
{
    /**
     * إتقان الطالب لكل مهارة سُئل عنها.
     *
     * @return Collection<int, array{skill:Skill, asked:int, right:int, percent:int, mastered:bool}>
     */
    public function forStudent(User $user, ?Course $course = null): Collection
    {
        $rows = DB::table('quiz_answers')
            ->join('quiz_attempts', 'quiz_attempts.id', '=', 'quiz_answers.attempt_id')
            ->join('enrollments', 'enrollments.id', '=', 'quiz_attempts.enrollment_id')
            ->join('question_skill', 'question_skill.question_id', '=', 'quiz_answers.question_id')
            ->where('enrollments.user_id', $user->getKey())
            ->whereNotNull('quiz_answers.is_correct')
            ->when($course !== null, fn ($q) => $q->where('enrollments.course_id', $course->getKey()))
            ->groupBy('question_skill.skill_id')
            ->selectRaw('question_skill.skill_id, count(*) as asked, sum(case when quiz_answers.is_correct = 1 then 1 else 0 end) as right_count')
            ->get();

        return $this->shape($rows);
    }

    /**
     * فجوات الصفّ في كورس — ما يحتاج إعادة شرحٍ للجميع لا لفرد.
     *
     * @return Collection<int, array{skill:Skill, asked:int, right:int, percent:int, mastered:bool}>
     */
    public function forCourse(Course $course): Collection
    {
        $rows = DB::table('quiz_answers')
            ->join('quiz_attempts', 'quiz_attempts.id', '=', 'quiz_answers.attempt_id')
            ->join('enrollments', 'enrollments.id', '=', 'quiz_attempts.enrollment_id')
            ->join('question_skill', 'question_skill.question_id', '=', 'quiz_answers.question_id')
            ->where('enrollments.course_id', $course->getKey())
            ->whereNotNull('quiz_answers.is_correct')
            ->groupBy('question_skill.skill_id')
            ->selectRaw('question_skill.skill_id, count(*) as asked, sum(case when quiz_answers.is_correct = 1 then 1 else 0 end) as right_count')
            ->get();

        return $this->shape($rows);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function shape(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        $skills = Skill::whereIn('id', $rows->pluck('skill_id'))->get()->keyBy('id');

        return $rows
            ->map(function (object $row) use ($skills): ?array {
                $skill = $skills->get($row->skill_id);

                if ($skill === null) {
                    return null;
                }

                $asked = (int) $row->asked;
                $right = (int) $row->right_count;
                $percent = $asked > 0 ? (int) round($right / $asked * 100) : 0;

                return [
                    'skill' => $skill,
                    'asked' => $asked,
                    'right' => $right,
                    'percent' => $percent,
                    'mastered' => $percent >= (int) $skill->mastery_percent,
                ];
            })
            ->filter()
            // الأضعف أوّلاً: اللوحة تُقرأ لتُعرف الفجوة لا لتُمدَح القوّة
            ->sortBy('percent')
            ->values();
    }
}
