<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Modules\Lms\Models\Certificate;
use App\Modules\Lms\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** رفّ الطالب: ما اشتراه وما أنجزه وما حصل عليه. */
final class MyCoursesController
{
    public function __invoke(Request $request): View
    {
        $enrollments = Enrollment::where('user_id', $request->user()->getKey())
            ->with(['course.instructor.user'])
            ->latest()
            ->get();

        return view('lms.my-courses', [
            'active' => $enrollments->filter(fn (Enrollment $e): bool => $e->hasAccess() && $e->progress_percent < 100),
            'completed' => $enrollments->filter(fn (Enrollment $e): bool => $e->progress_percent >= 100),
            'expired' => $enrollments->filter(fn (Enrollment $e): bool => ! $e->hasAccess()),
            'certificates' => Certificate::where('user_id', $request->user()->getKey())
                ->with('course')->latest('issued_at')->get(),
        ]);
    }
}
