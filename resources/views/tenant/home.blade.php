@php
    $wa = preg_replace('/\D+/', '', (string) $whatsapp);
@endphp

<x-layouts.app :title="site_name()">
<x-site.header />

<main id="main">

    {{--
        الواجهة: العنوان أولاً والصورة بعده على الهاتف.
        الزائر جاء ليقرأ ما تقدّمه لا ليرى صورةً تملأ شاشته الأولى.
    --}}
    <section class="border-b border-line bg-surface">
        <div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-12 sm:py-20
                    grid gap-8 lg:grid-cols-[minmax(0,1fr)_400px] lg:items-center">

            <div class="min-w-0">
                <h1 class="text-2xl sm:text-4xl font-extrabold leading-tight mb-4">{{ $headline }}</h1>

                @if($subheadline)
                    <p class="text-base text-muted leading-relaxed mb-7 max-w-[52ch]">{{ $subheadline }}</p>
                @endif

                <div class="flex flex-wrap gap-3">
                    <x-ui.button size="lg" :href="$ctaUrl">{{ $ctaLabel }}</x-ui.button>

                    @auth
                        <x-ui.button size="lg" variant="secondary" :href="url('/me')">{{ __('لوحتي') }}</x-ui.button>
                    @else
                        {{-- دخول الطالب ظاهرٌ في الواجهة: الطالب العائد أكثر من الجديد --}}
                        <x-ui.button size="lg" variant="secondary" :href="url('/login')">{{ __('دخول الطلاب') }}</x-ui.button>
                    @endauth
                </div>

                @if($phone || $wa)
                    <div class="flex flex-wrap gap-x-5 gap-y-2 mt-7 text-sm">
                        @if($wa)
                            <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener"
                               class="tap-link text-primary font-semibold hover:underline">{{ __('واتساب') }}</a>
                        @endif
                        @if($phone)
                            <a href="tel:{{ $phone }}" class="tap-link text-muted font-mono tabular hover:text-content">{{ $phone }}</a>
                        @endif
                    </div>
                @endif
            </div>

            @if($heroImage)
                <div class="rounded-xl overflow-hidden border border-line bg-surface-sunken aspect-[4/3] order-first lg:order-last">
                    <img src="{{ $heroImage }}" alt="" class="w-full h-full object-cover">
                </div>
            @endif
        </div>
    </section>

    @if($points !== [])
        <section class="max-w-[1100px] mx-auto px-4 sm:px-6 py-12">
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach($points as $point)
                    <div class="surface-card p-5">
                        <span class="block w-9 h-9 rounded-lg bg-primary-subtle text-primary grid place-items-center mb-3"
                              aria-hidden="true">✓</span>
                        <p class="text-sm leading-relaxed">{{ $point }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- المجموعات المفتوحة: أهمّ ما يبحث عنه وليّ الأمر --}}
    @if($groups->isNotEmpty())
        <section class="border-t border-line bg-surface-sunken">
            <div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-12">
                <h2 class="text-lg sm:text-xl font-bold mb-1">{{ __('المجموعات المفتوحة') }}</h2>
                <p class="text-sm text-muted mb-6">{{ __('المقاعد محدودة — والمواعيد كما هي أدناه.') }}</p>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($groups as $group)
                        <div class="surface-card p-4 grid gap-2">
                            <p class="font-semibold text-sm">{{ $group->name }}</p>

                            <p class="text-xs text-muted">
                                {{ $group->subject?->name }}
                                @if($group->grade) · {{ $group->grade->name }} @endif
                            </p>

                            @if($group->schedules->isNotEmpty())
                                <p class="text-2xs text-subtle font-mono tabular">
                                    {{ $group->schedules->map(fn ($s) => $s->weekdayLabel().' '.$s->timeLabel())->implode(' · ') }}
                                </p>
                            @endif

                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                @if($group->price_minor > 0)
                                    <span class="font-mono text-sm font-semibold tabular">{{ $group->price()->format() }}</span>
                                @endif

                                @php $left = $group->seatsLeft(); @endphp
                                @if($left > 0 && $left <= 5)
                                    {{-- الندرة بالرقم لا بكلمة «سارع» --}}
                                    <x-ui.badge tone="warning">{{ __('بقي :n مقاعد', ['n' => $left]) }}</x-ui.badge>
                                @elseif($left <= 0)
                                    <x-ui.badge tone="neutral">{{ __('مكتملة') }}</x-ui.badge>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if($courses->isNotEmpty())
        <section class="max-w-[1100px] mx-auto px-4 sm:px-6 py-12">
            <div class="flex flex-wrap items-baseline justify-between gap-3 mb-6">
                <h2 class="text-lg sm:text-xl font-bold">{{ __('الكورسات') }}</h2>
                <a href="{{ url('/courses') }}" class="tap-link text-sm text-primary hover:underline">{{ __('عرض الكل') }} ←</a>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($courses as $course)
                    <x-lms.course-card :course="$course" />
                @endforeach
            </div>
        </section>
    @endif

    @if($services->isNotEmpty())
        <section class="border-t border-line">
            <div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-12">
                <h2 class="text-lg sm:text-xl font-bold mb-6">{{ __('الخدمات') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($services as $service)
                        <a href="{{ url('/services/'.$service->slug) }}"
                           class="surface-card p-4 hover:border-primary transition-colors group">
                            <p class="text-sm font-semibold group-hover:text-primary transition-colors">{{ $service->title }}</p>
                            @if($service->excerpt)
                                <p class="text-xs text-muted mt-1 line-clamp-2 leading-relaxed">{{ $service->excerpt }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if($about)
        <section class="border-t border-line bg-surface">
            <div class="max-w-[68ch] mx-auto px-4 sm:px-6 py-12">
                <h2 class="text-lg font-bold mb-4">{{ __('عنّا') }}</h2>
                <p class="text-sm text-muted leading-loose whitespace-pre-line">{{ $about }}</p>
            </div>
        </section>
    @endif

    @if($posts->isNotEmpty())
        <section class="max-w-[1100px] mx-auto px-4 sm:px-6 py-12">
            <h2 class="text-lg sm:text-xl font-bold mb-6">{{ __('من المدوّنة') }}</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach($posts as $post)
                    <a href="{{ url('/blog/'.$post->slug) }}"
                       class="surface-card p-4 hover:border-primary transition-colors group">
                        <p class="text-sm font-semibold group-hover:text-primary transition-colors line-clamp-2">{{ $post->title }}</p>
                        @if($post->excerpt)
                            <p class="text-xs text-muted mt-1.5 line-clamp-3 leading-relaxed">{{ $post->excerpt }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{--
        دعوةٌ أخيرة عند نهاية الصفحة: من قرأ إلى هنا مهتمّ،
        وإعادةُ الزرّ إليه أرخص من أن يبحث عنه بالتمرير لأعلى.
    --}}
    <section class="border-t border-line bg-surface-sunken">
        <div class="max-w-[680px] mx-auto px-4 sm:px-6 py-14 text-center">
            <h2 class="text-lg sm:text-xl font-bold mb-3">{{ __('جاهز تبدأ؟') }}</h2>
            <div class="flex flex-wrap gap-3 justify-center">
                <x-ui.button size="lg" :href="$ctaUrl">{{ $ctaLabel }}</x-ui.button>
                @if($wa)
                    <x-ui.button size="lg" variant="secondary"
                                 :href="'https://wa.me/'.$wa" target="_blank" rel="noopener">{{ __('اسأل على واتساب') }}</x-ui.button>
                @endif
            </div>
        </div>
    </section>

</main>

<x-site.footer />
</x-layouts.app>
