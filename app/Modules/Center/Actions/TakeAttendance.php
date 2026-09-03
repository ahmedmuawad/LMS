<?php

declare(strict_types=1);

namespace App\Modules\Center\Actions;

use App\Models\User;
use App\Modules\Center\Models\Attendance;
use App\Modules\Center\Models\CenterEnrollment;
use App\Modules\Center\Models\Session;
use App\Modules\Center\Models\Student;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * وثيقة 16.5 — الحضور بأربع طرق.
 *
 * شاشة الحضور تعمل بأقل عدد نقرات وبإنترنت ضعيف: الافتراضي
 * «حاضر»، والموظف يعلّم الغائبين وحدهم — فالغالب حاضر.
 */
final class TakeAttendance
{
    /**
     * تعليم كشف كامل دفعة واحدة.
     *
     * @param  array<int, string>  $statuses  معرّف الطالب ← الحالة
     * @return array{present:int, absent:int, late:int, excused:int, online:int}
     */
    public function handle(Session $session, array $statuses, ?User $recorder = null, string $method = 'manual'): array
    {
        $students = CenterEnrollment::where('group_id', $session->group_id)
            ->active()
            ->pluck('student_id');

        $summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'online' => 0];

        DB::transaction(function () use ($session, $statuses, $students, $recorder, $method, &$summary): void {
            foreach ($students as $studentId) {
                // من لم يُعلَّم فهو حاضر: الغالب حضور، والاستثناء يُعلَّم
                $status = $statuses[$studentId] ?? 'present';

                if (! array_key_exists($status, $summary)) {
                    $status = 'present';
                }

                Attendance::updateOrCreate(
                    ['session_id' => $session->getKey(), 'student_id' => $studentId],
                    [
                        'status' => $status,
                        'method' => $method,
                        'recorded_by' => $recorder?->getKey(),
                        'recorded_at' => now(),
                    ],
                );

                $summary[$status]++;
            }

            $session->forceFill([
                'attendance_taken_at' => now(),
                'status' => $session->status === 'scheduled' ? 'done' : $session->status,
            ])->save();
        });

        return $summary;
    }

    /**
     * تعليم طالب واحد — بالكود أو بمسح الكارنيه.
     * تُستخدم من شاشة الجهاز اللوحي عند الباب.
     */
    public function mark(Session $session, string $studentCode, string $method = 'code', ?User $recorder = null): Attendance
    {
        $student = Student::where('code', mb_strtoupper(trim($studentCode)))->first();

        if ($student === null) {
            throw new RuntimeException(__('لا طالب بهذا الكود.'));
        }

        $enrolled = CenterEnrollment::where('group_id', $session->group_id)
            ->where('student_id', $student->getKey())
            ->active()
            ->exists();

        if (! $enrolled) {
            throw new RuntimeException(__(':name ليس مسجّلاً في هذه المجموعة.', ['name' => $student->name()]));
        }

        // المتأخر يُسجَّل متأخراً لا حاضراً: الفرق يهمّ ولي الأمر
        $lateBy = $this->minutesLate($session);

        return Attendance::updateOrCreate(
            ['session_id' => $session->getKey(), 'student_id' => $student->getKey()],
            [
                'status' => $lateBy > (int) setting('center.late_threshold_minutes', 10) ? 'late' : 'present',
                'method' => $method,
                'minutes_late' => $lateBy,
                'recorded_by' => $recorder?->getKey(),
                'recorded_at' => now(),
            ],
        );
    }

    private function minutesLate(Session $session): int
    {
        $start = $session->date->copy()->setTimeFromTimeString((string) $session->starts_at);

        return $start->isPast() ? (int) $start->diffInMinutes(now()) : 0;
    }

    /** نسبة حضور طالب في مجموعة — تدخل التقرير الشهري. */
    public function rateFor(int $studentId, int $groupId): ?float
    {
        $rows = Attendance::whereIn(
            'session_id',
            Session::where('group_id', $groupId)->whereNotNull('attendance_taken_at')->pluck('id'),
        )->where('student_id', $studentId)->get();

        if ($rows->isEmpty()) {
            return null;
        }

        // الغياب بعذر لا يُحتسب ضدّ الطالب ولا له
        $counted = $rows->reject(fn (Attendance $a): bool => $a->status === 'excused');

        if ($counted->isEmpty()) {
            return null;
        }

        return round($counted->filter(fn (Attendance $a): bool => $a->countsAsPresent())->count() / $counted->count() * 100, 1);
    }
}
