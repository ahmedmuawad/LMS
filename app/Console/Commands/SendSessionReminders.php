<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Notifications\Notifier;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Center\Models\Session;
use App\Modules\Live\LiveRooms;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * تذكير الطلبة قبل حصصهم.
 *
 * الغياب سببه النسيان لا الرفض — والتذكير يحوّل نصف الغياب حضوراً.
 *
 * ## لا يُرسَل تذكيران لحصة
 *
 * الأمر يعمل كل خمس دقائق، والنافذة أوسع من ذلك، فالحصة الواحدة
 * تقع في أكثر من دورة. ولذلك يُختَم في `reminded_at`: من ذُكِّر لا
 * يُذكَّر ثانية. والختم على الحصة لا على الطالب — فحصةٌ ذُكِّر بها
 * نصف طلابها ثم فشلت الدورة تُستأنف بلا تكرار على النصف الأول.
 */
final class SendSessionReminders extends Command
{
    protected $signature = 'center:remind
        {--tenant= : مشترك بعينه بدل الجميع}';

    protected $description = 'يذكّر الطلبة بحصصهم القادمة قبل موعدها';

    public function handle(Notifier $notifier): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q) => $q->where('slug', $this->option('tenant')))
            ->whereIn('status', ['active', 'trialing'])
            ->get();

        $sent = 0;
        $sessions = 0;

        foreach ($tenants as $tenant) {
            try {
                [$s, $n] = $this->runFor($tenant, $notifier);
                $sessions += $s;
                $sent += $n;
            } catch (Throwable $e) {
                // مشترك متعثّر لا يحرم البقية من تذكيرهم
                $this->error("[{$tenant->slug}] ".mb_substr($e->getMessage(), 0, 160));
            }
        }

        $this->info("حصص: {$sessions} · تذكيرات: {$sent}");

        return self::SUCCESS;
    }

    /** @return array{0:int, 1:int} */
    private function runFor(Tenant $tenant, Notifier $notifier): array
    {
        return $tenant->run(function () use ($notifier): array {
            if (! module_enabled('center') || ! (bool) setting('live.remind', true)) {
                return [0, 0];
            }

            if (! Schema::hasColumn('center_sessions', 'reminded_at')) {
                return [0, 0];
            }

            $before = (int) setting('live.remind_before', 60);
            $rooms = app(LiveRooms::class);

            /*
             | النافذة: ما يبدأ خلال المهلة ولم يبدأ بعد.
             |
             | والحدّ الأدنى صفر لا سالب: حصةٌ فاتت لا يُذكَّر بها،
             | فالتذكير بما مضى إزعاجٌ لا خدمة.
             */
            $sessions = Session::with(['group.subject', 'group.teacher'])
                ->whereNull('reminded_at')
                ->where('status', 'scheduled')
                ->whereDate('date', '>=', now()->toDateString())
                ->whereDate('date', '<=', now()->addDay()->toDateString())
                ->get()
                ->filter(function (Session $session) use ($before): bool {
                    $start = $session->date?->copy()->setTimeFromTimeString((string) $session->starts_at);

                    return $start !== null
                        && $start->isFuture()
                        && $start->diffInMinutes(now()) <= $before;
                });

            $sent = 0;

            foreach ($sessions as $session) {
                $sent += $this->remind($session, $notifier, $rooms);

                $session->forceFill(['reminded_at' => now()])->save();
            }

            return [$sessions->count(), $sent];
        });
    }

    private function remind(Session $session, Notifier $notifier, LiveRooms $rooms): int
    {
        $userIds = DB::table('center_enrollments as e')
            ->join('center_students as s', 's.id', '=', 'e.student_id')
            ->where('e.group_id', $session->group_id)
            ->where('e.status', 'active')
            ->whereNotNull('s.user_id')
            ->pluck('s.user_id');

        if ($userIds->isEmpty()) {
            return 0;
        }

        $students = User::whereIn('id', $userIds)->get();
        $start = $session->date?->copy()->setTimeFromTimeString((string) $session->starts_at);
        $room = $rooms->forSession($session);

        $notifier->send('center.session_reminder', $students, [
            'group_name' => (string) ($session->group?->name ?? ''),
            'subject_name' => (string) ($session->group?->subject?->name ?? ''),
            'teacher_name' => (string) ($session->group?->teacher?->name ?? ''),
            'session_at' => $start?->translatedFormat('l j F · g:i a') ?? '',
            'starts_in' => $start?->diffForHumans() ?? '',
            // رابط لوحته لا رابط الغرفة: الغرفة تُفتح في نافذتها لا في التذكير
            'join_url' => url('/my-classes'),
        ]);

        return $students->count();
    }
}
