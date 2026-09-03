<?php

declare(strict_types=1);

namespace App\Core\Access;

use App\Models\User;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\Instructor;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * حصر النطاق: أي صفوف يرى صاحب الصلاحية.
 *
 * الصلاحية تفتح الشاشة، وهذه تحدّد ما فيها. الفصل بينهما مقصود:
 * «المدرّس يدير كورسات» صحيحة، و«المدرّس يدير كورسات غيره» ليست —
 * والفرق بينهما جملة `where` واحدة إن وُجدت، وكارثة إن نُسيت.
 */
final class Scope
{
    public function __construct(private readonly Roles $roles) {}

    /** معرّف سجلّ المدرّس التابع لهذا المستخدم — إن كان مدرّساً. */
    public function instructorIdFor(?User $user): ?int
    {
        if ($user === null) {
            return null;
        }

        $id = Instructor::where('user_id', $user->getKey())->value('id');

        return $id === null ? null : (int) $id;
    }

    /** @return list<int> معرّفات الكورسات التي يملكها هذا المستخدم */
    public function courseIdsFor(?User $user): array
    {
        $instructorId = $this->instructorIdFor($user);

        if ($instructorId === null) {
            return [];
        }

        return Course::where('instructor_id', $instructorId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * يحصر الاستعلام بعمود يشير إلى الكورس.
     *
     * القائمة الفارغة تُترجَم إلى `whereRaw('1 = 0')` لا إلى تخطّي
     * الشرط: مدرّس بلا كورسات يجب أن يرى لا شيء، لا أن يرى الكل.
     */
    public function byCourse(Builder $query, ?User $user, string $column = 'course_id'): Builder
    {
        if (! $this->roles->isScoped($user)) {
            return $query;
        }

        $ids = $this->courseIdsFor($user);

        return $ids === [] ? $query->whereRaw('1 = 0') : $query->whereIn($column, $ids);
    }

    public function byInstructor(Builder $query, ?User $user, string $column = 'instructor_id'): Builder
    {
        if (! $this->roles->isScoped($user)) {
            return $query;
        }

        $id = $this->instructorIdFor($user);

        return $id === null ? $query->whereRaw('1 = 0') : $query->where($column, $id);
    }

    /** يحصر بما أنشأه المستخدم نفسه — لبنوك الدروس والأسئلة المشتركة. */
    public function byCreator(Builder $query, ?User $user, string $column = 'created_by'): Builder
    {
        if (! $this->roles->isScoped($user)) {
            return $query;
        }

        return $query->where($column, $user->getKey());
    }

    /** يحصر بالمستخدم نفسه — للأرباح والتحويلات. */
    public function byUser(Builder $query, ?User $user, string $column = 'user_id'): Builder
    {
        if (! $this->roles->isScoped($user)) {
            return $query;
        }

        return $query->where($column, $user?->getKey());
    }

    /**
     * يحصر بالكورس عبر علاقة — للجداول التي لا تحمل `course_id` بنفسها.
     *
     * محاولة الاختبار وتسليم الواجب يعرفان تسجيلاً، والتسجيل يعرف
     * الكورس؛ فبغير هذا كان المدرّس يقرأ أسماء طلاب غيره ويصحّح لهم.
     */
    public function byCourseVia(Builder $query, ?User $user, string $relation, string $column = 'course_id'): Builder
    {
        if (! $this->roles->isScoped($user)) {
            return $query;
        }

        $ids = $this->courseIdsFor($user);

        return $ids === []
            ? $query->whereRaw('1 = 0')
            : $query->whereHas($relation, fn (Builder $q) => $q->whereIn($column, $ids));
    }

    /** هل يملك هذا المستخدم هذا الكورس؟ */
    public function ownsCourse(?User $user, ?Course $course): bool
    {
        if ($course === null) {
            return false;
        }

        if (! $this->roles->isScoped($user)) {
            return true;
        }

        return $course->instructor_id !== null
            && (int) $course->instructor_id === $this->instructorIdFor($user);
    }

    /**
     * يُسقط الطلب إن لم يكن الكورس كورسه — 404 لا 403.
     *
     * 403 يقول «هذا موجود ولستَ صاحبه»، وهو بذاته تسريب: يكشف أن
     * الكورس رقم كذا قائم. 404 لا يقول شيئاً.
     */
    public function assertOwnsCourse(?User $user, ?Course $course): void
    {
        if (! $this->ownsCourse($user, $course)) {
            abort(404);
        }
    }
}
