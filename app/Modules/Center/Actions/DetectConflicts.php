<?php

declare(strict_types=1);

namespace App\Modules\Center\Actions;

use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Holiday;
use App\Modules\Center\Models\Room;
use App\Modules\Center\Models\Session;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * وثيقة 16.3 — كشف التعارض قبل الحفظ.
 *
 * أكثر ما يزعج إدارة السنتر: حصتان في قاعة واحدة، أو مدرّس في
 * مكانين، أو موعد في يوم عطلة. الفحص يجري قبل الحفظ لا بعده،
 * ويُقترح أقرب بديل — فالإدارة لا تحتاج «ممنوع» بل تحتاج «متى إذن».
 */
final class DetectConflicts
{
    /**
     * @param  array{group_id:int, room_id?:?int, teacher_id?:?int, date:string, starts_at:string, ends_at:string, ignore_session_id?:?int}  $slot
     * @return list<array{code:string, message:string, session_id?:int}>
     */
    public function handle(array $slot): array
    {
        $conflicts = [];

        $date = Carbon::parse($slot['date']);
        $from = $this->time($slot['starts_at']);
        $to = $this->time($slot['ends_at']);

        if ($to <= $from) {
            $conflicts[] = ['code' => 'time', 'message' => __('وقت النهاية يجب أن يكون بعد وقت البداية.')];

            return $conflicts;
        }

        $group = Group::with('branch')->find($slot['group_id']);

        // 1) عطلة رسمية
        $holiday = Holiday::covering($date, $group?->branch_id);

        if ($holiday !== null) {
            $conflicts[] = [
                'code' => 'holiday',
                'message' => __('هذا اليوم عطلة: :name.', ['name' => $holiday->name]),
            ];
        }

        $overlapping = $this->overlapping($slot, $date, $from, $to);

        // 2) القاعة مشغولة
        if (filled($slot['room_id'] ?? null)) {
            $clash = $overlapping->firstWhere('room_id', $slot['room_id']);

            if ($clash !== null) {
                $conflicts[] = [
                    'code' => 'room',
                    'message' => __('القاعة محجوزة لـ«:group» في :time.', [
                        'group' => $clash->group?->name ?? '—',
                        'time' => $clash->timeLabel(),
                    ]),
                    'session_id' => (int) $clash->id,
                ];
            }
        }

        // 3) المدرّس في مكانين
        if (filled($slot['teacher_id'] ?? null)) {
            $clash = $overlapping->firstWhere('teacher_id', $slot['teacher_id']);

            if ($clash !== null) {
                $conflicts[] = [
                    'code' => 'teacher',
                    'message' => __('المدرّس عنده حصة «:group» في :time.', [
                        'group' => $clash->group?->name ?? '—',
                        'time' => $clash->timeLabel(),
                    ]),
                    'session_id' => (int) $clash->id,
                ];
            }
        }

        // 4) المجموعة عندها حصة أخرى
        $clash = $overlapping->firstWhere('group_id', $slot['group_id']);

        if ($clash !== null) {
            $conflicts[] = [
                'code' => 'group',
                'message' => __('للمجموعة حصة أخرى في :time.', ['time' => $clash->timeLabel()]),
                'session_id' => (int) $clash->id,
            ];
        }

        // 5) عدد الطلاب يفوق سعة القاعة
        if (filled($slot['room_id'] ?? null) && $group !== null) {
            $room = Room::find($slot['room_id']);

            if ($room !== null && $group->enrolled_count > $room->capacity) {
                $conflicts[] = [
                    'code' => 'capacity',
                    'message' => __('عدد طلاب المجموعة (:students) يفوق سعة القاعة (:capacity).', [
                        'students' => $group->enrolled_count,
                        'capacity' => $room->capacity,
                    ]),
                ];
            }
        }

        return $conflicts;
    }

    /**
     * أقرب موعد بديل خالٍ في نفس اليوم — «ممنوع» وحدها لا تكفي.
     *
     * @param  array<string, mixed>  $slot
     */
    public function suggestAlternative(array $slot, int $stepMinutes = 30, int $maxTries = 16): ?array
    {
        $start = Carbon::parse($slot['date'].' '.$slot['starts_at']);
        $end = Carbon::parse($slot['date'].' '.$slot['ends_at']);

        // القيمة المطلقة: diffInMinutes موجَّه في Carbon الحديث،
        // وسالبٌ هنا يقلب الموعد ويجعل البحث لا ينتهي إلى شيء.
        $duration = (int) abs($start->diffInMinutes($end));

        for ($try = 1; $try <= $maxTries; $try++) {
            $candidateStart = $start->copy()->addMinutes($stepMinutes * $try);
            $candidateEnd = $candidateStart->copy()->addMinutes((int) $duration);

            // لا نقترح موعداً بعد منتصف الليل
            if ($candidateEnd->toDateString() !== $start->toDateString()) {
                return null;
            }

            $candidate = [
                ...$slot,
                'starts_at' => $candidateStart->format('H:i'),
                'ends_at' => $candidateEnd->format('H:i'),
            ];

            // العطلة لا تُحلّ بتأخير ساعة، فنتجاهل اقتراح اليوم نفسه
            $remaining = array_filter(
                $this->handle($candidate),
                fn (array $conflict): bool => $conflict['code'] !== 'holiday',
            );

            if ($remaining === []) {
                return ['starts_at' => $candidate['starts_at'], 'ends_at' => $candidate['ends_at']];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $slot
     * @return Collection<int, Session>
     */
    private function overlapping(array $slot, Carbon $date, string $from, string $to): Collection
    {
        return Session::with('group')
            ->whereDate('date', $date)
            ->whereIn('status', ['scheduled', 'running'])
            ->when(filled($slot['ignore_session_id'] ?? null), fn ($q) => $q->whereKeyNot($slot['ignore_session_id']))
            // تداخل حقيقي: تبدأ قبل نهايتنا وتنتهي بعد بدايتنا
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get();
    }

    private function time(string $value): string
    {
        return substr(Carbon::parse($value)->format('H:i:s'), 0, 8);
    }
}
