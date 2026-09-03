@props(['course', 'progress' => null])
<article class="surface-card overflow-hidden flex flex-col transition-[border-color] hover:border-line-strong">
    <a href="{{ url('/courses/'.$course->slug) }}" class="block aspect-video bg-surface-sunken relative overflow-hidden">
        @if($course->cover_path)
            <img src="{{ $course->cover_path }}" alt="" class="size-full object-cover" loading="lazy">
        @else
            <span class="absolute inset-0 grid place-items-center text-3xl text-subtle" aria-hidden="true">▤</span>
        @endif
        @if($course->isFree())
            <span class="absolute top-2 start-2"><x-ui.badge tone="success">{{ __('مجاني') }}</x-ui.badge></span>
        @endif
    </a>

    <div class="p-4 flex flex-col gap-2 flex-1 min-w-0">
        @if($course->category)
            <span class="text-2xs text-subtle">{{ $course->category->name }}</span>
        @endif

        <h3 class="font-bold leading-snug min-w-0">
            <a href="{{ url('/courses/'.$course->slug) }}" class="tap-link hover:text-primary transition-colors">{{ $course->title }}</a>
        </h3>

        @if($course->instructor)
            <p class="text-xs text-muted">{{ $course->instructor->name() }}</p>
        @endif

        @if($progress !== null)
            <div class="mt-1">
                <x-ui.progress :value="$progress" :label="__('تقدّمك')" />
                <p class="text-2xs text-subtle mt-1 font-mono tabular">{{ $progress }}%</p>
            </div>
        @endif

        <div class="flex items-center justify-between gap-2 mt-auto pt-2">
            <span class="font-mono text-sm tabular">
                @if($course->isFree())
                    <span class="text-success font-semibold">{{ __('مجاني') }}</span>
                @else
                    {{ $course->price()->format() }}
                    @if($course->compare_price_minor)
                        <s class="text-2xs text-subtle ms-1">{{ App\Core\Support\Money::fromMinor((int) $course->compare_price_minor, $course->currency ?? tenant('currency') ?? 'EGP')->format() }}</s>
                    @endif
                @endif
            </span>
            <span class="text-2xs text-subtle font-mono tabular">
                {{ trans_choice('{0} لا طلاب|{1} طالب|{2} طالبان|[3,10] :count طلاب|[11,*] :count طالباً', (int) $course->students_count, ['count' => (int) $course->students_count]) }}
            </span>
        </div>
    </div>
</article>
