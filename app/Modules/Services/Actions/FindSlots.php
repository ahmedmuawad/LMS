<?php

declare(strict_types=1);

namespace App\Modules\Services\Actions;

use App\Modules\Services\Models\Availability;
use App\Modules\Services\Models\AvailabilityException;
use App\Modules\Services\Models\Booking;
use App\Modules\Services\Models\Service;
use App\Modules\Services\Models\ServiceProvider;
use Illuminate\Support\Carbon;

/**
 * المواعيد المتاحة = ساعات العمل − ما هو محجوز − الاستثناءات.
 *
 * لا نعرض موعداً لا يمكن حجزه: عرض موعد ثم رفضه عند الدفع هو
 * أسوأ ما يمكن أن يحدث في صفحة حجز.
 */
final class FindSlots
{
    /** @var array<string, int> عدد الحجوزات لكل مقدّم·يوم·ساعة */
    private array $taken = [];

    /** @return array<string, list<array{starts_at:string, ends_at:string, provider_id:int}>> */
    public function handle(Service $service, ?Carbon $from = null, int $days = 14, ?int $providerId = null): array
    {
        $from ??= now();
        $earliest = $service->earliestBookableAt();
        $providers = $service->providers()->where('is_active', true)
            ->when($providerId !== null, fn ($q) => $q->whereKey($providerId))
            ->with(['availability', 'exceptions'])
            ->get();

        // المحجوز يُقرأ استعلاماً واحداً للمدى كلّه: استعلام لكل موعد
        // يعني مئات الاستعلامات في صفحة حجز واحدة.
        $this->loadTaken($providers->modelKeys(), $from, $days);

        $calendar = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $from->copy()->addDays($offset)->startOfDay();
            $slots = [];

            foreach ($providers as $provider) {
                foreach ($this->windowsFor($provider, $date) as $window) {
                    $slots = [...$slots, ...$this->sliceWindow($service, $provider, $date, $window, $earliest)];
                }
            }

            if ($slots !== []) {
                usort($slots, fn (array $a, array $b): int => $a['starts_at'] <=> $b['starts_at']);
                $calendar[$date->toDateString()] = $slots;
            }
        }

        return $calendar;
    }

    /**
     * نوافذ العمل في يوم بعينه.
     * الاستثناء يغلب القالب: إجازة تُلغي اليوم، وساعة إضافية تُضاف.
     *
     * @return list<array{0:string, 1:string}>
     */
    private function windowsFor(ServiceProvider $provider, Carbon $date): array
    {
        $exceptions = $provider->exceptions->filter(
            fn (AvailabilityException $e): bool => $e->date->isSameDay($date),
        );

        if ($exceptions->contains(fn (AvailabilityException $e): bool => ! $e->is_available && $e->starts_at === null)) {
            return [];   // إجازة يوم كامل
        }

        $extra = $exceptions
            ->filter(fn (AvailabilityException $e): bool => $e->is_available && $e->starts_at !== null)
            ->map(fn (AvailabilityException $e): array => [(string) $e->starts_at, (string) $e->ends_at])
            ->all();

        $regular = $provider->availability
            ->filter(fn (Availability $a): bool => (int) $a->weekday === (int) $date->dayOfWeek)
            ->map(fn (Availability $a): array => [(string) $a->starts_at, (string) $a->ends_at])
            ->all();

        return [...$regular, ...$extra];
    }

    /**
     * @param  array{0:string, 1:string}  $window
     * @return list<array{starts_at:string, ends_at:string, provider_id:int}>
     */
    private function sliceWindow(Service $service, ServiceProvider $provider, Carbon $date, array $window, Carbon $earliest): array
    {
        $step = (int) $service->duration_minutes + (int) $service->buffer_minutes;

        if ($step <= 0) {
            return [];
        }

        $cursor = $date->copy()->setTimeFromTimeString($window[0]);
        $closes = $date->copy()->setTimeFromTimeString($window[1]);
        $slots = [];

        while ($cursor->copy()->addMinutes((int) $service->duration_minutes)->lte($closes)) {
            $ends = $cursor->copy()->addMinutes((int) $service->duration_minutes);

            if ($cursor->gte($earliest) && $this->isFree($service, $provider, $cursor, $ends)) {
                $slots[] = [
                    'starts_at' => $cursor->format('H:i'),
                    'ends_at' => $ends->format('H:i'),
                    'provider_id' => (int) $provider->getKey(),
                ];
            }

            $cursor->addMinutes($step);
        }

        return $slots;
    }

    /** @param  list<int|string>  $providerIds */
    private function loadTaken(array $providerIds, Carbon $from, int $days): void
    {
        $this->taken = [];

        if ($providerIds === []) {
            return;
        }

        $bookings = Booking::blocking()
            ->whereIn('provider_id', $providerIds)
            // نصّياً «…-٠٥ ٠٠:٠٠:٠٠» أكبر من «…-٠٥»، فيسقط آخر يوم من نافذة البحث
            ->whereDate('date', '>=', $from->copy()->startOfDay()->toDateString())
            ->whereDate('date', '<=', $from->copy()->addDays($days)->toDateString())
            ->get(['provider_id', 'date', 'starts_at', 'ends_at']);

        foreach ($bookings as $booking) {
            $this->taken[] = [
                'provider_id' => (int) $booking->provider_id,
                'date' => $booking->date?->toDateString(),
                'starts_at' => (string) $booking->starts_at,
                'ends_at' => (string) $booking->ends_at,
            ];
        }
    }

    private function isFree(Service $service, ServiceProvider $provider, Carbon $start, Carbon $end): bool
    {
        $date = $start->toDateString();
        $startTime = $start->format('H:i:s');
        $endTime = $end->format('H:i:s');
        $overlapping = 0;

        foreach ($this->taken as $booking) {
            if ($booking['provider_id'] !== (int) $provider->getKey() || $booking['date'] !== $date) {
                continue;
            }

            // تداخل حقيقي: يبدأ قبل نهايتنا وينتهي بعد بدايتنا
            if ($booking['starts_at'] < $endTime && $booking['ends_at'] > $startTime) {
                $overlapping++;
            }
        }

        return $overlapping < max(1, (int) $service->max_per_slot);
    }
}
