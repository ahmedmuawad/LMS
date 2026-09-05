<?php

declare(strict_types=1);

namespace App\Core\Reports;

use App\Core\Support\Money;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Payment;
use App\Modules\Commerce\Models\Refund;
use App\Modules\Community\Models\Discussion;
use App\Modules\Growth\Models\Affiliate;
use App\Modules\Growth\Models\Campaign;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\QuizAttempt;
use App\Modules\Services\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * التقارير.
 *
 * كل رقم هنا يُجمَع من مصدره لا من عدّاد: العدّاد ينحرف بأول خطأ
 * ولا يُصحَّح، وتقرير مالي منحرف أسوأ من غياب التقرير.
 */
final class ReportBuilder
{
    /** @return array<string, mixed> */
    public function learning(Carbon $from, Carbon $to): array
    {
        $enrollments = Enrollment::whereBetween('created_at', [$from, $to]);

        $completed = Enrollment::whereBetween('completed_at', [$from, $to])
            ->where('status', 'completed');

        $active = Enrollment::where('status', 'active')->where('progress_percent', '>', 0);

        return [
            'enrolled' => $enrollments->count(),
            'completed' => $completed->count(),
            'completion_rate' => $this->rate($completed->count(), Enrollment::where('created_at', '<=', $to)->count()),
            'avg_progress' => round((float) $active->avg('progress_percent'), 1),
            'certificates' => DB::table('certificates')->whereBetween('issued_at', [$from, $to])->count(),

            'quiz_pass_rate' => $this->rate(
                QuizAttempt::whereBetween('submitted_at', [$from, $to])->where('passed', true)->count(),
                QuizAttempt::whereBetween('submitted_at', [$from, $to])->whereNotNull('submitted_at')->count(),
            ),

            'unanswered_questions' => Discussion::unanswered()->count(),

            'top_courses' => Course::withCount(['enrollments' => fn ($q) => $q->whereBetween('enrollments.created_at', [$from, $to])])
                ->orderByDesc('enrollments_count')
                ->limit(8)
                ->get(['id', 'title', 'slug', 'rating_avg']),

            'daily' => $this->daily(Enrollment::query(), 'created_at', $from, $to),
        ];
    }

    /** @return array<string, mixed> */
    public function financial(Carbon $from, Carbon $to): array
    {
        $currency = (string) (tenant('currency') ?? config('money.default', 'EGP'));

        $captured = (int) Payment::where('status', 'captured')
            ->whereBetween('paid_at', [$from, $to])->sum('amount_minor');

        /*
         | تحصيل الأقساط إيرادٌ أيضاً.
         |
         | للمنصة نظاما مال: مدفوعات المتجر (بيع الكورسات) وتحصيل
         | أقساط الطلبة. وكان التقرير يقرأ الأول وحده — فمدرّسٌ يدير
         | مجموعات ويحصّل نقداً كل يوم يقرأ «الإيراد ٠٫٠٠» ويظنّ
         | النظام لا يحسب ما قبضه.
         |
         | والجمع لا يُكرّر: القسط لا يمرّ بجدول مدفوعات المتجر أبداً.
         */
        $fees = $this->centerCollected($from, $to);
        $captured += $fees;

        $refunded = (int) Refund::where('status', 'processed')
            ->whereBetween('handled_at', [$from, $to])->sum('amount_minor');

        $orders = Order::whereBetween('created_at', [$from, $to]);
        $paid = Order::whereIn('status', ['paid', 'processing', 'completed'])
            ->whereBetween('created_at', [$from, $to]);

        $paidCount = $paid->count();

        return [
            'currency' => $currency,
            'revenue' => Money::fromMinor($captured, $currency),
            'fees_collected' => Money::fromMinor($fees, $currency),
            'refunds' => Money::fromMinor($refunded, $currency),
            'net' => Money::fromMinor(max(0, $captured - $refunded), $currency),
            'orders' => $orders->count(),
            'paid_orders' => $paidCount,
            'conversion' => $this->rate($paidCount, $orders->count()),
            'average_order' => Money::fromMinor($paidCount === 0 ? 0 : (int) round($captured / $paidCount), $currency),

            'by_gateway' => Payment::where('status', 'captured')
                ->whereBetween('paid_at', [$from, $to])
                ->selectRaw('gateway, count(*) as orders, sum(amount_minor) as total')
                ->groupBy('gateway')->orderByDesc('total')->get()
                ->map(fn ($row): array => [
                    'gateway' => (string) $row->gateway,
                    'orders' => (int) $row->orders,
                    'total' => Money::fromMinor((int) $row->total, $currency),
                ])->all(),

            'bookings' => Booking::whereBetween('created_at', [$from, $to])->count(),

            'daily' => $this->dailySum(
                Payment::where('status', 'captured'),
                'paid_at',
                'amount_minor',
                $from,
                $to,
            ),
        ];
    }

