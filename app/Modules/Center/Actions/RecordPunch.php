<?php

declare(strict_types=1);

namespace App\Modules\Center\Actions;

use App\Modules\Center\Models\Attendance;
use App\Modules\Center\Models\AttendanceDevice;
use App\Modules\Center\Models\CenterEnrollment;
use App\Modules\Center\Models\DevicePunch;
use App\Modules\Center\Models\Session;
use App\Modules\Center\Models\Student;
use Illuminate\Support\Carbon;

/**
 * بصمةٌ وصلت من جهاز — تُطابَق بحصةٍ وتُسجَّل حضوراً.
 *
 * ## البصمة تُحفظ دائماً حتى إن لم تُطابَق
 *
 * صاحب السنتر يسأل: «الجهاز شغّال؟» — وسجلٌّ فيه «كودٌ غير معروف»
 * يجيب بنعم ويدلّ على الخطأ الحقيقي (طالبٌ لم يُسجَّل بعد). أمّا
 * رميُ ما لا يُطابَق فيجعل الجهاز يبدو معطّلاً وهو يعمل.
 *
 * ## والمطابقة تختار أقرب حصة لا أوّل حصة
 *
 * الطالب قد يكون في مجموعتين في اليوم نفسه، فتُختار الحصة الأقرب
 * إلى وقت بصمته — وإلا سُجّل حضوره في حصة الصباح وهو جاء للمساء.
 */
final class RecordPunch
{
    /** كم دقيقة قبل الحصة وبعدها تُقبل البصمة */
    private const WINDOW = 45;

    /** @return array{result:string, session_id:?int, student:?string} */
    public function handle(AttendanceDevice $device, string $code, ?Carbon $at = null): array
    {
        $at ??= now();
        $code = trim($code);

        $student = Student::where('code', $code)->first();

        if ($student === null) {
            return $this->log($device, $code, $at, 'unknown_code', null, null);
        }

        $session = $this->sessionFor($student, $at);

        if ($session === null) {
            return $this->log($device, $code, $at, 'no_session', null, $student->name());
        }

        $existing = Attendance::where('session_id', $session->getKey())
            ->where('student_id', $student->getKey())
            ->first();

        /*
         | البصمة الثانية لا تُبدّل ما سجّله المدرّس بيده.
         |
         | طالبٌ عُلّم «بعذر» ثم مرّ بالجهاز لا يصير «حاضراً» —
         | فقرار المدرّس أعلم من الجهاز، والجهاز لا يعرف أن الطالب
         | جاء ليأخذ ورقةً وينصرف.
         */
        if ($existing !== null && $existing->method === 'manual') {
            return $this->log($device, $code, $at, 'duplicate', $session->getKey(), $student->name());
        }

        Attendance::updateOrCreate(
            ['session_id' => $session->getKey(), 'student_id' => $student->getKey()],
            [
                // المتأخّر يُعلَّم متأخّراً لا حاضراً: الفرق هو ما يُتابَع
                'status' => $this->statusFor($session, $at),
                'method' => $device->kind === 'fingerprint' ? 'fingerprint' : 'nfc',
                'recorded_at' => $at,
            ],
        );

        $session->forceFill(['attendance_taken_at' => now()])->save();

        return $this->log($device, $code, $at, 'matched', $session->getKey(), $student->name());
    }

    private function sessionFor(Student $student, Carbon $at): ?Session
    {
        $groupIds = CenterEnrollment::where('student_id', $student->getKey())
            ->where('status', 'active')
            ->pluck('group_id');

        if ($groupIds->isEmpty()) {
            return null;
        }

        return Session::whereIn('group_id', $groupIds)
            ->whereDate('date', $at->toDateString())
            ->whereIn('status', ['scheduled', 'running'])
            ->get()
            ->map(fn (Session $s): array => [
                'session' => $s,
                'distance' => $this->minutesFrom($s, $at),
            ])
            ->filter(fn (array $row): bool => $row['distance'] !== null && $row['distance'] <= self::WINDOW)
            ->sortBy('distance')
            ->first()['session'] ?? null;
    }

    private function minutesFrom(Session $session, Carbon $at): ?int
    {
        $start = $session->date?->copy()->setTimeFromTimeString((string) $session->starts_at);

        return $start === null ? null : (int) abs($start->diffInMinutes($at));
    }

    private function statusFor(Session $session, Carbon $at): string
    {
        $start = $session->date?->copy()->setTimeFromTimeString((string) $session->starts_at);
        $grace = (int) setting('center.late_after_minutes', 10);

        return $start !== null && $at->greaterThan($start->copy()->addMinutes($grace))
            ? 'late'
            : 'present';
    }

    /** @return array{result:string, session_id:?int, student:?string} */
    private function log(
        AttendanceDevice $device,
        string $code,
        Carbon $at,
        string $result,
        ?int $sessionId,
        ?string $student,
    ): array {
        DevicePunch::create([
            'device_id' => $device->getKey(),
            'code' => $code,
            'punched_at' => $at,
            'result' => $result,
            'session_id' => $sessionId,
        ]);

        $device->forceFill(['last_seen_at' => now()])->saveQuietly();

        return ['result' => $result, 'session_id' => $sessionId, 'student' => $student];
    }
}
