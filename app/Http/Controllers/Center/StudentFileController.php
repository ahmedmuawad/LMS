<?php

declare(strict_types=1);

namespace App\Http\Controllers\Center;

use App\Modules\Center\Actions\MonthlyReport;
use App\Modules\Center\Models\Student;
use Illuminate\Http\Request;
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
        ]);
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