    /** @return array<string, mixed> */
    public function marketing(Carbon $from, Carbon $to): array
    {
        $currency = (string) (tenant('currency') ?? config('money.default', 'EGP'));

        return [
            'currency' => $currency,

            'affiliates' => Affiliate::active()->count(),
            'affiliate_clicks' => (int) DB::table('affiliate_clicks')
                ->whereBetween('created_at', [$from, $to])->count(),
            'affiliate_sales' => (int) DB::table('affiliate_conversions')
                ->whereBetween('created_at', [$from, $to])
                ->whereIn('status', ['approved', 'paid'])->count(),
            'affiliate_cost' => Money::fromMinor(
                (int) DB::table('affiliate_conversions')
                    ->whereBetween('created_at', [$from, $to])
                    ->whereIn('status', ['approved', 'paid'])
                    ->sum('commission_minor'),
                $currency,
            ),

            'top_affiliates' => Affiliate::with('user')
                ->orderByDesc('earned_minor')->limit(5)->get(),

            'campaigns' => Campaign::withCount([
                'enrolments as entered' => fn ($q) => $q->whereBetween('campaign_enrolments.created_at', [$from, $to]),
                'enrolments as converted' => fn ($q) => $q
                    ->whereBetween('campaign_enrolments.created_at', [$from, $to])
                    ->where('campaign_enrolments.status', 'converted'),
            ])->get(),

            'new_users' => DB::table('users')->whereBetween('created_at', [$from, $to])->count(),
        ];
    }

    private function rate(int $part, int $whole): float
    {
        return $whole === 0 ? 0.0 : round($part / $whole * 100, 1);
    }

    /**
     * سلسلة يومية كاملة بلا فجوات.
     *
     * الأيام الصفرية تُملأ عمداً: رسم يقفز فوق يوم بلا مبيعات يكذب
     * بصرياً — يبدو الخطّ متّصلاً وقد انقطع البيع يوماً كاملاً.
     *
     * @return array<string, int>
     */
    private function daily(mixed $query, string $column, Carbon $from, Carbon $to): array
    {
        $rows = $query->whereBetween($column, [$from, $to])
            ->selectRaw("date({$column}) as day, count(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        return $this->fill($rows->all(), $from, $to);
    }

    /** @return array<string, int> */
    private function dailySum(mixed $query, string $column, string $sum, Carbon $from, Carbon $to): array
    {
        $rows = $query->whereBetween($column, [$from, $to])
            ->selectRaw("date({$column}) as day, sum({$sum}) as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        return $this->fill($rows->all(), $from, $to);
    }

    /**
     * @param  array<string, mixed>  $rows
     * @return array<string, int>
     */
    private function fill(array $rows, Carbon $from, Carbon $to): array
    {
        $series = [];
        $cursor = $from->copy()->startOfDay();

        // مدى طويل يُختزل: ٣٦٥ عموداً في رسم عرضه ٣٠٠ بكسل لا يُقرأ
        while ($cursor->lte($to) && count($series) < 120) {
            $key = $cursor->toDateString();
            $series[$key] = (int) ($rows[$key] ?? 0);
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * ما حُصِّل من أقساط الطلبة في المدة.
     *
     * صفرٌ إن كان موديول الأقساط مطفأً أو جدوله غير موجود: التقرير
     * يخدم كل الأنماط، وأكاديميةٌ أونلاين صِرفة لا مركز لها.
     */
    private function centerCollected(Carbon $from, Carbon $to): int
    {
        if (! module_enabled('center-finance') || ! Schema::hasTable('center_payments')) {
            return 0;
        }

        return (int) DB::table('center_payments')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount_minor');
    }
}
