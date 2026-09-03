<?php

declare(strict_types=1);

namespace App\Http\Controllers\Center;

use App\Modules\Center\Actions\MonthlyReport;
use App\Modules\Center\Models\Guardian;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * بوابة ولي الأمر.
 *
 * «ولي الأمر بيقول ابنه دفع» لا تُحلّ بمكالمة: كشف الحساب متاح له
 * لحظياً، وحضور ابنه أمامه، فلا يحتاج أن يسأل أصلاً.
 */
final class GuardianPortalController
{
    public function __construct(private readonly MonthlyReport $report) {}

    public function index(Request $request): View
    {
        $guardian = $this->guardian($request);

        return view('center.guardian', [
            'guardian' => $guardian,
            'children' => $guardian->students()->with(['user', 'grade'])->get(),
        ]);
    }

    public function child(Request $request, string $studentId): View
    {
        $guardian = $this->guardian($request);

        $student = $guardian->students()->with(['user', 'grade'])->findOrFail($studentId);

        return view('center.guardian-child', [
            'student' => $student,
            'report' => $this->report->handle($student, (string) $request->input('period', now()->format('Y-m'))),
        ]);
    }

    private function guardian(Request $request): Guardian
    {
        $guardian = Guardian::where('user_id', $request->user()?->getKey())->first();

        abort_if($guardian === null, 403, __('هذا الحساب ليس ولي أمر مسجّلاً.'));

        return $guardian;
    }
}
