<?php

declare(strict_types=1);

namespace App\Http\Controllers\Center;

use App\Modules\Center\Actions\EnrolStudent;
use App\Modules\Center\Models\CenterEnrollment;
use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * تسجيل الطالب في مجموعة — من صفحة المجموعة أو من ملفه.
 *
 * كان الفعل موجوداً (`EnrolStudent`) ولا شاشة تستدعيه: ملف الطالب
 * يقول «سجّله في مجموعة ليبدأ حضوره وأقساطه» ولا زرّ يفعل ذلك.
 * فلا طالب دخل مجموعة إلا من بذرة.
 */
final class GroupEnrolmentController
{
    public function __construct(private readonly EnrolStudent $enrol) {}

    public function store(Request $request, string $groupId): RedirectResponse
    {
        $group = Group::findOrFail($groupId);

        $input = $request->validate([
            'student_id' => ['required', 'integer', 'exists:center_students,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $student = Student::with('user')->findOrFail($input['student_id']);

        try {
            $this->enrol->handle(
                $student,
                $group,
                filled($input['discount'] ?? null) ? (int) round(((float) $input['discount']) * 100) : null,
                $input['reason'] ?? null,
            );
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['student_id' => $e->getMessage()]);
        }

        return back()->with('status', __('سُجّل :name في :group.', [
            'name' => $student->name(),
            'group' => (string) $group->name,
        ]));
    }

    /** إخراج الطالب من المجموعة — يبقى سجلّه وحضوره، وتتوقّف أقساطه القادمة. */
    public function destroy(string $groupId, string $enrollmentId): RedirectResponse
    {
        $enrollment = CenterEnrollment::where('group_id', $groupId)->with('student.user')->findOrFail($enrollmentId);

        $this->enrol->drop($enrollment);

        return back()->with('status', __('أُخرج :name من المجموعة.', ['name' => $enrollment->student?->name() ?? '']));
    }
}
