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

        $this->tellGuardians($session, $statuses);

        return $summary;
    }

    /**
     * إخبار ولي الأمر بغياب ابنه أو تأخّره.
     *
     * هذا هو الفارق الذي يشتريه صاحب السنتر فعلاً: الرسالة تصل
     * وقت الحصة لا في نهاية الشهر، فيسأل الأب ابنه اليوم لا بعد شهر.
     *
     * @param  array<int, string>  $statuses
     */
    private function tellGuardians(Session $session, array $statuses): void
    {
        $notable = array_filter($statuses, fn (string $status): bool => in_array($status, ['absent', 'late'], true));

        if ($notable === []) {
            return;
        }

        $students = Student::with(['guardians.user'])->whereIn('id', array_keys($notable))->get()->keyBy('id');
        $groupName = (string) ($session->group?->name ?? '');
        // التاريخ عمود والوقت عمود آخر: الجمع هنا لا في القالب
        $sessionAt = trim(($session->date?->translatedFormat('l j F') ?? '').' — '.$session->timeLabel());

        foreach ($notable as $studentId => $status) {
            $student = $students->get($studentId);

            if ($student === null) {
                continue;
            }

            $event = $status === 'absent' ? 'center.absence' : 'center.late';

            $recipients = $student->guardians
                ->filter(fn ($guardian): bool => $guardian->wants($event) && $guardian->user !== null)
                ->map(fn ($guardian) => $guardian->user);

            if ($recipients->isEmpty()) {
                continue;
            }

            notify($event, $recipients->values(), [
                'student_name' => (string) $student->name,
                'group_name' => $groupName,
                'session_at' => $sessionAt,
                'late_minutes' => (string) (Attendance::where('session_id', $session->getKey())
                    ->where('student_id', $studentId)->value('late_minutes') ?? 0),
                'absence_rate' => (string) round((float) ($this->rateFor((int) $studentId, (int) $session->group_id) ?? 0), 1),
                'url' => url('/guardian'),
            ]);
        }
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
