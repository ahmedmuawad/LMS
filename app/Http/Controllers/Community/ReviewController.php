<?php

declare(strict_types=1);

namespace App\Http\Controllers\Community;

use App\Modules\Community\Actions\SubmitReview;
use App\Modules\Community\Models\ServiceReview;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseReview;
use App\Modules\Services\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** كتابة التقييمات ومراجعتها من اللوحة. */
final class ReviewController
{
    public function __construct(private readonly SubmitReview $reviews) {}

    public function storeCourse(Request $request, string $slug): RedirectResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        $input = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $review = $this->reviews->forCourse($request->user(), $course, $input);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['review' => $e->getMessage()]);
        }

        return back()->with('status', $review->status === 'approved'
            ? __('نُشر تقييمك. شكراً لك.')
            : __('وصل تقييمك وسيظهر بعد المراجعة.'));
    }

    public function storeService(Request $request, string $slug): RedirectResponse
    {
        $service = Service::where('slug', $slug)->firstOrFail();

        $input = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $review = $this->reviews->forService($request->user(), $service, $input);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['review' => $e->getMessage()]);
        }

        return back()->with('status', $review->status === 'approved'
            ? __('نُشر تقييمك. شكراً لك.')
            : __('وصل تقييمك وسيظهر بعد المراجعة.'));
    }

    /** طابور المراجعة: المعلَّق أولاً لأن الشاشة عمل لا أرشيف. */
    public function queue(Request $request): View
    {
        $status = (string) $request->input('status', 'pending');

        return view('community.moderation', [
            'courseReviews' => CourseReview::with(['user', 'course'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(20, ['*'], 'courses')->withQueryString(),
            'serviceReviews' => ServiceReview::with(['user', 'service'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(20, ['*'], 'services')->withQueryString(),
            'status' => $status,
        ]);
    }

    public function moderate(Request $request, string $type, string $id): RedirectResponse
    {
        $input = $request->validate([
            'status' => ['required', 'string', 'in:pending,approved,rejected'],
            'reply' => ['nullable', 'string', 'max:2000'],
        ]);

        // النوع من قائمة مغلقة: لا يصل اسم صنف من الطلب إلى الحاوية
        $review = match ($type) {
            'course' => CourseReview::findOrFail($id),
            'service' => ServiceReview::findOrFail($id),
            default => abort(404),
        };

        $reply = (bool) setting('community.allow_reply', true) ? ($input['reply'] ?? null) : null;

        $this->reviews->moderate($review, $input['status'], $reply);

        return back()->with('status', __('حُدّثت حالة التقييم.'));
    }
}
