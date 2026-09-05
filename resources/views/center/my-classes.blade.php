@php
    use App\Modules\Center\Models\Session;

    $statusLabels = [
        'present' => 'حاضر', 'absent' => 'غائب',
        'late' => 'متأخّر', 'excused' => 'بعذر',
    ];
    $statusTones = [
        'present' => 'success', 'absent' => 'danger',
        'late' => 'warning', 'excused' => 'info',
    ];

    /*
     | الرابط يُفتح قبل الموعد بنصف ساعة وحتى انتهاء الحصة.
     |
     | الرابط الظاهر طوال الأسبوع يُنسخ ويُتداول خارج المشتركين،
     | والظاهر في اللحظة فقط يُفوّت من تأخّر دقيقةً على اتصاله.
     */
    $joinable = function (Session $session) use ($opensBefore): bool {
        if (blank($session->meeting_url ?? $session->group?->meeting_url)) {
            return false;
        }

        $start = $session->date?->copy()->setTimeFromTimeString((string) $session->starts_at);

        if ($start === null) {
            return false;
        }

        $end = $session->ends_at
            ? $session->date->copy()->setTimeFromTimeString((string) $session->ends_at)
            : $start->copy()->addHours(2);

        return now()->between($start->copy()->subMinutes($opensBefore), $end);
    };

    $link = fn (Session $s): ?string => $s->meeting_url ?: $s->group?->meeting_url;
@endphp

<x-layouts.student :title="__('حصصي')" current="my-classes">

    <x-ui.page-header :title="__('حصصي')"
                      :subtitle="__('مجموعاتك ومواعيدها ورابط الدخول وسجلّ حضورك.')" />

    @if($student === null)
        <x-ui.card>
            <x-ui.empty :title="__('حسابك غير مربوط بملفّ طالب')">
                {{ __('اطلب من مدرّسك ربط حسابك بملفّك في المركز، عندها تظهر هنا مجموعاتك ومواعيد حصصك.') }}
            </x-ui.empty>
        </x-ui.card>
    @else

        {{-- الحصص القادمة أولاً: هذا ما جاء الطالب لأجله --}}
        <section class="mb-8">
            <h2 class="text-sm font-bold text-subtle mb-3">{{ __('حصصك القادمة') }}</h2>

            @if($upcoming->isEmpty())
                <x-ui.card>
                    <x-ui.empty :title="__('لا حصص مجدولة')">
                        {{ __('لم يُجدول مدرّسك حصصاً قادمة بعد.') }}
                    </x-ui.empty>
                </x-ui.card>
            @else
                <div class="grid gap-3">
                    @foreach($upcoming as $session)
                        @php $open = $joinable($session); $url = $link($session); @endphp
                        <div class="surface-card p-4 flex flex-wrap items-center gap-x-4 gap-y-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-sm truncate">
                                    {{ $session->group?->subject?->name ?? __('حصة') }}
                                    @if($session->group)
                                        <span class="text-muted font-normal">· {{ $session->group->name }}</span>
                                    @endif
                                </p>

                                <p class="text-xs text-muted font-mono tabular mt-1">
                                    {{ $session->date?->translatedFormat('l j F') }} ·
                                    {{ $session->timeLabel() }}
                                </p>

                                <p class="text-xs text-subtle mt-1">
                                    @if($session->teacher)
                                        {{ $session->teacher->name }}
                                    @endif
                                    @if($session->room)
                                        · {{ $session->room->name }}@if($session->room->branch) — {{ $session->room->branch->name }}@endif
                                    @endif
                                    @if($session->topic)
                                        · {{ $session->topic }}
                                    @endif
                                </p>
                            </div>

                            <div class="shrink-0 w-full sm:w-auto">
                                @if($open && $url)
                                    <x-ui.button as="a" :href="$url" target="_blank" rel="noopener"
                                                 class="w-full sm:w-auto">{{ __('ادخل الحصة') }}</x-ui.button>
                                @elseif($url)
                                    {{-- الوقت لا الرابط هو ما ينقص: قل متى بدل أن تُخفي --}}
                                    <p class="text-2xs text-subtle text-center sm:text-end leading-relaxed">
                                        {{ __('يُفتح الرابط قبل الموعد بـ :minutes دقيقة', ['minutes' => $opensBefore]) }}
                                    </p>
                                @else
                                    <p class="text-2xs text-subtle text-center sm:text-end">{{ __('حضورياً') }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="grid gap-6 lg:grid-cols-2">

            <section>
                <h2 class="text-sm font-bold text-subtle mb-3">{{ __('مجموعاتي') }}</h2>
                <div class="grid gap-2">
                    @forelse($groups as $enrolment)
                        @continue(! $enrolment->group)
                        @php $group = $enrolment->group; @endphp
                        <div class="surface-card p-3">
                            <p class="text-sm font-semibold">
                                {{ $group->name }}
                                @if($group->subject)
                                    <span class="text-muted font-normal">· {{ $group->subject->name }}</span>
                                @endif
                            </p>
                            @if($group->teacher)
                                <p class="text-xs text-muted mt-0.5">{{ $group->teacher->name }}</p>
                            @endif
                            @if($group->schedules->isNotEmpty())
                                <p class="text-2xs text-subtle font-mono tabular mt-1.5">
                                    {{ $group->schedules->map(fn ($s) => $s->weekdayLabel().' '.$s->timeLabel())->implode(' · ') }}
                                </p>
                            @endif
                        </div>
                    @empty
                        <x-ui.card>
                            <x-ui.empty :title="__('لست في مجموعة بعد')">
                                {{ __('تواصل مع مدرّسك لضمّك إلى مجموعة.') }}
                            </x-ui.empty>
                        </x-ui.card>
                    @endforelse
                </div>
            </section>

            <section>
                <h2 class="text-sm font-bold text-subtle mb-3">{{ __('سجلّ حضوري') }}</h2>

                @if($attendance->isEmpty())
                    <x-ui.card>
                        <x-ui.empty :title="__('لا سجلّ بعد')">
                            {{ __('يظهر هنا حضورك وغيابك بعد أول حصة.') }}
                        </x-ui.empty>
                    </x-ui.card>
                @else
                    <div class="grid gap-1.5">
                        @foreach($attendance as $record)
                            <div class="surface-card px-3 py-2.5 flex items-center gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold truncate">
                                        {{ $record->session?->group?->subject?->name ?? __('حصة') }}
                                    </p>
                                    <p class="text-2xs text-subtle font-mono tabular mt-0.5">
                                        {{ $record->session?->date?->translatedFormat('j F Y') ?? '—' }}
                                    </p>
                                </div>
                                <x-ui.badge :tone="$statusTones[$record->status] ?? 'neutral'">
                                    {{ __($statusLabels[$record->status] ?? $record->status) }}
                                </x-ui.badge>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    @endif

</x-layouts.student>
