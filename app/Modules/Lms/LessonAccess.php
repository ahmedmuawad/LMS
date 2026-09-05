<?php

declare(strict_types=1);

namespace App\Modules\Lms;

use App\Core\Access\Ability;
use App\Models\User;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\Lesson;

/**
 * هل يفتح هذا المستخدم هذا الدرس؟
 *
 * ## لماذا موضعٌ واحد
 *
 * السؤال يتكرّر عند كل ما يتعلّق بالدرس: مرفقاته، وحزمة SCORM
 * فيه، ومحتوى H5P، ونقاط الفيديو. وكلّها نقاطٌ تُنادى مباشرةً
 * بمعرّفٍ في المسار، فلا يكفي أن يكون الدرس محروساً في صفحته.
 *
 * ولو نُسخ الفحص في كل واحدة لاختلف: يُشدَّد في المرفقات ويُنسى في
 * الحزمة، فيقرأ من لم يدفع من الباب الذي نُسي.
 */
final class LessonAccess
{
    /**
     * المدرّس يفتح ما يُدير، والطالب يفتح ما سجّل فيه وما زال نافذاً.
     *
     * ودرسٌ غير مرتبطٍ بكورس لا يفتحه طالب: لا سبيل إلى معرفة من
     * يملكه، والافتراض في المجهول المنع.
     */
    public function grants(?User $user, ?Lesson $lesson): bool
    {
        if ($user === null || $lesson === null) {
            return false;
        }

        if ($user->allows(Ability::LESSONS_MANAGE)) {
            return true;
        }

        // الدرس يصل الكورس عبر عناصر المنهج، وقد يكون في أكثر من كورس
        $courseIds = $lesson->items()->pluck('course_id')->filter()->unique();

        if ($courseIds->isEmpty()) {
            return false;
        }

        return Enrollment::where('user_id', $user->getKey())
            ->whereIn('course_id', $courseIds)
            ->get()
            ->contains(fn (Enrollment $e): bool => $e->hasAccess());
    }
}
