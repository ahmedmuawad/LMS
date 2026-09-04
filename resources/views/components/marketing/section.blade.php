@props([
    'id' => null,
    'eyebrow' => null,
    'title' => null,
    'lead' => null,
    'tone' => 'plain',   // plain | sunken | spot | tint
    'align' => 'center', // center | start
    'wide' => false,
])
@php
    /*
     | غلاف القسم. النغمة (tone) هي ما يمنع الصفحة من أن تصير
     | كومة بطاقات بيضاء متطابقة: أسطح تتبادل، ولوح داكن يقطع
     | الإيقاع، وتدرّج خفيف يسبق الأسعار.
     */
    $tones = [
        'plain'  => 'bg-bg',
        'sunken' => 'bg-surface-sunken',
        'spot'   => 'bg-spot text-on-spot',
        'tint'   => 'bg-bg',
    ];
@endphp

<section @if($id) id="{{ $id }}" @endif
         {{ $attributes->merge(['class' => 'relative py-16 sm:py-24 scroll-mt-20 '.($tones[$tone] ?? $tones['plain'])]) }}>

    @if($tone === 'tint')
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true"
             style="background:
                radial-gradient(48rem 26rem at 78% 0%, var(--color-primary-subtle), transparent 62%),
                radial-gradient(36rem 22rem at 12% 100%, var(--color-accent-subtle), transparent 66%);"></div>
    @endif

    @if($tone === 'spot')
        <div class="pointer-events-none absolute inset-0 opacity-60" aria-hidden="true"
             style="background:
                radial-gradient(44rem 24rem at 80% -5%, var(--color-spot-raised), transparent 60%),
                radial-gradient(34rem 20rem at 5% 105%, var(--color-spot-raised), transparent 62%);"></div>
    @endif

    <div class="relative {{ $wide ? 'max-w-[1320px]' : 'max-w-[1200px]' }} mx-auto px-4 sm:px-6">

        @if($eyebrow || $title || $lead)
            <div @class([
                'mb-10 sm:mb-14',
                'max-w-[60ch] mx-auto text-center' => $align === 'center',
                'max-w-[64ch]' => $align === 'start',
            ])>
                @if($eyebrow)
                    <p @class([
                        'inline-flex items-center gap-2 text-xs font-bold tracking-wide mb-4',
                        'text-on-spot-accent' => $tone === 'spot',
                        'text-accent-text' => $tone !== 'spot',
                    ])>
                        <span @class([
                            'inline-block h-px w-6',
                            'bg-on-spot-accent' => $tone === 'spot',
                            'bg-accent' => $tone !== 'spot',
                        ]) aria-hidden="true"></span>
                        {{ $eyebrow }}
                    </p>
                @endif

                @if($title)
                    <h2 class="text-[1.7rem] sm:text-4xl font-extrabold leading-[1.25] tracking-tight">{{ $title }}</h2>
                @endif

                @if($lead)
                    <p @class([
                        'mt-4 text-lg leading-relaxed',
                        'text-on-spot-muted' => $tone === 'spot',
                        'text-muted' => $tone !== 'spot',
                    ])>{{ $lead }}</p>
                @endif
            </div>
        @endif

        {{ $slot }}
    </div>
</section>
