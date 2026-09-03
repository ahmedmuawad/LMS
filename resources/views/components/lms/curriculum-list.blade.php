@props(['sections' => [], 'course', 'current' => null, 'enrollment' => null])
<div class="p-3">
    @if($enrollment)
        <div class="px-2 pb-3 mb-2 border-b border-line">
            <x-ui.progress :value="$enrollment->progress_percent" :label="__('تقدّمك في الكورس')" />
            <p class="text-2xs text-subtle mt-1.5 font-mono tabular">
                {{ __('أنجزت :percent% من الكورس', ['percent' => $enrollment->progress_percent]) }}
            </p>
        </div>
    @endif

    @foreach($sections as $section)
        <p class="text-2xs tracking-wider text-subtle font-semibold px-2 pt-3 pb-1 flex items-center justify-between gap-2">
            <span class="min-w-0 truncate">{{ $section['title'] }}</span>
            <span class="font-mono tabular shrink-0">{{ $section['done'] }}/{{ $section['total'] }}</span>
        </p>

        @foreach($section['items'] as $row)
            @php $isCurrent = $current !== null && $row['item']->is($current); @endphp

            @if($row['locked'])
                <span class="flex items-start gap-2.5 px-2 py-2 rounded-md text-sm text-subtle cursor-not-allowed select-none"
                      title="{{ $row['lock_reason'] }}">
                    <span aria-hidden="true" class="w-4 text-center shrink-0 mt-0.5">🔒</span>
                    <span class="flex-1 min-w-0">
                        <span class="block truncate">{{ $row['title'] }}</span>
                        <span class="block text-2xs">{{ $row['lock_reason'] }}</span>
                    </span>
                </span>
            @else
                <a href="{{ url('/learn/'.$course->slug.'/'.$row['item']->getKey()) }}"
                   @class([
                       'flex items-start gap-2.5 px-2 py-2 rounded-md text-sm transition-colors',
                       'bg-primary-subtle text-primary font-semibold' => $isCurrent,
                       'text-muted hover:bg-surface-sunken hover:text-content' => ! $isCurrent,
                   ])
                   @if($isCurrent) aria-current="page" @endif>
                    <span aria-hidden="true" class="w-4 text-center shrink-0 mt-0.5">
                        {{ $row['completed'] ? '✓' : $row['item']->icon() }}
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block truncate">{{ $row['title'] }}</span>
                        <span class="block text-2xs text-subtle">{{ $row['item']->label() }}</span>
                    </span>
                </a>
            @endif
        @endforeach
    @endforeach
</div>
