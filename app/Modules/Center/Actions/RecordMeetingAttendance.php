<?php

declare(strict_types=1);

namespace App\Modules\Center\Actions;

use App\Models\User;
use App\Modules\Center\Models\Attendance;
use App\Modules\Center\Models\CenterEnrollment;
use App\Modules\Center\Models\Session;
use App\Modules\Center\Models\Student;
use Illuminate\Support\Carbon;

/**
 * حضورٌ يُسجَّل عند دخول الحصة المباشرة.
 *
 * ## ما الذي نعرفه حقّاً
 *
 * نعرف أن الطالب فتح الغرفة من عندنا وهو مسجَّلٌ في المجموعة وفي
 * موعدها. ولا نعرف أنه بقي، ولا أنه سمع. وسجلٌّ يقول أكثر من ذلك
 * يكذب — والمدرّس يستطيع تعديله بيده وهو الذي يرى الغرفة.
 *
 * ## ولماذا لا يُسأل المزوّد
 *
 * Jitsi هو الافتراضي عندنا، ولا واجهةَ له تُسأل عمّن في الغرفة.
 * وميزةٌ تعمل مع مزوّدٍ واحد ولا تعمل مع الباقي أسوأ من ميزةٍ
 * تعمل مع الكل بجوابٍ أضيق — لأن المشترك لا يعرف أيّهما عنده.
 *
 * ## و«أونلاين» لا «حاضر»
 *
 * السنتر يفرّق بينهما في تقاريره ورسوم حصصه: من حضر القاعة ليس
 * كمن تابع من بيته. والعمود يعرف الحالتين منذ البداية.
 */
final class RecordMeetingAttendance
{
    /** بعد كم دقيقة من بداية الحصة يُعدّ الدخول تأخّراً */
    private const LATE_AFTER = 10;

    /** يُسجّل دخول الطالب — ويعيد الحالة، أو null إن لم يكن طالباً */
    public function handle(Session $session, User $user, ?Carbon $at = null): ?string
    {
        $at ??= now();

        $student = Student::where('user_id', $user->getKey())->first();

        if ($student === null) {
            return null;   // مدرّسٌ أو موظّف يدخل الغرفة: لا يُسجَّل في كشف الطلبة
        }

        $enrolled = CenterEnrollment::where('group_id', $session->group_id)
            ->where('student_id', $student->getKey())
            ->active()
            ->exists();

        if (! $enrolled) {
            return null;
        }

        /*
         | ما سجّله المدرّس بيده لا يُبدَّل.
         |
         | مدرّسٌ علّم طالباً «بعذر» له سببٌ يعرفه، ودخولٌ متأخّرٌ
         | إلى الغرفة لا يُلغي حكمه.
         */
        $existing = Attendance::where('session_id', $session->getKey())
            ->where('student_id', $student->getKey())
            ->first();

        if ($existing !== null && $existing->method === 'manual') {
            return (string) $existing->status;
        }

        $status = $this->statusAt($session, $at);

        Attendance::updateOrCreate(
            ['session_id' => $session->getKey(), 'student_id' => $student->getKey()],
            [
                'status' => $status,
                'method' => 'meeting',
                'recorded_by' => $user->getKey(),
                'recorded_at' => $at,
            ],
        );

        return $status;
    }

    /** «أونلاين» في وقته، و«متأخّر» بعد عشر دقائق */
    private function statusAt(Session $session, Carbon $at): string
    {
        $start = $session->date?->copy()
            ->setTimeFromTimeString((string) $session->starts_at);

        if ($start === null) {
            return 'online';
        }

        return $at->greaterThan($start->copy()->addMinutes(self::LATE_AFTER))
            ? 'late'
            : 'online';
    }
}
