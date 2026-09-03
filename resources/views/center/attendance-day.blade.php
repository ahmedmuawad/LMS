<x-layouts.admin :title="__('الحضور')" current="attendance">
<div class="max-w-[1100px]">

    <x-ui.page-header :title="__('حصص اليوم')"
                      :subtitle="\Illuminate\Support\Carbon::parse($date)->translatedFormat('l j F Y')">
        <x-slot:actions>
            <form method="GET" class="flex items-end gap-2">
                <x-ui.field :label="__('اليوم')" for="date" class="mb-0">
                    <x-ui.input type="date" name="date" id="date" value="{{ $date }}" onchange="this.form.submit()" />
                </x-ui.field>
                <noscript><x-ui.button size="sm" type="submit" class="h-11">{{ __('عرض') }}</x-ui.button></noscript>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif

    @if($sessions->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا حصص في هذا اليوم')">
                {{ __('إما أنه عطلة، أو أن جدول المجموعات لم يُولَّد بعد.') }}
                <x-slot:action>
                    <x-ui.button size="sm" variant="secondary" :href="url('/admin/schedule')">{{ __('جدول الأسبوع') }}</x-ui.button>
                </x-slot:action>
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-3">
            @foreach($sessions as $session)
                @php $summary = $session->attendanceSummary(); @endphp
                <x-ui.card>
                    <div class="flex flex-wrap items-center gap-4">
                        <span class="font-mono text-sm tabular shrink-0 px-3 py-2 rounded-md bg-surface-sunken">
                            {{ $session->timeLabel() }}
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm truncate">{{ $session->group?->name }}</p>
                            <p class="text-2xs text-subtle">
                                {{ $session->group?->subject?->name }} ·
                                {{ $session->room?->name ?? __('بلا قاعة') }} ·
                                {{ $session->teacher?->name ?? __('بلا مدرّس') }}
                            </p>
                        </div>

                        @if($session->attendanceTaken())
                            <div class="flex flex-wrap gap-1.5 shrink-0">
                                @if(($summary['present'] ?? 0) + ($summary['late'] ?? 0) > 0)
                                    <x-ui.badge tone="success">{{ __(':n حاضر', ['n' => ($summary['present'] ?? 0) + ($summary['late'] ?? 0)]) }}</x-ui.badge>
                                @endif
                                @if(($summary['absent'] ?? 0) > 0)
                                    <x-ui.badge tone="danger">{{ __(':n غائب', ['n' => $summary['absent']]) }}</x-ui.badge>
                                @endif
                            </div>
                        @else
                            <x-ui.badge tone="warning" class="shrink-0">{{ __('لم يُسجَّل') }}</x-ui.badge>
                        @endif

                        <x-ui.button size="sm" :variant="$session->attendanceTaken() ? 'secondary' : 'primary'"
                                     :href="url('/admin/attendance/'.$session->id)" class="shrink-0">
                            {{ $session->attendanceTaken() ? __('تعديل') : __('تسجيل الحضور') }}
                        </x-ui.button>
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</div>
</x-layouts.admin>
