<?php

declare(strict_types=1);

namespace App\Http\Controllers\Center;

use App\Models\User;
use App\Modules\Center\Actions\DetectConflicts;
use App\Modules\Center\Actions\GenerateSessions;
use App\Modules\Center\Models\Branch;
use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Room;
use App\Modules\Center\Models\Schedule;
use App\Modules\Center\Models\Session;
use App\Modules\Center\Models\Subject;
use App\Modules\Center\Models\SubjectTeacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    /**
     * إشغال القاعات في يوم من الأسبوع: قاعات × ساعات.
     *
     * جدول اليوم لا يجيب سؤال الاستقبال الحقيقي: «فين قاعة فاضية
     * السبت الساعة ٤؟». هذه الشبكة تجيبه بنظرة، وتُظهر المشغول
     * باسم مجموعته ومدرّسه.
     */
    public function rooms(Request $request): View
    {
        $weekday = (int) $request->input('weekday', now()->dayOfWeek);
        $branchId = $request->input('branch');

        $rooms = Room::with('branch')->where('is_active', true)
            ->when(filled($branchId), fn ($q) => $q->where('branch_id', (int) $branchId))
            ->orderBy('branch_id')->get();

        $slots = Schedule::with(['group.subject', 'group.teacher', 'group.grade'])
            ->where('weekday', $weekday)
            ->whereIn('room_id', $rooms->pluck('id'))
            ->orderBy('starts_at')
            ->get()
            ->groupBy('room_id');

        return view('center.rooms', [
            'weekday' => $weekday,
            'weekdays' => Schedule::WEEKDAYS,
            'rooms' => $rooms,
            'slots' => $slots,
            'branches' => Branch::where('is_active', true)->get(),
            'branch' => $branchId,
            // نطاق العرض من أبكر موعد إلى أمتده، لا من منتصف الليل
            'hours' => $this->hourRange($slots->flatten()),
        ]);
    }

    /**
     * مدرّسو السنتر: كل واحد بمادته ومواعيده وطلبته.
     *
     * «كل مدرّس له مواعيده وطلبته في مادته» — هذه هي الشاشة التي
     * تقولها. وبغيرها تُقرأ من ثلاث شاشات وتُجمَع في الذهن.
     */
    public function teachers(Request $request): View
    {
        $assignments = SubjectTeacher::active()->with(['teacher', 'subject', 'branch'])->get()
            ->groupBy('user_id');

        $groups = Group::with(['subject', 'grade', 'schedules.room'])
            ->whereNotNull('teacher_id')
            ->get()
            ->groupBy('teacher_id');

        $teachers = User::whereIn('id', $assignments->keys())
            ->orderBy('name')->get()
            ->map(fn (User $teacher): array => [
                'teacher' => $teacher,
                'subjects' => ($assignments[$teacher->getKey()] ?? collect())
                    ->map(fn (SubjectTeacher $row): string => (string) $row->subject?->name)
                    ->filter()->unique()->values(),
                'groups' => $groups[$teacher->getKey()] ?? collect(),
                'students' => (int) ($groups[$teacher->getKey()] ?? collect())->sum('enrolled_count'),
                'slots' => ($groups[$teacher->getKey()] ?? collect())
                    ->flatMap(fn (Group $group) => $group->schedules)
                    ->sortBy([['weekday', 'asc'], ['starts_at', 'asc']])
                    ->values(),
            ]);

        return view('center.teachers', [
            'teachers' => $teachers,
            'subjects' => Subject::where('is_active', true)->get(),
            'weekdays' => Schedule::WEEKDAYS,
        ]);
    }

    /** المواعيد الأسبوعية لمجموعة: إضافة وحذف بفحص تعارض. */
    public function slots(Request $request, string $groupId): View
    {
        $group = Group::with(['subject', 'teacher', 'branch', 'grade'])->findOrFail($groupId);

        return view('center.group-slots', [
            'group' => $group,
            'slots' => $group->schedules()->with('room')->orderBy('weekday')->orderBy('starts_at')->get(),
            'rooms' => Room::with('branch')->where('is_active', true)
                ->when($group->branch_id !== null, fn ($q) => $q->where('branch_id', $group->branch_id))
                ->get(),
            'weekdays' => Schedule::WEEKDAYS,
        ]);
    }

    public function storeSlot(Request $request, string $groupId): RedirectResponse
    {
        $group = Group::findOrFail($groupId);

        $input = $request->validate([
            'room_id' => ['nullable', 'integer', 'exists:center_rooms,id'],
            'weekday' => ['required', 'integer', 'min:0', 'max:6'],
            'starts_at' => ['required', 'string'],
            'ends_at' => ['required', 'string'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $conflicts = $this->conflicts->forSchedule([
            ...$input,
            'group_id' => (int) $group->getKey(),
            'teacher_id' => $group->teacher_id,
        ]);

        if ($conflicts !== []) {
            return back()->withInput()->withErrors([
                'slot' => collect($conflicts)->pluck('message')->implode(' · '),
            ]);
        }

        Schedule::create([...$input, 'group_id' => $group->getKey()]);

        return back()->with('status', __('أُضيف الموعد. ولّد الحصص لتظهر في الجدول.'));
    }

    public function destroySlot(string $groupId, string $slotId): RedirectResponse
    {
        Schedule::where('group_id', $groupId)->findOrFail($slotId)->delete();

        return back()->with('status', __('حُذف الموعد. الحصص المولَّدة منه تبقى حتى تُلغيها.'));
    }

    /** فحص تعارض الموعد المتكرر — يُنادى من النموذج قبل الحفظ. */
    public function checkSlot(Request $request, string $groupId): JsonResponse
    {
        $group = Group::findOrFail($groupId);

        $input = $request->validate([
            'room_id' => ['nullable', 'integer'],
            'weekday' => ['required', 'integer', 'min:0', 'max:6'],
            'starts_at' => ['required', 'string'],
            'ends_at' => ['required', 'string'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date'],
            'ignore_schedule_id' => ['nullable', 'integer'],
        ]);

        $conflicts = $this->conflicts->forSchedule([
            ...$input,
            'group_id' => (int) $group->getKey(),
            'teacher_id' => $group->teacher_id,
        ]);

        return response()->json(['ok' => $conflicts === [], 'conflicts' => $conflicts]);
    }

    /**
     * ساعات العرض: من أبكر موعد إلى أمتده.
     *
     * @param  Collection<int, Schedule>  $slots
     * @return list<int>
     */
    private function hourRange($slots): array
    {
        if ($slots->isEmpty()) {
            return range(14, 21);
        }

        $start = (int) substr((string) $slots->min('starts_at'), 0, 2);
        $end = (int) ceil((float) substr((string) $slots->max('ends_at'), 0, 2) + (substr((string) $slots->max('ends_at'), 3, 2) === '00' ? 0 : 1));

        return range(max(0, $start), min(23, max($end, $start + 1)));
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

        $message = __('وُلِّدت :created حصة، وتُخطّيت :holidays في العطلات.', [
            'created' => $result['created'],
            'holidays' => $result['holidays'],
        ]);

        // التعارض يُقال لا يُبتلع: حصة لم تُولَّد يجب أن يعرف سببها
        if ($result['conflicts'] !== []) {
            return back()->with('status', $message)->withErrors([
                'schedule' => __('وتُخطّيت :count حصة لتعارض: :first', [
                    'count' => count($result['conflicts']),
                    'first' => $result['conflicts'][0]['date'].' — '.$result['conflicts'][0]['reason'],
                ]),
            ]);
        }

        return back()->with('status', $message);
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
