@php
    $email = config('marketing.brand.email');
@endphp

<section id="start" class="scroll-mt-20 relative overflow-hidden bg-spot text-on-spot">
    <div class="pointer-events-none absolute inset-0 opacity-70" aria-hidden="true"
         style="background:
            radial-gradient(46rem 26rem at 20% -10%, var(--color-spot-raised), transparent 62%),
            radial-gradient(40rem 24rem at 85% 110%, var(--color-spot-raised), transparent 62%);"></div>

    <div class="relative max-w-[1200px] mx-auto px-4 sm:px-6 py-20 sm:py-28 text-center">
        <p class="inline-flex items-center gap-2 text-xs font-bold tracking-wide text-on-spot-accent mb-5">
            <span class="inline-block h-px w-6 bg-on-spot-accent" aria-hidden="true"></span>
            {{ __('ابدأ اليوم') }}
        </p>

        <h2 class="font-display text-3xl sm:text-5xl font-extrabold max-w-[22ch] mx-auto leading-[1.22] tracking-tight">
            {{ __('ابدأ بنمطك أنت — والباقي يُبنى حولك') }}
        </h2>

        <p class="text-on-spot-muted text-lg mt-5 max-w-[56ch] mx-auto leading-relaxed">
            {{ __('اختر باقتك، وأجب عن سؤال واحد في معالج التهيئة، وستجد منصّتك جاهزة بمحتوى تجريبي تحذفه متى شئت. تجربة 14 يوماً بلا بطاقة ائتمان.') }}
        </p>

        <div class="flex flex-wrap items-center justify-center gap-3 mt-9">
            <x-ui.button size="lg" variant="accent" href="#pricing" class="shadow-lg">{{ __('اختر باقتك') }}</x-ui.button>
            <a href="mailto:{{ $email }}?subject={{ rawurlencode(__('طلب عرض توضيحي')) }}"
               class="inline-flex items-center justify-center gap-2 text-base font-semibold rounded-md border border-spot-line bg-spot-raised text-on-spot px-6 py-3 transition-colors hover:brightness-125">
                {{ __('اطلب عرضاً توضيحياً') }}
            </a>
        </div>

        <p class="text-sm text-on-spot-muted mt-7">
            {{ __('أو راسلنا مباشرة على') }}
            <a href="mailto:{{ $email }}" class="tap-link text-on-spot-accent font-mono hover:underline">{{ $email }}</a>
        </p>
    </div>
</section>
