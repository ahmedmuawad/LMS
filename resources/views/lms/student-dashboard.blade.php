@php
    $me = auth()->user();
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'صباح الخير' : 'مساء الخير';
    $first = trim(explode(' ', (string) $me->name)[0] ?? '');
@endphp

<x-layouts.student :title="__('لوحتي')" current="dashboard">

    <header class="mb-6">
        <h1 class="text-xl sm:text-2xl font-extrabold">
            {{ __($greeting) }}{{ $first !== '' ? '، ' . $first : '' }}
        </h1>
        <p class="text-sm text-muted mt-1">
            @if($continue->isNotEmpty())
                {{ __('عندك :count كورس في المنتصف — أقربها للاكتمال أوّلاً.', ['count' => $continue->count()]) }}
            @elseif($stats['active'] > 0)
                {{ __('ابدأ أول درس اليوم؛ الدرس الأول هو أصعب خطوة.') }}
            @else
                {{ __('لم تبدأ بعد. اختر كورساً وابدأ.') }}
            @endif
        </p>
    </header>

    {{--
        «تابع من حيث وقفت» قبل الأرقام عمداً: الطالب يدخل ليتعلّم لا
        ليقرأ إحصاءه. والأرقام تحته تخدم الدافع لا القرار.
    --}}
    @if($continue->isNotEmpty())
        <section class="mb-8">
            <h2 class="text-sm font-bold text-subtle mb-3">{{ __('تابع من حيث وقفت') }}</h2>
            <div class="grid gap-3">
                @foreach($continue as $enrollment)
                    @continue(! $enrollment->course)
                    @php $course = $enrollment->course; @endphp
                    <a href="{{ url('/learn/' . $course->slug) }}"
                       class="surface-card p-4 flex items-center gap-4 hover:border-primary transition-colors group">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm truncate group-hover:text-primary transition-colors">{{ $course->title }}</p>
                            @if($course->instructor?->user)
                                <p class="text-xs text-muted mt-0.5 truncate">{{ $course->instructor->user->name }}</p>
                            @endif
                            <div class="flex items-center gap-3 mt-2.5">
                                <x-ui.progress :value="$enrollment->progress_percent" :label="__('نسبة الإنجاز')" class="max-w-[220px]" />
                                <span class="text-2xs font-mono text-subtle tabular shrink-0">{{ (int) $enrollment->progress_percent }}%</span>
                            </div>
                        </div>
                        <span class="shrink-0 text-primary text-sm font-semibold hidden sm:block">{{ __('تابع') }} ←</span>
                    </a>
                @endforeach
            </div>
        </section>
    @elseif($notStarted->isNotEmpty())
        <section class="mb-8">
            <h2 class="text-sm font-bold text-subtle mb-3">{{ __('ابدأ من هنا') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($notStarted as $enrollment)
                    @if($enrollment->course)
                        <x-lms.course-card :course="$enrollment->course" :progress="0" />
                    @endif
                @endforeach
            </div>
        </section>
    @elseif($stats['active'] === 0)
        <x-ui.card class="mb-8">
            <x-ui.empty :title="__('لم تسجّل في كورس بعد')">
                {{ __('تصفّح الكورسات المتاحة واختر ما يناسبك — بعضها مجاني تماماً.') }}
                <x-slot:action>
                    <x-ui.button size="sm" :href="url('/courses')">{{ __('تصفّح الكورسات') }}</x-ui.button>
                </x-slot:action>
            </x-ui.empty>
        </x-ui.card>
    @endif

    {{-- تنبيه الانتهاء قبل الأرقام: ما له موعد يسبق ما ليس له --}}
    @if($expiring->isNotEmpty())
        <x-ui.alert tone="warning" class="mb-8">
            <p class="font-semibold text-sm mb-1">{{ __('اشتراكات على وشك الانتهاء') }}</p>
            <ul class="grid gap-1">
                @foreach($expiring as $enrollment)
                    @continue(! $enrollment->course)
                    <li class="text-xs">
                        {{ $enrollment->course->title }} —
                        {{ __('متبقٍ :days يوماً', ['days' => (int) $enrollment->daysLeft()]) }}
                    </li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
        <x-ui.stat :label="__('كورسات نشطة')" :value="$stats['active']" />
        <x-ui.stat :label="__('أنهيتها')" :value="$stats['completed']" />
        <x-ui.stat :label="__('شهادات')" :value="$stats['certificates']" />
        @if($stats['points'] !== null)
            <x-ui.stat :label="__('نقاطي')" :value="number_format($stats['points'])" />
        @endif
    </section>

    @if($upcoming->isNotEmpty() || $answered->isNotEmpty())
        <div class="grid gap-6 lg:grid-cols-2">

            @if($upcoming->isNotEmpty())
                <section>
                    <h2 class="text-sm font-bold text-subtle mb-3">{{ __('حجوزاتك القادمة') }}</h2>
                    <div class="grid gap-2">
                        @foreach($upcoming as $booking)
                            <div class="surface-card p-3 flex items-center gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold truncate">{{ $booking->service?->title ?? __('حجز') }}</p>
                                    <p class="text-xs text-muted font-mono tabular mt-0.5">{{ $booking->startsAtCarbon()?->translatedFormat('l j F · g:i a') ?? '—' }}</p>
                                </div>
                                <x-ui.badge :tone="$booking->status === 'confirmed' ? 'success' : 'warning'">
                                    {{ $booking->status === 'confirmed' ? __('مؤكّد') : __('بانتظار التأكيد') }}
                                </x-ui.badge>
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ url('/my-bookings') }}" class="tap-link inline-block text-xs text-primary mt-2 hover:underline">{{ __('كل حجوزاتي') }} ←</a>
                </section>
            @endif

            @if($answered->isNotEmpty())
                <section>
                    <h2 class="text-sm font-bold text-subtle mb-3">{{ __('ردود على أسئلتك') }}</h2>
                    <div class="grid gap-2">
                        @foreach($answered as $discussion)
                            <a href="{{ url('/discussions/' . $discussion->getKey()) }}"
                               class="surface-card p-3 block hover:border-primary transition-colors">
                                <p class="text-sm font-semibold truncate">{{ $discussion->title }}</p>
                                <p class="text-xs text-muted mt-0.5">
                                    {{ __(':count ردّ', ['count' => (int) $discussion->replies_count]) }}
                                    @if($discussion->course) · {{ $discussion->course->title }} @endif
                                </p>
                            </a>
                        @endforeach
                    </div>
                    <a href="{{ url('/discussions') }}" class="tap-link inline-block text-xs text-primary mt-2 hover:underline">{{ __('كل النقاشات') }} ←</a>
                </section>
            @endif
        </div>
    @endif

</x-layouts.student>
