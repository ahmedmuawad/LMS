@php
    use App\Modules\Center\Models\Session;
    $tones = ['scheduled' => 'info', 'running' => 'primary', 'done' => 'success', 'cancelled' => 'danger', 'postponed' => 'warning'];
@endphp

<x-layouts.admin :title="__('جدول الحصص')" current="schedule">
<div class="max-w-[1400px]">

    <x-ui.page-header :title="__('جدول الأسبوع')"
                      :subtitle="$from->translatedFormat('j F').' – '.$to->translatedFormat('j F Y')">
        <x-slot:actions>
            <x-ui.button size="sm" variant="secondary" :href="url('/admin/schedule?from='.$from->copy()->subWeek()->toDateString())">
                <span class="flip-rtl" aria-hidden="true">←</span> {{ __('السابق') }}
            </x-ui.button>
            <x-ui.button size="sm" variant="secondary" :href="url('/admin/schedule?from='.$from->copy()->addWeek()->toDateString())">
                {{ __('التالي') }} <span class="flip-rtl" aria-hidden="true">→</span>
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif
    @error('schedule')<x-ui.alert tone="danger" :title="__('تعارض في الموعد')" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    <div class="grid gap-3 lg:grid-cols-7">
        @foreach($days as $day)
            @php $daySessions = $sessions[$day->toDateString()] ?? collect(); @endphp
            <x-ui.card :padding="false" class="min-w-0">
                <div class="px-3 py-2.5 border-b border-line
                            {{ $day->isToday() ? 'bg-primary-subtle' : 'bg-surface-sunken' }}">
                    <p class="text-xs font-bold {{ $day->isToday() ? 'text-primary' : '' }}">{{ $day->translatedFormat('l') }}</p>
                    <p class="text-2xs text-subtle font-mono">{{ $day->format('m-d') }}</p>
                </div>

                @if($daySessions->isEmpty())
                    <p class="px-3 py-6 text-center text-2xs text-subtle">{{ __('لا حصص') }}</p>
                @else
                    <ul class="p-2 grid gap-2">
                        @foreach($daySessions as $session)
                            <li>
                                <a href="{{ url('/admin/attendance/'.$session->id) }}"
                                   class="block rounded-md border border-line p-2 hover:bg-surface-sunken transition-colors"
                                   style="border-inline-start: 3px solid {{ $session->group?->color ?? 'var(--color-primary)' }}">
                                    <span class="block font-mono text-2xs text-subtle">{{ $session->timeLabel() }}</span>
                                    <span class="block text-xs font-semibold truncate mt-0.5">{{ $session->group?->name }}</span>
                                    <span class="block text-2xs text-subtle truncate">{{ $session->room?->name ?? __('بلا قاعة') }}</span>
                                    @if($session->status !== 'scheduled')
                                        <x-ui.badge :tone="$tones[$session->status] ?? 'neutral'" class="mt-1">
                                            {{ __(Session::STATUSES[$session->status] ?? $session->status) }}
                                        </x-ui.badge>
                                    @elseif($session->attendanceTaken())
                                        <x-ui.badge tone="success" class="mt-1">{{ __('سُجّل') }}</x-ui.badge>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endforeach
    </div>

    <x-ui.card :title="__('لماذا لا يقبل النظام موعداً؟')" class="mt-4">
        <p class="text-sm text-muted leading-relaxed">
            {{ __('قبل حفظ أي حصة نفحص ستة أشياء: القاعة مشغولة؟ المدرّس عنده حصة؟ المجموعة عندها حصة؟ عدد الطلاب يفوق سعة القاعة؟ اليوم عطلة؟ الوقت صحيح؟ — والنتيجة تظهر قبل الحفظ مع أقرب موعد بديل متاح.') }}
        </p>
    </x-ui.card>
</div>
</x-layouts.admin>
