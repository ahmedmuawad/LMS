{{-- الترويسة الأولى: الوعد، ثم الدليل، ثم الفعل. --}}
<section class="relative overflow-hidden">

    {{-- تدرّجات خلفية: زخرفة صرفة لا يقرؤها قارئ الشاشة ولا تؤثر في التباين --}}
    <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true"
         style="background:
            radial-gradient(52rem 30rem at 88% -12%, var(--color-primary-subtle), transparent 58%),
            radial-gradient(40rem 26rem at 6% -8%, var(--color-accent-subtle), transparent 62%),
            radial-gradient(60rem 30rem at 50% 108%, var(--color-surface-sunken), transparent 60%);"></div>

    <div class="max-w-[1200px] mx-auto px-4 sm:px-6 pt-14 sm:pt-20 pb-12 sm:pb-16">
        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1.02fr)_minmax(0,0.98fr)] gap-12 lg:gap-16 items-center">

            <div class="min-w-0">
                <p class="inline-flex items-center gap-2 rounded-full border border-accent/40 bg-accent-subtle text-accent-text ps-2 pe-3.5 py-1 text-xs font-bold mb-6">
                    <span class="inline-block size-1.5 rounded-full bg-accent motion-safe:animate-pulse" aria-hidden="true"></span>
                    {{ __('منصّة واحدة · خمسة أنماط · صفر عمولة') }}
                </p>

                <h1 class="text-[2.1rem] sm:text-5xl lg:text-[3.35rem] font-extrabold leading-[1.2] tracking-tight mb-6">
                    {{ __('منصّتك التعليمية كاملة') }}<br>
                    <span class="relative inline-block">
                        <span class="relative z-10">{{ __('أونلاين وحضورياً') }}</span>
                        {{-- تمييز بخط عريض تحت الكلمة بدل تلوين النص: يبقى التباين كما هو --}}
                        <span class="absolute inset-x-0 bottom-1 h-3.5 sm:h-4 bg-accent-subtle -z-0 rounded-sm" aria-hidden="true"></span>
                    </span>
                    <span class="text-primary">{{ __('في نظام واحد') }}</span>
                </h1>

                <p class="text-lg sm:text-xl text-muted leading-relaxed max-w-[52ch] mb-8">
                    {{ __('بِع كورساتك وخدماتك ومنتجاتك، وأدِر سنترك بمجموعاته وحضوره وأقساطه وأولياء أموره — على منصّة باسمك ونطاقك، بقاعدة بيانات مستقلة، وبالدفع المحلي، وبلا أي عمولة على مبيعاتك.') }}
                </p>

                <div class="flex flex-wrap items-center gap-3 mb-6">
                    <x-ui.button size="lg" :href="url('/start')" class="shadow-md">{{ __('ابدأ تجربة 14 يوماً') }}</x-ui.button>
                    <x-ui.button size="lg" variant="secondary" href="#compare">{{ __('قارِنّا بالمنافسين') }}</x-ui.button>
                </div>

                <ul class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-subtle">
                    @foreach(['بلا بطاقة ائتمان', 'منصّة عاملة في أقل من دقيقة', 'غيّر نمطك لاحقاً بلا فقد بيانات'] as $item)
                        <li class="flex items-center gap-1.5">
                            <span class="text-success" aria-hidden="true">✓</span>{{ __($item) }}
                        </li>
                    @endforeach
                </ul>
            </div>

            {{--
                الدليل البصري: الفكرة الحاكمة في وثيقة 16 — طالب واحد،
                سجل واحد. أوضح من أي لقطة شاشة للوحة تحكّم.
            --}}
            <div class="min-w-0 relative">

                {{-- بطاقة خلفية مائلة: عمق بلا صورة، ومخفيّة عن الشاشات الضيقة --}}
                <div class="hidden sm:block absolute -inset-3 rounded-2xl bg-spot/10 border border-line rotate-2" aria-hidden="true"></div>

                <div class="relative rounded-2xl border border-line bg-surface shadow-xl overflow-hidden">

                    <div class="flex items-center gap-3 px-5 py-4 border-b border-line"
                         style="background-image: linear-gradient(135deg, var(--color-primary-subtle), transparent 70%);">
                        <span class="size-11 rounded-full grid place-items-center text-primary-on font-bold shrink-0"
                              style="background-image: linear-gradient(140deg, var(--color-primary), var(--sem-primary-hover));"
                              aria-hidden="true">ي</span>
                        <div class="min-w-0">
                            <p class="font-bold truncate">{{ __('يوسف حمدي') }}</p>
                            <p class="text-2xs text-subtle truncate">{{ __('ثانوية · فيزياء · مجموعة السبت 4م') }}</p>
                        </div>
                        <span class="ms-auto shrink-0 text-2xs font-bold px-2.5 py-1 rounded-full bg-surface border border-line-strong">{{ __('سجل واحد') }}</span>
                    </div>

                    <ul class="divide-y divide-[var(--color-line)]">
                        @foreach([
                            ['icon' => '🏫', 'title' => 'مجموعة حضورية', 'meta' => 'حضور 14 من 16 حصّة', 'chip' => '87٪', 'tone' => 'attended'],
                            ['icon' => '🎬', 'title' => 'كورس مسجّل', 'meta' => 'مراجعة نهائية · 62٪ مكتمل', 'chip' => 'جارٍ', 'tone' => 'progress'],
                            ['icon' => '📝', 'title' => 'امتحان أونلاين', 'meta' => 'الفصل الثالث · تصحيح فوري', 'chip' => '78٪', 'tone' => 'completed'],
                            ['icon' => '💳', 'title' => 'قسط الشهر', 'meta' => '650 ج.م · متأخر 51 يوماً', 'chip' => 'متأخر', 'tone' => 'overdue'],
                        ] as $row)
                            <li class="flex items-center gap-3 px-5 py-3.5">
                                <span class="size-10 rounded-xl grid place-items-center bg-surface-sunken text-lg shrink-0" aria-hidden="true">{{ $row['icon'] }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold truncate">{{ __($row['title']) }}</p>
                                    <p class="text-2xs text-subtle truncate">{{ __($row['meta']) }}</p>
                                </div>
                                <span class="shrink-0 text-2xs font-bold px-2.5 py-1 rounded-full text-status-on bg-{{ $row['tone'] }}">{{ __($row['chip']) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="px-5 py-4 bg-spot text-on-spot flex items-center gap-3">
                        <span class="text-xl shrink-0" aria-hidden="true">📄</span>
                        <p class="text-xs leading-relaxed min-w-0">
                            <span class="font-bold">{{ __('تقرير شهري واحد') }}</span>
                            <span class="text-on-spot-muted">{{ __('يجمع الأربعة ويصل لولي الأمر بالواتساب — بضغطة واحدة لكل المجموعة.') }}</span>
                        </p>
                    </div>
                </div>

                <p class="text-2xs text-subtle text-center mt-4 px-4">
                    {{ __('لا منافس عربي أو أجنبي يربط الحضوري بالأونلاين في سجل واحد اليوم.') }}
                </p>
            </div>
        </div>
    </div>

    {{-- أرقام لا شعارات: شريط واحد بلا حدود بين الخلايا سوى فاصل رفيع --}}
    <div class="max-w-[1200px] mx-auto px-4 sm:px-6 pb-14 sm:pb-20">
        <dl class="grid grid-cols-2 lg:grid-cols-4 gap-8 sm:gap-10 rounded-2xl border border-line bg-surface/70 backdrop-blur px-6 sm:px-8 py-8">
            @foreach(config('marketing.hero_stats', []) as $stat)
                <div class="min-w-0 text-center lg:text-start">
                    <dt class="sr-only">{{ __($stat['label']) }}</dt>
                    <dd>
                        <span class="block font-display text-4xl sm:text-5xl font-extrabold text-primary leading-none mb-2.5">{{ $stat['value'] }}</span>
                        <span class="block text-sm font-bold">{{ __($stat['label']) }}</span>
                        <span class="block text-2xs text-subtle mt-1 leading-relaxed">{{ __($stat['note']) }}</span>
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
