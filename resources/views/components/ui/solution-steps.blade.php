@props(['steps' => null, 'title' => null])
@php
    // سطر لكل خطوة — والفراغات تُهمَل حتى لا تُعدّ خطوةً فارغة
    $lines = collect(preg_split('/\r\n|\r|\n/', (string) $steps))
        ->map(fn (string $line): string => trim($line))
        ->filter()
        ->values();
@endphp

@if($lines->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-line bg-surface-sunken p-4']) }}>
        <h4 class="text-xs font-bold text-muted mb-3">{{ $title ?? __('خطوات الحل') }}</h4>

        {{-- مرقّمة لأن الترتيب هو الحلّ نفسه: خطوة قبل أختها تُغيّر النتيجة --}}
        <ol class="grid gap-2.5">
            @foreach($lines as $index => $line)
                <li class="flex items-start gap-3">
                    <span class="size-6 shrink-0 grid place-items-center rounded-full bg-primary-subtle text-primary text-2xs font-bold tabular"
                          aria-hidden="true">{{ $index + 1 }}</span>
                    <x-ui.math as="span" class="text-sm min-w-0">{{ $line }}</x-ui.math>
                </li>
            @endforeach
        </ol>
    </div>
@endif
