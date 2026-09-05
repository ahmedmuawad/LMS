<?php

declare(strict_types=1);

namespace App\Http\Controllers\Center;

use App\Modules\Center\Actions\TakeAttendance;
use App\Modules\Center\Models\Attendance;
use App\Modules\Center\Models\CenterEnrollment;
use App\Modules\Center\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * شاشة الحضور — تُستخدم على جهاز لوحي عند الباب وبإنترنت ضعيف.
 * أقل عدد نقرات، وأزرار كبيرة، وحفظ واحد في النهاية.
 */
final class AttendanceController
{
    public function __construct(private readonly TakeAttendance $attendance) {}

    public function today(Request $request): View
    {
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();

        return view('center.attendance-day', [
            'date' => $date,
            'sessions' => Session::with(['group.subject', 'room', 'teacher'])
                ->onDate($date)
                ->active()
                ->orderBy('starts_at')
                ->get(),
        ]);
    }

    public function show(string $sessionId): View
    {
        $session = Session::with(['group.subject', 'room', 'teacher'])->findOrFail($sessionId);

        $students = CenterEnrollment::where('group_id', $session->group_id)
            ->active()
            ->with('student.user')
            ->get()
            ->map(fn (CenterEnrollment $e) => $e->student)
            ->filter()
            ->sortBy(fn ($student): string => (string) $student->name())
            ->values();

        return view('center.attendance', [
            'session' => $session,
            'students' => $students,
            'existing' => Attendance::where('session_id', $session->getKey())
                ->pluck('status', 'student_id'),
        ]);
    }

    public function store(Request $request, string $sessionId): RedirectResponse
    {
        $session = Session::findOrFail($sessionId);

        $input = $request->validate([
            'status' => ['nullable', 'array'],
            'status.*' => ['string', 'in:'.implode(',', array_keys(Attendance::STATUSES))],
        ]);

        try {
            $summary = $this->attendance->handle(
                $session,
                $input['status'] ?? [],
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['session' => $e->getMessage()]);
        }

        return redirect(url('/admin/attendance?date='.$session->date->toDateString()))
            ->with('status', __('سُجّل الحضور: :present حاضر · :absent غائب.', [
                'present' => $summary['present'] + $summary['late'] + $summary['online'],
                'absent' => $summary['absent'],
            ]));
    }

    /** مسح كارنيه أو إدخال كود — يُنادى من نفس الشاشة بلا إعادة تحميل. */
    public function mark(Request $request, string $sessionId): JsonResponse
    {
        $session = Session::findOrFail($sessionId);

        $input = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'method' => ['nullable', 'string', 'in:code,qr,nfc,fingerprint,self'],
        ]);

        try {
            $record = $this->attendance->mark(
                $session,
                $input['code'],
                $input['method'] ?? 'code',
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'student_id' => $record->student_id,
            'name' => $record->student?->name(),
            'status' => $record->status,
            'late' => $record->minutes_late,
        ]);
    }
}
