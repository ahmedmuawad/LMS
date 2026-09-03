<?php

declare(strict_types=1);

namespace App\Modules\Growth\Actions;

use App\Modules\Commerce\Models\Cart;
use App\Modules\Growth\Models\Campaign;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Services\Models\Booking;
use Illuminate\Support\Carbon;

/**
 * رصد ما يُدخل الناس في الحملات.
 *
 * الرصد بالاستعلام لا بالحدث: السلة تُترك بالصمت لا بفعل، والخمول
 * غياب لا حدث — وما لا يقع لا يُطلق حدثاً ينتظره أحد.
 */
final class DetectTriggers
{
    public function __construct(private readonly RunCampaigns $campaigns) {}

    /** @return array<string, int> عدد من دخل كل مُطلِق */
    public function handle(): array
    {
        return [
            'cart_abandoned' => $this->abandonedCarts(),
            'course_idle' => $this->idleLearners(),
            'access_expiring' => $this->expiringAccess(),
            'booking_upcoming' => $this->upcomingBookings(),
        ];
    }

    /**
     * السلة المتروكة: فيها شيء، ولم تُلمس منذ مدة، ولم تصر طلباً.
     *
     * أعلى عائد في التجارة كلّها، وأسهل ما يُنسى بناؤه.
     */
    private function abandonedCarts(): int
    {
        $campaigns = Campaign::active()->where('trigger', 'cart_abandoned')->with('steps')->get();

        if ($campaigns->isEmpty()) {
            return 0;
        }

        $after = (int) setting('growth.cart_abandoned_after_minutes', 60);
        $window = now()->subMinutes($after);

        $carts = Cart::whereNotNull('user_id')
            ->where('updated_at', '<=', $window)
            // نافذة يومين: سلة عمرها أسبوع لم تعد «متروكة» بل منسيّة
            ->where('updated_at', '>=', now()->subDays(2))
            ->whereHas('items')
            ->with('user')
            ->limit(500)
            ->get();

        $entered = 0;

        foreach ($carts as $cart) {
            if ($cart->user === null) {
                continue;
            }

            foreach ($campaigns as $campaign) {
                $entered += $this->campaigns->enrol($campaign, $cart->user, $cart) !== null ? 1 : 0;
            }
        }

        return $entered;
    }

    /** الخمول: بدأ ولم يُكمل ولم يفتح شيئاً منذ مدة. */
    private function idleLearners(): int
    {
        $campaigns = Campaign::active()->where('trigger', 'course_idle')->with('steps')->get();

        if ($campaigns->isEmpty()) {
            return 0;
        }

        $days = (int) setting('growth.idle_after_days', 7);

        $enrollments = Enrollment::where('status', 'active')
            ->where('progress_percent', '>', 0)
            ->where('progress_percent', '<', 100)
            ->where('updated_at', '<=', now()->subDays($days))
            ->where('updated_at', '>=', now()->subDays($days * 4))
            ->with(['user', 'course'])
            ->limit(500)
            ->get();

        $entered = 0;

        foreach ($enrollments as $enrollment) {
            if ($enrollment->user === null) {
                continue;
            }

            foreach ($campaigns as $campaign) {
                $entered += $this->campaigns->enrol($campaign, $enrollment->user, $enrollment) !== null ? 1 : 0;
            }
        }

        return $entered;
    }

    /** قرب انتهاء الوصول: فرصة تجديد، وإنذار عادل للطالب. */
    private function expiringAccess(): int
    {
        $campaigns = Campaign::active()->where('trigger', 'access_expiring')->with('steps')->get();

        if ($campaigns->isEmpty()) {
            return 0;
        }

        $days = (int) setting('growth.expiring_before_days', 7);

        $enrollments = Enrollment::whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)])
            ->where('status', 'active')
            ->with(['user', 'course'])
            ->limit(500)
            ->get();

        $entered = 0;

        foreach ($enrollments as $enrollment) {
            if ($enrollment->user === null) {
                continue;
            }

            foreach ($campaigns as $campaign) {
                $entered += $this->campaigns->enrol($campaign, $enrollment->user, $enrollment) !== null ? 1 : 0;
            }
        }

        return $entered;
    }

    /**
     * التذكير بالموعد.
     *
     * لا يمرّ عبر الحملات: التذكير رسالة واحدة في وقت محسوب، وبناء
     * تسلسل لها تعقيد بلا فائدة.
     */
    private function upcomingBookings(): int
    {
        if (! (bool) setting('services.send_reminders', true)) {
            return 0;
        }

        $hours = max(1, (int) setting('services.reminder_hours', 24));
        $from = now()->addHours($hours);
        $to = $from->copy()->addHour();

        $bookings = Booking::blocking()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->with(['service', 'user'])
            ->limit(500)
            ->get()
            ->filter(function (Booking $booking) use ($from, $to): bool {
                $start = $booking->startsAtCarbon();

                return $start !== null && $start->betweenIncluded($from, $to);
            });

        $sent = 0;

        foreach ($bookings as $booking) {
            if ($booking->user === null) {
                continue;
            }

            notify('services.booking_reminder', $booking->user, [
                'service_title' => (string) ($booking->service?->title ?? ''),
                'booking_at' => $booking->startsAtCarbon()?->translatedFormat('l j F — H:i') ?? '',
                'meeting_url' => (string) ($booking->meeting_url ?? ''),
                'hours_left' => (string) $hours,
                'url' => url('/bookings/'.$booking->token),
            ]);

            $sent++;
        }

        return $sent;
    }

    /** بداية اليوم بتوقيت المشترك لا بتوقيت الخادم. */
    public function tenantToday(): Carbon
    {
        return now()->setTimezone((string) (tenant('timezone') ?: config('app.timezone')))->startOfDay();
    }
}
