<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Models\User;
use App\Modules\Lms\Actions\BuildGradebook;
use App\Modules\Lms\Actions\MeasureSkills;
use App\Modules\Lms\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * درجات الطالب في كورساته.
 *
 * كانت الدرجة تُرى في ورقة الاختبار وحدها: يفتحها الطالب فيرى
 * محاولةً واحدة، ولا يعرف أين هو من الكورس كلّه. وهذه صورته
 * كاملةً — وهي ما يسأل عنه وليّ الأمر كذلك.
 */
final class MyGradesController
{
    public function __invoke(Request $request, BuildGradebook $builder, MeasureSkills $skills): View
    {
        $user = $request->user();

        abort_if(! $user instanceof User, 403);

        $courses = Enrollment::where('user_id', $user->getKey())
            ->with('course')
            ->get()
            ->filter(fn (Enrollment $e): bool => $e->course !== null)
            ->map(fn (Enrollment $e): array => [
                'enrollment' => $e,
                'course' => $e->course,
                ...$builder->forStudent($e),
            ])
            // كورسٌ بلا مُقيَّم لا درجة له، وعرضه بصفر يوهم بالرسوب
            ->filter(fn (array $row): bool => $row['columns']->isNotEmpty())
            ->values();

        return view('lms.student.grades', [
            'courses' => $courses,

            /*
             | المهارات تحت الدرجات لا في شاشةٍ ثالثة.
             |
             | الدرجة تقول «كم»، والمهارة تقول «أين» — والسؤالان
             | يُسألان معاً في اللحظة نفسها.
             */
            'skills' => $skills->forStudent($user),
        ]);
    }
}
