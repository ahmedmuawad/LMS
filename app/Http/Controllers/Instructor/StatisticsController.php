<?php

declare(strict_types=1);

namespace App\Http\Controllers\Instructor;

use App\Core\Access\Scope;
use App\Core\Support\Money;
use App\Models\User;
use App\Modules\Commerce\Models\InstructorEarning;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseReview;
use App\Modules\Lms\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * إحصاءات المدرّس — كورساً كورساً.
 *
 * الرقم المجمَّع يخفي القرار: متوسّط إتمام 40% لا يقول أي كورس
 * يُسرّب طلابه. الجدول هنا مرتّب بأضعف إتمام أولاً لأن ذاك هو
 * الكورس الذي يحتاج عملاً.
 */
final class StatisticsController
{
    private const DAYS = 30;

    public function __construct(private readonly Scope $scope) {}

    public function __invoke(Request $request): View
    {
        $user = $this->user($request);
        $courseIds = $this->scope->courseIdsFor($user);
        $scoped = $user !== null && $user->isScoped();
        $ids = $scoped ? ($courseIds ?: [0]) : (Course::pluck('id')->all() ?: [0]);
        $currency = (string) (tenant('currency') ?? 'EGP');

        $enrolled = $this->countBy(Enrollment::whereIn('course_id', $ids), 'course_id');
        $completed = $this->countBy(
            Enrollment::whereIn('course_id', $ids)->where('status', 'completed'),
            'course_id',
        );
        $ratings = Enrollment::query()->getConnection()->table('course_reviews')
            ->whereIn('course_id', $ids)->where('status', 'approved')
            ->selectRaw('course_id, avg(rating) as average, count(*) as total')
            ->groupBy('course_id')->get()->keyBy('course_id');

        $rows = Course::whereIn('id', $ids)->get()->map(function (Course $course) use ($enrolled, $completed, $ratings): array {
            $total = (int) ($enrolled[$course->getKey()] ?? 0);
            $done = (int) ($completed[$course->getKey()] ?? 0);
            $rating = $ratings[$course->getKey()] ?? null;

            return [
                'course' => $course,
                'enrolled' => $total,
                'completed' => $done,
                'rate' => $total === 0 ? null : round($done / $total * 100),
                'rating' => $rating === null ? null : round((float) $rating->average, 1),
                'reviews' => (int) ($rating->total ?? 0),
            ];
        })
            // الأضعف إتماماً أولاً، ومن لا طلاب له في الذيل لا في الصدارة
            ->sortBy(fn (array $row): float => $row['rate'] ?? 999)
            ->values();

        return $this->render($rows, $ids, $user, $currency);
    }

    /** @param  Collection<int, array<string, mixed>>  $rows */
    private function render($rows, array $ids, ?User $user, string $currency): View
    {
        return view('instructor.statistics', [
            'rows' => $rows,
            'enrollmentsByDay' => $this->enrollmentsByDay($ids),
            'revenue' => Money::fromMinor(
                (int) $this->scope->byInstructor(InstructorEarning::query(), $user)
                    ->whereIn('status', ['available', 'paid'])
                    ->where('created_at', '>=', now()->subDays(self::DAYS))
                    ->sum('amount_minor'),
                $currency,
            ),
            'sales' => (int) $this->scope->byInstructor(OrderItem::query(), $user)
                ->where('created_at', '>=', now()->subDays(self::DAYS))
                ->count(),
            'newReviews' => CourseReview::whereIn('course_id', $ids)
                ->where('created_at', '>=', now()->subDays(self::DAYS))->count(),
            'days' => self::DAYS,
        ]);
    }

    /** @return array<int,int> */
    private function countBy($query, string $column): array
    {
        return $query->selectRaw($column.', count(*) as total')
            ->groupBy($column)->pluck('total', $column)
            ->map(fn ($v): int => (int) $v)->all();
    }

    /**
     * تسجيلات كل يوم في الشهر الأخير — بأيام صفرية مملوءة.
     *
     * القاعدة لا تُعيد صفّاً ليومٍ بلا تسجيل، ورسم ما تُعيده وحده
     * يُنتج خطاً صاعداً دائماً لأن الأيام الفارغة تختفي.
     *
     * @return array<string,int>
     */
    private function enrollmentsByDay(array $ids): array
    {
        $from = now()->subDays(self::DAYS - 1)->startOfDay();

        $rows = Enrollment::whereIn('course_id', $ids)
            ->where('created_at', '>=', $from)
            ->selectRaw(DB::getDriverName() === 'sqlite'
                ? 'date(created_at) as day, count(*) as total'
                : 'DATE(created_at) as day, count(*) as total')
            ->groupBy('day')->pluck('total', 'day')
            ->map(fn ($v): int => (int) $v)->all();

        $series = [];

        for ($day = $from->copy(); $day->lte(now()); $day->addDay()) {
            $key = $day->toDateString();
            $series[$key] = (int) ($rows[$key] ?? 0);
        }

        return $series;
    }

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
