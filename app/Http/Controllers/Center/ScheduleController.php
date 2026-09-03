<?php

declare(strict_types=1);

namespace App\Http\Controllers\Center;

use App\Modules\Center\Actions\DetectConflicts;
use App\Modules\Center\Actions\GenerateSessions;
use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Room;
use App\Modules\Center\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

final class ScheduleController
{
    public function __construct(private readonly DetectConflicts $conflicts) {}

    /** جدول الأسبوع: صفوف الأيام وأعمدة القاعات. */
    public function week(Request $request): View
    {
        $start = ($request->date('from') ?? now())->startOfWeek(Carbon::SUNDAY);
        $end = $start->copy()->addDays(6);

        return view('center.schedule', [
            'from' => $start,
            'to' => $end,
            'days' => collect(range(0, 6))->map(fn (int $i) => $start->copy()->addDays($i)),
            'sessions' => Session::with(['group.subject', 'room', 'teacher'])
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->active()
                ->orderBy('starts_at')
                ->get()
                ->groupBy(fn (Session $s): string => $s->date->toDateString()),
            'rooms' => Room::where('is_active', true)->get(),
        ]);
    }

    /** فحص التعارض قبل الحفظ — يُنادى من النموذج مباشرة. */
    public function check(Request $request): JsonResponse
    {
        $input = $request->validate([
            'group_id' => ['required', 'integer', 'exists:center_groups,id'],
            'room_id' => ['nullable', 'integer', 'exists:center_rooms,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
            'starts_at' => ['required', 'string'],
            'ends_at' => ['required', 'string'],
            'ignore_session_id' => ['nullable', 'integer'],
        ]);

        $conflicts = $this->conflicts->handle($input);

        return response()->json([
            'ok' => $conflicts === [],
            'conflicts' => $conflicts,
            'suggestion' => $conflicts === [] ? null : $this->conflicts->suggestAlternative($input),
        ]);
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'group_id' => ['required', 'integer', 'exists:center_groups,id'],
            'room_id' => ['nullable', 'integer', 'exists:center_rooms,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
            'starts_at' => ['required', 'string'],
            'ends_at' => ['required', 'string'],
            'topic' => ['nullable', 'string', 'max:200'],
            'force' => ['nullable', 'boolean'],
        ]);

        $conflicts = $this->conflicts->handle($input);

        // العطلة وحدها قابلة للتجاوز بقرار واعٍ؛ باقي التعارضات لا
        $blocking = array_filter($conflicts, fn (array $c): bool => $c['code'] !== 'holiday' || ! ($input['force'] ?? false));

        if ($blocking !== []) {
            return back()
                ->withInput()
                ->withErrors(['schedule' => collect($blocking)->pluck('message')->implode(' · ')]);
        }

        Session::create([...$input, 'status' => 'scheduled']);

        return back()->with('status', __('أُضيفت الحصة.'));
    }

    public function generate(Request $request, string $groupId, GenerateSessions $action): RedirectResponse
    {
        $group = Group::findOrFail($groupId);

        $result = $action->handle(
            $group,
            $request->date('from'),
            $request->date('to'),
        );

        return back()->with('status', __('وُلِّدت :created حصة، وتُخطّيت :holidays في العطلات.', [
            'created' => $result['created'],
            'holidays' => $result['holidays'],
        ]));
    }

    public function cancelSession(Request $request, string $sessionId): RedirectResponse
    {
        $session = Session::findOrFail($sessionId);

        $session->forceFill([
            'status' => $request->input('mode') === 'postpone' ? 'postponed' : 'cancelled',
            'notes' => trim(($session->notes ?? '')."\n".$request->input('reason')),
        ])->save();

        return back()->with('status', __('حُدِّثت حالة الحصة، وسيُخطَر الطلاب وأولياء الأمور.'));
    }
}
