<?php

declare(strict_types=1);

namespace App\Http\Controllers\Center;

use App\Models\User;
use App\Modules\Center\Actions\MonthlyReport;
use App\Modules\Center\Models\Grade;
use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Guardian;
use App\Modules\Center\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * ملف الطالب: كل ما يخصّه في شاشة واحدة — حضوره وأقساطه ودرجاته.
 * الموظف على الاستقبال لا يملك وقتاً لفتح خمس شاشات.
 */
final class StudentFileController
{
    public function __construct(private readonly MonthlyReport $report) {}

    public function show(Request $request, string $id): View
    {
        $student = Student::with(['user', 'grade', 'branch', 'guardians'])->findOrFail($id);
        $period = (string) $request->input('period', now()->format('Y-m'));

        return view('center.student', [
            'student' => $student,
            'report' => $this->report->handle($student, $period),
            'period' => $period,
            'payments' => $student->payments()->latest('paid_at')->limit(20)->get(),
            // ما يمكن تسجيله فيه الآن: المفتوح، وليس هو فيه بالفعل
            'openGroups' => Group::open()
                ->whereNotIn('id', $student->enrollments()->active()->pluck('group_id'))
                ->orderBy('grade_id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('center.student-create', [
            'grades' => Grade::with('stage')->get()
                ->sortBy(fn (Grade $g): array => [(int) ($g->stage?->position ?? 0), (int) $g->position])
                ->values(),
        ]);
    }

    /**
     * طالب جديد = حساب دخول + سجلّ طالب + ولي أمر (اختياري) — في خطوة واحدة.
     *
     * ثلاثة جداول وراء نموذج واحد؛ تفريقها ثلاث شاشات كان يعني أن
     * الطالب يُضاف بلا ولي أمر، فلا يصل تنبيه غيابه إلى أحد.
     */
    public function store(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:190', 'unique:users,email'],
            'grade_id' => ['required', 'integer', 'exists:center_grades,id'],
            'school' => ['nullable', 'string', 'max:120'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female'],
            'guardian_name' => ['nullable', 'string', 'max:120', 'required_with:guardian_phone'],
            'guardian_phone' => ['nullable', 'string', 'max:32', 'required_with:guardian_name'],
            'guardian_relation' => ['nullable', 'string', 'max:32'],
            'guardian_whatsapp' => ['nullable', 'string', 'max:32'],
        ], [], [
            'name' => __('اسم الطالب'), 'phone' => __('هاتف الطالب'), 'email' => __('البريد'),
            'grade_id' => __('الصف'), 'guardian_name' => __('اسم ولي الأمر'), 'guardian_phone' => __('هاتف ولي الأمر'),
        ]);

        $grade = Grade::findOrFail($input['grade_id']);

        $student = DB::transaction(function () use ($input, $grade): Student {
            $code = Student::nextCode();

            $user = User::create([
                'name' => $input['name'],
                // بلا بريد: عنوان داخلي فريد، فالطالب الصغير يدخل بهاتفه لا ببريده
                'email' => $input['email'] ?: strtolower($code).'@students.'.tenant('slug').'.local',
                'phone' => $input['phone'],
                'password' => Hash::make(Str::password(16)),
                'role' => 'student',
                'status' => 'active',
                'email_verified_at' => $input['email'] ? null : now(),
            ]);

            $student = Student::create([
                'user_id' => $user->getKey(),
                'code' => $code,
                'stage_id' => $grade->stage_id,
                'grade_id' => $grade->getKey(),
                'school' => $input['school'] ?? null,
                'birth_date' => $input['birth_date'] ?? null,
                'gender' => $input['gender'] ?? null,
                'joined_at' => now()->toDateString(),
                'status' => 'active',
            ]);

            if (filled($input['guardian_name'] ?? null)) {
                $guardian = Guardian::create([
                    'name' => $input['guardian_name'],
                    'relation' => $input['guardian_relation'] ?? null,
                    'phone' => $input['guardian_phone'],
                    'whatsapp' => $input['guardian_whatsapp'] ?: $input['guardian_phone'],
                ]);

                $guardian->students()->attach($student->getKey(), ['is_primary' => true]);
            }

            return $student;
        });

        return redirect(url('/admin/center-students/'.$student->getKey()))
            ->with('status', __('أُضيف :name بكود :code. سجّله الآن في مجموعة.', [
                'name' => $input['name'], 'code' => $student->code,
            ]));
    }

    /** التقرير الشهري كما يصل ولي الأمر. */
    public function monthly(Request $request, string $id): View
    {
        $student = Student::with(['user', 'grade'])->findOrFail($id);

        return view('center.monthly-report', [
            'report' => $this->report->handle($student, (string) $request->input('period', now()->format('Y-m'))),
        ]);
    }
}
