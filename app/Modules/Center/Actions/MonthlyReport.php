<?php

declare(strict_types=1);

namespace App\Modules\Center\Actions;

use App\Core\Support\Money;
use App\Modules\Center\Models\Attendance;
use App\Modules\Center\Models\CenterEnrollment;
use App\Modules\Center\Models\Invoice;
use App\Modules\Center\Models\Mark;
use App\Modules\Center\Models\Session;
use App\Modules\Center\Models\Student;
use Illuminate\Support\Carbon;

/**
 * وثيقة 16.8 — التقرير الشهري: أهم مخرج للسنتر.
 *
 * يجمع في ورقة واحدة ما يسأل عنه ولي الأمر: حضر كم؟ درجاته كام؟
 * عليه فلوس؟ ولا يوجد منافس عربي أو أجنبي يجمعها اليوم.
 */
final class MonthlyReport
{
    public function __construct(private readonly TakeAttendance $attendance) {}

    /** @return array<string, mixed> */
    public function handle(Student $student, ?string $period = null): array
    {
        $period ??= now()->format('Y-m');
        $from = Carbon::parse($period.'-01')->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $enrollments = CenterEnrollment::where('student_id', $student->getKey())
            ->active()
            ->with(['group.subject', 'group.teacher'])
            ->get();

        return [
            'student' => $student,
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'groups' => $enrollments->map(fn (CenterEnrollment $e): array => $this->forGroup($student, $e, $from, $to))->all(),
            'finance' => $this->finance($student, $period),
            'overall_attendance' => $this->overallAttendance($student, $from, $to),
        ];
    }

    /** @return array<string, mixed> */
    private function forGroup(Student $student, CenterEnrollment $enrollment, Carbon $from, Carbon $to): array
    {
        $group = $enrollment->group;

        $sessionIds = Session::where('group_id', $group->getKey())
            // نصّياً «…-٠٥ ٠٠:٠٠:٠٠» أكبر من «…-٠٥»، فيسقط آخر يوم من التقرير
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->whereNotNull('attendance_taken_at')
            ->pluck('id');

        $rows = Attendance::whereIn('session_id', $sessionIds)
            ->where('student_id', $student->getKey())
            ->get();

        $marks = Mark::whereHas('assessment', fn ($q) => $q->where('group_id', $group->getKey())
            ->whereBetween('held_on', [$from->toDateString(), $to->toDateString()]))
            ->where('student_id', $student->getKey())
            ->with('assessment')
            ->get();

        $weighted = $marks->filter(fn (Mark $m): bool => $m->marks !== null);

        return [
            'group' => $group,
            'sessions' => $sessionIds->count(),
            'present' => $rows->filter(fn (Attendance $a): bool => $a->countsAsPresent())->count(),
            'absent' => $rows->where('status', 'absent')->count(),
            'late' => $rows->where('status', 'late')->count(),
            'excused' => $rows->where('status', 'excused')->count(),
            'attendance_rate' => $this->attendance->rateFor((int) $student->getKey(), (int) $group->getKey()),
            'marks' => $marks,
            'average' => $weighted->isEmpty() ? null : round(
                $weighted->sum(fn (Mark $m): float => (float) $m->percentage()) / $weighted->count(),
                1,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function finance(Student $student, string $period): array
    {
        $currency = (string) (tenant('currency') ?? 'EGP');

        $due = Invoice::where('student_id', $student->getKey())->outstanding()->get();

        return [
            'period_total' => Money::fromMinor(
                (int) Invoice::where('student_id', $student->getKey())->where('period', $period)->sum('total_minor'),
                $currency,
            ),
            'outstanding' => Money::fromMinor((int) $due->sum(fn (Invoice $i): int => $i->remaining()->minor), $currency),
            'overdue_count' => $due->filter(fn (Invoice $i): bool => $i->isOverdue())->count(),
            'invoices' => $due,
        ];
    }

    private function overallAttendance(Student $student, Carbon $from, Carbon $to): ?float
    {
        $rows = Attendance::where('student_id', $student->getKey())
            ->whereHas('session', fn ($q) => $q
                ->whereDate('date', '>=', $from->toDateString())
                ->whereDate('date', '<=', $to->toDateString()))
            ->get()
            ->reject(fn (Attendance $a): bool => $a->status === 'excused');

        if ($rows->isEmpty()) {
            return null;
        }

        return round($rows->filter(fn (Attendance $a): bool => $a->countsAsPresent())->count() / $rows->count() * 100, 1);
    }
}
