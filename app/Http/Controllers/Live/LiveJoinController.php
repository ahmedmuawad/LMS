<?php

declare(strict_types=1);

namespace App\Http\Controllers\Live;

use App\Core\Access\Ability;
use App\Models\User;
use App\Modules\Center\Actions\RecordMeetingAttendance;
use App\Modules\Center\Models\CenterEnrollment;
use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Session;
use App\Modules\Center\Models\Student;
use App\Modules\Live\LiveRooms;
use App\Modules\Live\Providers\BigBlueButtonProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * دخول الحصة المباشرة — لكل المزوّدين.
 *
 * ## لماذا نقطةٌ عندنا لا رابطٌ مباشر
 *
 * رابط الدخول عند BBB موقَّعٌ باسم الداخل ودوره، فلا يصلح رابطٌ
 * واحد للجميع: كلٌّ يدخل باسمه، والمدرّس مديراً والطالب مشاهداً.
 *
 * وبناؤه هنا يحرس ما لا يحرسه رابطٌ منسوخ: التسجيل في المجموعة،
 * ونافذة الموعد. فرابطٌ يُنسَخ في مجموعةِ واتساب لا ينفع من ليس
 * مسجّلاً، ولا ينفع قبل موعده.
 *
 * ## والمرور من هنا يُسجّل الحضور
 *
 * Jitsi — وهو افتراضينا — لا واجهةَ له تُسأل عمّن في الغرفة. لكنّ
 * من دخل من عندنا نعرفه يقيناً؛ فيُكتب ذلك، ويبقى للمدرّس تعديله.
 * وكان البديل أن يُعلّم خمسةً وعشرين اسماً من ذاكرته بعد الحصة.
 *
 * ولهذا يمرّ كلُّ مزوّدٍ من هنا لا BBB وحده: رابطٌ مباشر في
 * الشاشة يعني حصّةً أونلاين بلا كشف حضور.
 */
final class LiveJoinController
{
    public function __construct(private readonly RecordMeetingAttendance $attendance) {}

    public function __invoke(Request $request, string $seed, LiveRooms $rooms, BigBlueButtonProvider $bbb): RedirectResponse
    {
        $user = $request->user();

        abort_if(! $user instanceof User, 403);

        [$kind, $id] = $this->parse($seed);

        $session = $kind === 'session' ? Session::with('group')->find($id) : null;
        $group = $session?->group ?? ($kind === 'group' ? Group::find($id) : null);

        abort_if($group === null, 404);

        $moderator = $user->allows(Ability::CENTER_MANAGE);

        abort_unless($moderator || $this->enrolled($user, $group), 403, __('لست مسجّلاً في هذه المجموعة.'));

        $meeting = $session !== null ? $rooms->forSession($session) : $rooms->forGroup($group);

        abort_if($meeting === null, 404);

        /*
         | النافذة تُفحص هنا كذلك.
         |
         | الشاشة تُخفي الزرّ خارجها، وإخفاءُ زرٍّ ليس منعاً: الرابط
         | يُحفظ ويُفتح في أي وقت. والمدرّس مستثنًى — قد يدخل مبكّراً
         | ليجهّز.
         */
        abort_unless($moderator || $meeting->isOpen(), 403, __('لم يُفتح باب الحصة بعد.'));

        /*
         | والحضور يُسجَّل عند الدخول لا بعد الحصة.
         |
         | كان المدرّس يُنهي حصّةً أونلاين ثم يفتح الكشف ويعلّم
         | خمسة وعشرين اسماً من ذاكرته — فيُعلَّم الكلّ حاضرين.
         | ونحن نعرف من دخل من عندنا يقيناً؛ فيُكتب ما نعرفه،
         | ويبقى للمدرّس تعديلُه.
         |
         | ويُسجَّل قبل التحويل لا بعده: بعد `redirect` لا يعود
         | المتصفّح إلينا أبداً.
         */
        if ($session !== null && ! $moderator) {
            $this->attendance->handle($session, $user);
        }

        if ($meeting->provider !== 'bbb') {
            return redirect()->away($meeting->url);
        }

        $room = $meeting->room ?? $seed;

        try {
            $bbb->create($room, $this->title($session, $group));

            return redirect()->away($bbb->joinUrl($room, (string) $user->name, $moderator));
        } catch (RuntimeException $e) {
            return back()->withErrors(['live' => $e->getMessage()]);
        }
    }

    /** @return array{0:string, 1:int} */
    private function parse(string $seed): array
    {
        // `session-12` أو `group-3` — وما سواهما لا يُفهم
        abort_unless(preg_match('/^(session|group)-(\d+)$/', $seed, $m) === 1, 404);

        return [$m[1], (int) $m[2]];
    }

    private function enrolled(User $user, Group $group): bool
    {
        $student = Student::where('user_id', $user->getKey())->first();

        if ($student === null) {
            return false;
        }

        return CenterEnrollment::where('group_id', $group->getKey())
            ->where('student_id', $student->getKey())
            ->where('status', 'active')
            ->exists();
    }

    private function title(?Session $session, Group $group): string
    {
        return trim((string) $group->name).($session !== null && $session->date !== null
            ? ' — '.$session->date->toDateString()
            : '');
    }
}
