<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Modules\Lms\Actions\BuildCurriculum;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\Taxonomy;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** كتالوج الكورسات وصفحة الكورس — ما يراه الزائر قبل أن يدفع. */
final class CatalogController
{
    public function index(Request $request): View
    {
        $courses = Course::query()
            ->visibleTo($request->user())
            ->with(['instructor.user', 'category'])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q').'%';
                // البحث في عمود json يغطّي اللغتين معاً بلا استعلام لكل لغة
                $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('excerpt', 'like', $term));
            })
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->integer('category')))
            ->when($request->filled('level'), fn ($q) => $q->where('level_id', $request->integer('level')))
            ->when($request->input('price') === 'free', fn ($q) => $q->where('price_minor', 0))
            ->when($request->input('price') === 'paid', fn ($q) => $q->where('price_minor', '>', 0))
            ->when($request->input('sort') === 'popular', fn ($q) => $q->orderByDesc('students_count'))
            ->when($request->input('sort') === 'rating', fn ($q) => $q->orderByDesc('rating_avg'))
            ->when($request->input('sort') === 'price', fn ($q) => $q->orderBy('price_minor'))
            ->when(! $request->filled('sort'), fn ($q) => $q->orderByDesc('published_at'))
            ->paginate(12)
            ->withQueryString();

        return view('lms.catalog', [
            'courses' => $courses,
            'categories' => Taxonomy::ofType('category')->orderBy('position')->get(),
            'levels' => Taxonomy::ofType('level')->orderBy('position')->get(),
        ]);
    }

    public function show(Request $request, string $slug, BuildCurriculum $curriculum): View
    {
        $course = Course::where('slug', $slug)
            ->visibleTo($request->user())
            ->with(['instructor.user', 'category', 'level'])
            ->firstOrFail();

        $enrollment = $request->user() === null ? null : Enrollment::where('user_id', $request->user()->getKey())
            ->where('course_id', $course->getKey())
            ->first();

        return view('lms.course', [
            'course' => $course,
            'enrollment' => $enrollment,
            'sections' => $curriculum->handle($course, $enrollment),
            'reviews' => $course->reviews()->approved()->with('user')->latest()->limit(10)->get(),
        ]);
    }
}
