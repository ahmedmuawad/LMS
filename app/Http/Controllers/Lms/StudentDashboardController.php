<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Modules\Community\Models\Discussion;
use App\Modules\Gamification\Models\PointEntry;
use App\Modules\Lms\Models\Certificate;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Services\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * لوحة الطالب — أول ما يراه بعد الدخول.
 *
 * كانت شاشاته اثنتي عشرة متفرّقة بلا مدخل يجمعها، فيدخل الطالب
 * ولا يعرف أين كان. هذه الشاشة تجيب سؤالاً واحداً: **ما التالي؟**
 * فالبطاقة الأولى هي «تابع من حيث وقفت» لا رقمٌ في مربّع.
 */
final class StudentDashboardController
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $id = $user->getKey();

        $enrollments = Enrollment::where('user_id', $id)
            ->with(['course.instructor.user', 'course.category'])
            ->latest('updated_at')
            ->get();

        $live = $enrollments->filter(fn (Enrollment $e): bool => $e->hasAccess());

        return view('lms.student-dashboard', [
            // ما لم يكتمل بعد، أقربه إلى الإنجاز أولاً: الدفعة الأخيرة أسهل من البداية
            'continue' => $live
                ->filter(fn (Enrollment $e): bool => $e->progress_percent > 0 && $e->progress_percent < 100)
                ->sortByDesc('progress_percent')
                ->take(3)
                ->values(),

            'notStarted' => $live->filter(fn (Enrollment $e): bool => (int) $e->progress_percent === 0)->take(3)->values(),

            'stats' => [
                'active' => $live->count(),
                'completed' => $enrollments->filter(fn (Enrollment $e): bool => $e->progress_percent >= 100)->count(),
                'certificates' => Certificate::where('user_id', $id)->count(),
                'points' => $this->points($id),
            ],

            // ما ينتظر الطالب فعلاً: حجز قادم، وردٌّ على سؤاله
            'upcoming' => $this->upcoming($id),
            'answered' => $this->answered($id),
            'expiring' => $live->filter(fn (Enrollment $e): bool => ($e->daysLeft() ?? 999) <= 14)->values(),
        ]);
    }

    /** نقاط التحفيز — والموديول قد يكون مطفأً فلا نسأل جدولاً غائباً. */
    private function points(int|string $userId): ?int
    {
        if (! module_enabled('gamification') || ! Schema::hasTable('point_entries')) {
            return null;
        }

        return (int) PointEntry::where('user_id', $userId)->sum('points');
    }

    /** @return Collection<int, Booking> */
    private function upcoming(int|string $userId)
    {
        if (! module_enabled('bookings') || ! Schema::hasTable('bookings')) {
            return collect();
        }

        return Booking::where('user_id', $userId)
            ->whereIn('status', ['confirmed', 'pending'])
            ->whereDate('date', '>=', now()->toDateString())
            ->with('service')
            ->orderBy('date')->orderBy('starts_at')
            ->limit(3)
            ->get();
    }

    /**
     * أسئلته التي أُجيبت — أهمّ إشعار لا يصل عادةً.
     *
     * @return Collection<int, Discussion>
     */
    private function answered(int|string $userId)
    {
        if (! module_enabled('community') || ! Schema::hasTable('discussions')) {
            return collect();
        }

        return Discussion::where('user_id', $userId)
            ->where('replies_count', '>', 0)
            ->with('course')
            ->latest('updated_at')
            ->limit(3)
            ->get();
    }
}
