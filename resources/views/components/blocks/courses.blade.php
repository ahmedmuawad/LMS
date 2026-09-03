@props(['content' => [], 'settings' => []])
@php
    $t = fn (string $key): ?string => is_array($content[$key] ?? null)
        ? ($content[$key][app()->getLocale()] ?? $content[$key][config('locales.default', 'ar')] ?? null)
        : ($content[$key] ?? null);

    $courses = App\Modules\Lms\Models\Course::published()
        ->with(['instructor.user', 'category'])
        ->when(($content['source'] ?? 'latest') === 'popular', fn ($q) => $q->orderByDesc('students_count'))
        ->when(($content['source'] ?? '') === 'featured', fn ($q) => $q->orderByDesc('rating_avg'))
        ->when(($content['source'] ?? 'latest') === 'latest', fn ($q) => $q->latest('published_at'))
        ->limit((int) ($content['limit'] ?? 6))
        ->get();
    $columns = (int) ($content['columns'] ?? 3);
@endphp

@if($t('heading'))
    <h2 class="text-2xl font-bold mb-6">{{ $t('heading') }}</h2>
@endif

@if($courses->isEmpty())
    <x-ui.empty :title="__('لا كورسات منشورة بعد')">{{ __('ستظهر هنا فور نشر أول كورس.') }}</x-ui.empty>
@else
    <div @class([
        'grid gap-4 sm:grid-cols-2',
        'lg:grid-cols-3' => $columns === 3,
        'lg:grid-cols-4' => $columns === 4,
    ])>
        @foreach($courses as $course)
            <x-lms.course-card :course="$course" />
        @endforeach
    </div>
@endif
