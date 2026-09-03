<?php

declare(strict_types=1);

namespace App\Modules\Services\Actions;

use App\Models\User;
use App\Modules\Services\Models\Booking;
use App\Modules\Services\Models\Service;
use App\Modules\Services\Models\ServiceProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * حجز خدمة.
 *
 * يُعاد فحص التوفّر داخل معاملة بقفل: بين عرض الموعد والضغط عليه
 * دقائق، وقد يكون أحدهم سبقك — والرفض هنا أهون من حجزين في وقت.
 */
final class BookService
{
    /** @param  array<string, mixed>  $input */
    public function handle(Service $service, array $input, ?User $user = null): Booking
    {
        if ($service->status !== 'published') {
            throw new RuntimeException(__('هذه الخدمة غير متاحة الآن.'));
        }

        return DB::transaction(function () use ($service, $input, $user): Booking {
            $provider = $service->type === 'appointment'
                ? $this->lockProvider($input['provider_id'] ?? null, $service)
                : null;

            if ($service->type === 'appointment') {
                $this->assertSlotIsFree($service, $provider, $input);
            }

            $this->assertWithinOpenLimit($user);

            /*
             | التأكيد التلقائي خيار المشترك لا افتراض المنصّة: مركز
             | يراجع كل طلب، ومستشار يريد التقويم يُحجز فوراً.
             */
            $auto = (string) setting('services.confirmation', 'manual') === 'auto';

            $booking = Booking::create([
                'reference' => $this->reference(),
                'service_id' => $service->getKey(),
                'provider_id' => $provider?->getKey(),
                'user_id' => $user?->getKey(),
                'customer_name' => $input['name'] ?? $user?->name,
                'customer_email' => $input['email'] ?? $user?->email,
                'customer_phone' => $input['phone'] ?? null,
                'date' => $input['date'] ?? null,
                'starts_at' => $input['starts_at'] ?? null,
                'ends_at' => $this->endTime($service, $input),
                'timezone' => $input['timezone'] ?? tenant('timezone'),
                'status' => $auto ? 'confirmed' : 'pending',
                'confirmed_at' => $auto ? now() : null,
                'currency' => $service->currency,
                'price_minor' => $service->needsQuote() ? 0 : $service->price_minor,
                'intake' => $input['intake'] ?? null,
                'notes' => $input['notes'] ?? null,
            ]);

            $this->announce($booking, $auto ? 'services.booking_confirmed' : 'services.booking_placed');

            return $booking;
        });
    }

    public function confirm(Booking $booking, ?string $meetingUrl = null): Booking
    {
        $booking->forceFill([
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'meeting_url' => $meetingUrl ?? $booking->meeting_url,
        ])->save();

        $booking->service?->increment('bookings_count');

        $this->announce($booking, 'services.booking_confirmed');

        return $booking;
    }

    public function cancel(Booking $booking, ?string $reason = null): Booking
    {
        if (in_array($booking->status, ['completed', 'cancelled'], true)) {
            throw new RuntimeException(__('لا يمكن إلغاء هذا الحجز.'));
        }

        $booking->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ])->save();

        $this->announce($booking, 'services.booking_cancelled');

        return $booking;
    }

    public function complete(Booking $booking, ?array $deliverables = null): Booking
    {
        $booking->forceFill([
            'status' => 'completed',
            'completed_at' => now(),
            'deliverables' => $deliverables ?? $booking->deliverables,
        ])->save();

        return $booking;
    }

    /**
     * إخبار العميل ومقدّم الخدمة.
     *
     * الحجز بلا إخبار وعدٌ لا يعرف به أحد: العميل ينتظر تأكيداً،
     * والمقدّم لا يعرف أن وقته حُجز حتى يفتح اللوحة صدفةً.
     */
    private function announce(Booking $booking, string $event): void
    {
        $service = $booking->service;
        $start = $booking->startsAtCarbon();

        $data = [
            'service_title' => (string) ($service?->title ?? ''),
            'booking_reference' => (string) $booking->reference,
            'booking_url' => url('/bookings/'.$booking->token),
            'booking_at' => $start?->translatedFormat('l j F Y — H:i') ?? '',
            'meeting_url' => (string) ($booking->meeting_url ?? ''),
            'reason' => (string) ($booking->cancel_reason ?? ''),
            'customer_name' => $booking->customerName(),
            'url' => url('/bookings/'.$booking->token),
        ];

        if ($booking->user !== null) {
            notify($event, $booking->user, $data);
        }

        $provider = $booking->provider?->user;

        if ($provider !== null && $event !== 'services.booking_cancelled') {
            notify('services.booking_for_provider', $provider, $data + [
                'url' => url('/admin/bookings/'.$booking->getKey().'/edit'),
            ]);
        }
    }

    /** حدّ الحجوزات المفتوحة يحمي التقويم من حاجز يحجز كل المواعيد. */
    private function assertWithinOpenLimit(?User $user): void
    {
        $limit = (int) setting('services.max_open_per_user', 0);

        if ($limit <= 0 || $user === null) {
            return;
        }

        $open = Booking::blocking()->where('user_id', $user->getKey())->count();

        if ($open >= $limit) {
            throw new RuntimeException(__('لديك :count حجوزات مفتوحة — أنهِ أحدها أولاً.', ['count' => $open]));
        }
    }

    private function lockProvider(mixed $providerId, Service $service): ServiceProvider
    {
        $provider = ServiceProvider::where('service_id', $service->getKey())
            ->where('is_active', true)
            ->when($providerId !== null, fn ($q) => $q->whereKey($providerId))
            ->lockForUpdate()
            ->first();

        if ($provider === null) {
            throw new RuntimeException(__('لا مقدّم متاح لهذه الخدمة.'));
        }

        return $provider;
    }

    /** @param  array<string, mixed>  $input */
    private function assertSlotIsFree(Service $service, ServiceProvider $provider, array $input): void
    {
        if (blank($input['date'] ?? null) || blank($input['starts_at'] ?? null)) {
            throw new RuntimeException(__('اختر موعداً أولاً.'));
        }

        $start = Carbon::parse($input['date'].' '.$input['starts_at']);

        if ($start->lt($service->earliestBookableAt())) {
            throw new RuntimeException(__('هذا الموعد أقرب من مهلة الحجز (:hours ساعة).', [
                'hours' => $service->lead_hours,
            ]));
        }

        $end = $start->copy()->addMinutes((int) $service->duration_minutes);

        $taken = Booking::blocking()
            ->where('provider_id', $provider->getKey())
            ->whereDate('date', $start->toDateString())
            ->where('starts_at', '<', $end->format('H:i:s'))
            ->where('ends_at', '>', $start->format('H:i:s'))
            ->lockForUpdate()
            ->count();

        if ($taken >= max(1, (int) $service->max_per_slot)) {
            throw new RuntimeException(__('حُجز هذا الموعد للتوّ — اختر موعداً آخر.'));
        }
    }

    /** @param  array<string, mixed>  $input */
    private function endTime(Service $service, array $input): ?string
    {
        if (blank($input['starts_at'] ?? null)) {
            return null;
        }

        return Carbon::parse($input['starts_at'])
            ->addMinutes((int) $service->duration_minutes)
            ->format('H:i:s');
    }

    private function reference(): string
    {
        $prefix = 'BK-'.now()->format('Ym').'-';

        $last = Booking::where('reference', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('reference')
            ->value('reference');

        $sequence = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
