<?php

declare(strict_types=1);

namespace App\Modules\Center\Actions;

use App\Models\User;
use App\Modules\Center\Models\Attendance;
use App\Modules\Center\Models\CenterEnrollment;
use App\Modules\Center\Models\Session;
use App\Modules\Center\Models\Student;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * تسجيلٌ ذاتي بكودٍ متغيّر على شاشة القاعة.
 *
 * ## لماذا لا يكفي كودٌ ثابت
 *
 * أوّلُ من يدخل يصوّره ويرسله لمن في البيت، فيُسجَّل الغائب حاضراً
 * — وسجلُّ حضورٍ يكذب أسوأ من غيابه: تُبنى عليه إنذاراتٌ لأولياء
 * الأمور وخصوماتٌ من رواتب.
 *
 * ## والدورة عشرون ثانية
 *
 * لقطةُ الشاشة تموت قبل أن تصل واتساب. وثانيةٌ أقلّ تجعل الطالب
 * يُخفق في المسح فيقف الطابور عند الباب — وهذا أوّل ما يشتكي منه
 * صاحب السنتر.
 *
 * ## والكود لا يُخزَّن
 *
 * هو توقيعٌ يحمل رقم الحصة ورقم الشريحة الزمنية. فلا جدولَ يُنظَّف،
 * ولا صفَّ يُكتب كل عشرين ثانية لكل قاعة، ولا يعمل كودُ قاعةٍ في
 * قاعةٍ أخرى.
 */
final class SelfCheckIn
{
    /** طول الشريحة الزمنية بالثواني */
    public const SLOT = 20;

    /**
     * كم شريحةً ماضية تُقبل.
     *
     * الطالب يفتح الكاميرا ويصوّب ويضغط: عشرون ثانية قد تمرّ في
     * ذلك وحده. وقبولُ الشريحة السابقة يجعل الكود صالحاً أربعين
     * ثانية على الأكثر — لا يكفي لإرساله إلى بيتٍ بعيد.
     */
    private const GRACE_SLOTS = 1;

    /** بعد كم دقيقة من بداية الحصة يُسجَّل «متأخّر» لا «حاضر» */
    private const LATE_AFTER = 10;

    /** الكود الحالي لحصة — يُطلب كل عشرين ثانية من شاشة القاعة */
    public function token(Session $session, ?Carbon $at = null): string
    {
        $slot = $this->slot($at ?? now());

        return $session->getKey().'.'.$slot.'.'.$this->signature($session, $slot);
    }

    /** متى ينتهي الكود الحالي — لتعرف الشاشة متى تطلب غيره */
    public function expiresIn(?Carbon $at = null): int
    {
        $now = ($at ?? now())->getTimestamp();

        return self::SLOT - ($now % self::SLOT);
    }

    /**
     * يقرأ الكود ويُسجّل الحضور.
     *
     * @return array{status:string, session:Session}
     *
     * @throws RuntimeException
     */
    public function handle(string $token, User $user, ?Carbon $at = null): array
    {
        $at ??= now();

        $session = $this->verify($token, $at);

        $student = Student::where('user_id', $user->getKey())->first();

        if ($student === null) {
            throw new RuntimeException(__('حسابك ليس حساب طالب في السنتر.'));
        }

        /*
         | ولا يُسجَّل إلا من هو في المجموعة.
         |
         | كودُ القاعة يراه كلّ من في الغرفة، وطالبُ الحصة التالية
         | ينتظر عند الباب فيمسحه — فيُسجَّل في حصةٍ ليست له.
         */
        $enrolled = CenterEnrollment::where('group_id', $session->group_id)
            ->where('student_id', $student->getKey())
            ->active()
            ->exists();

        if (! $enrolled) {
            throw new RuntimeException(__('أنت لست في هذه المجموعة.'));
        }

        $status = $this->statusAt($session, $at);

        /*
         | ولا يُبدَّل ما سجّله المدرّس بيده.
         |
         | مدرّسٌ علّم طالباً «غائباً» أو «بعذر» له سببٌ يعرفه؛ وكودٌ
         | مُسح بعدها من هاتفٍ في الممرّ لا يُلغي حكمه.
         */
        $existing = Attendance::where('session_id', $session->getKey())
            ->where('student_id', $student->getKey())
            ->first();

        if ($existing !== null && $existing->method === 'manual') {
            return ['status' => (string) $existing->status, 'session' => $session];
        }

        Attendance::updateOrCreate(
            ['session_id' => $session->getKey(), 'student_id' => $student->getKey()],
            [
                'status' => $status,
                'method' => 'self',
                'recorded_by' => $user->getKey(),
                'recorded_at' => $at,
            ],
        );

        return ['status' => $status, 'session' => $session];
    }

    /**
     * يتحقّق من الكود ويعيد حصّته.
     *
     * @throws RuntimeException
     */
    public function verify(string $token, ?Carbon $at = null): Session
    {
        $at ??= now();

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new RuntimeException(__('الكود غير صالح.'));
        }

        [$sessionId, $slot, $signature] = $parts;

        $session = Session::find($sessionId);

        if ($session === null) {
            throw new RuntimeException(__('الكود غير صالح.'));
        }

        $current = $this->slot($at);

        /*
         | الشريحة تُقارَن قبل التوقيع.
         |
         | توقيعُ شريحةٍ قديمة صحيحٌ إلى الأبد؛ وما يُبطله هو الزمن
         | لا الحساب.
         */
        if ((int) $slot > $current || (int) $slot < $current - self::GRACE_SLOTS) {
            throw new RuntimeException(__('انتهى هذا الكود — امسح الكود الظاهر الآن.'));
        }

        if (! hash_equals($this->signature($session, (int) $slot), $signature)) {
            throw new RuntimeException(__('الكود غير صالح.'));
        }

        return $session;
    }

    /** «حاضر» أول عشر دقائق، ثم «متأخّر» */
    private function statusAt(Session $session, Carbon $at): string
    {
        $start = $session->date?->copy()
            ->setTimeFromTimeString((string) $session->starts_at);

        if ($start === null) {
            return 'present';
        }

        return $at->greaterThan($start->copy()->addMinutes(self::LATE_AFTER))
            ? 'late'
            : 'present';
    }

    private function slot(Carbon $at): int
    {
        return intdiv($at->getTimestamp(), self::SLOT);
    }

    /**
     * التوقيع بمفتاح التطبيق.
     *
     * وبلا تخزين: الكود يحمل ما يكفي للتحقّق منه، فلا صفَّ يُكتب كل
     * عشرين ثانية لكل قاعة ولا جدولَ يُنظَّف بعد ذلك.
     */
    private function signature(Session $session, int $slot): string
    {
        return mb_substr(hash_hmac(
            'sha256',
            'checkin:'.$session->getKey().':'.$slot,
            (string) config('app.key'),
        ), 0, 16);
    }
}
