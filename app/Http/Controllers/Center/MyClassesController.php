<?php

declare(strict_types=1);

namespace App\Http\Controllers\Center;

use App\Modules\Center\Models\Attendance;
use App\Modules\Center\Models\CenterEnrollment;
use App\Modules\Center\Models\Session;
use App\Modules\Center\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * حصص الطالب — مجموعاته ومواعيدها ورابط دخولها.
 *
 * كان رابط الحصة يُحفظ على المجموعة ولا يظهر للطالب في أي شاشة،
 * فيضطر المدرّس إلى إرساله في واتساب قبل كل حصة. الرابط الذي لا
 * يصل صاحبه ليس ميزة.
 *
 * ويُعرض رابط الحصة قبل موعدها بنصف ساعة وحتى انتهائها: الرابط
 * الظاهر دائماً يُنسَخ ويُتداول خارج المشتركين.
 */
final class MyClassesController
{
    /** الدقائق قبل بدء الحصة التي يُفتح فيها الرابط */
    private const OPENS_BEFORE = 30;

    public function __invoke(Request $request): View
    {
        $student = Student::where('user_id', $request->user()->getKey())->first();

        if ($student === null) {
            return view('center.my-classes', [
                'student' => null,
                'groups' => collect(),
                'upcoming' => collect(),
                'attendance' => collect(),
                'opensBefore' => self::OPENS_BEFORE,
            ]);
        }

        $enrolments = CenterEnrollment::where('student_id', $student->getKey())
            ->where('status', 'active')
            ->with(['group.subject', 'group.teacher', 'group.schedules'])
            ->get();

        $groupIds = $enrolments->pluck('group_id')->filter()->all();

        return view('center.my-classes', [
            'student' => $student,
            'groups' => $enrolments,

            'upcoming' => $groupIds === [] ? collect() : Session::whereIn('group_id', $groupIds)
                ->whereDate('date', '>=', now()->toDateString())
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->with(['group.subject', 'room.branch', 'teacher'])
                ->orderBy('date')->orderBy('starts_at')
                ->limit(12)
                ->get(),

            // سجلّ حضوره: الطالب يسأل «كم غبتُ؟» قبل أن يسأله أحد
            'attendance' => Attendance::where('student_id', $student->getKey())
                ->with('session.group.subject')
                ->latest('id')
                ->limit(20)
                ->get(),

            'opensBefore' => self::OPENS_BEFORE,
        ]);
    }
}
