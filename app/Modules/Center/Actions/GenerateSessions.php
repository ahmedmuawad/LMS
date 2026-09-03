<?php

declare(strict_types=1);

namespace App\Modules\Center\Actions;

use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Holiday;
use App\Modules\Center\Models\Schedule;
use App\Modules\Center\Models\Session;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * توليد حصص المجموعة من جدولها المتكرر.
 *
 * العطلات تُتخطّى ولا تُولَّد فيها حصة؛ والحصة القائمة لا تُستبدل،
 * فإعادة التوليد بعد تعديل الجدول لا تمحو حضوراً سُجّل.
 */
final class GenerateSessions
{
    /** @return array{created:int, skipped:int, holidays:int} */
    public function handle(Group $group, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= $group->start_date ?? now()->startOfDay();
        $to ??= $group->end_date ?? $from->copy()->addWeeks(12);

        $schedules = $group->schedules()->get();

        if ($schedules->isEmpty()) {
            return ['created' => 0, 'skipped' => 0, 'holidays' => 0];
        }

        $created = 0;
        $skipped = 0;
        $holidays = 0;

        DB::transaction(function () use ($group, $schedules, $from, $to, &$created, &$skipped, &$holidays): void {
            for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
                foreach ($schedules as $schedule) {
                    if (! $this->appliesOn($schedule, $date)) {
                        continue;
                    }

                    if (Holiday::covering($date, $group->branch_id) !== null) {
                        $holidays++;

                        continue;
                    }

                    $exists = Session::where('group_id', $group->getKey())
                        ->whereDate('date', $date)
                        ->where('starts_at', $schedule->starts_at)
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }

                    Session::create([
                        'group_id' => $group->getKey(),
                        'room_id' => $schedule->room_id,
                        'teacher_id' => $group->teacher_id,
                        'schedule_id' => $schedule->getKey(),
                        'date' => $date->toDateString(),
                        'starts_at' => $schedule->starts_at,
                        'ends_at' => $schedule->ends_at,
                        'status' => 'scheduled',
                    ]);

                    $created++;
                }
            }

            $group->forceFill(['sessions_count' => $group->sessions()->count()])->save();
        });

        return ['created' => $created, 'skipped' => $skipped, 'holidays' => $holidays];
    }

    /** الأحد = 0 عندنا، وCarbon يوافقه. */
    private function appliesOn(Schedule $schedule, Carbon $date): bool
    {
        if ((int) $schedule->weekday !== (int) $date->dayOfWeek) {
            return false;
        }

        if ($schedule->effective_from !== null && $date->lt($schedule->effective_from)) {
            return false;
        }

        return ! ($schedule->effective_to !== null && $date->gt($schedule->effective_to));
    }
}
