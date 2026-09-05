<x-layouts.student :title="__('التحدّيات')" current="challenges">

    <x-ui.page-header :title="__('التحدّيات')"
                      :subtitle="__('أهدافٌ بمهلة — تُتمّها فتُكافأ. وعجلةٌ تُدار مرّةً كل يوم.')" />

    @error('wheel')<x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>@enderror

    @if($wheelOn)
        <x-ui.card :title="__('عجلة اليوم')" class="mb-6">
            @if(session('wheel_result'))
                @php $result = session('wheel_result'); @endphp

                <div class="text-center py-4">
                    <p class="text-4xl mb-3" aria-hidden="true">✦</p>
                    <p class="text-lg font-bold text-success">{{ $result['label'] }}</p>
                    <p class="text-sm text-muted mt-1">{{ __('أُضيفت إلى رصيدك. عُد غداً لدورةٍ أخرى.') }}</p>
                </div>
            @elseif($canSpin)
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-muted text-sm leading-relaxed">
                            {{ __('دورةٌ واحدة كل يوم، وجائزتها نقاطٌ تُضاف إلى رصيدك. لا تدفع شيئاً ولا تخسر شيئاً.') }}
                        </p>

                        <p class="text-2xs text-subtle mt-2">
                            {{ __('الجوائز:') }}
                            {{ collect($segments)->pluck('label')->implode(' · ') }}
                        </p>
                    </div>

                    <form method="POST" action="{{ url('/challenges/spin') }}">
                        @csrf
                        <x-ui.button type="submit">{{ __('أدِر العجلة') }}</x-ui.button>
                    </form>
                </div>
            @else
                <p class="text-muted text-sm">{{ __('أدرتَ عجلة اليوم — عُد غداً.') }}</p>
            @endif
        </x-ui.card>
    @endif

    @if($rows->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا تحدّيات جارية')">
                {{ __('يضع مدرّسك تحدّيات بمُهَل — مثل «أتمّ خمسة دروس هذا الأسبوع» — وتظهر هنا بتقدّمك فيها.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($rows as $row)
                @php
                    $challenge = $row['challenge'];
                    $percent = (int) $challenge->target > 0
                        ? min(100, (int) round($row['progress'] / (int) $challenge->target * 100))
                        : 0;
                @endphp

                <x-ui.card>
                    <div class="flex items-start gap-3 mb-3">
                        <span class="text-2xl shrink-0" aria-hidden="true">{{ $challenge->icon ?: '◎' }}</span>

                        <div class="min-w-0 flex-1">
                            <h3 class="font-bold truncate">{{ $challenge->title }}</h3>

                            @if($challenge->description)
                                <p class="text-xs text-muted mt-0.5 leading-relaxed">{{ $challenge->description }}</p>
                            @endif
                        </div>

                        @if($row['done'])
                            <x-ui.badge tone="success" class="shrink-0">{{ __('أُنجز') }}</x-ui.badge>
                        @endif
                    </div>

                    {{-- الشريط قبل الأرقام: التقدّم يُرى قبل أن يُقرأ --}}
                    <div class="h-2 rounded-full bg-surface-sunken overflow-hidden mb-2">
                        <div class="h-full rounded-full transition-[width] duration-500
                                    {{ $row['done'] ? 'bg-success' : 'bg-primary' }}"
                             style="width: {{ $percent }}%"></div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 text-2xs">
                        <span class="font-mono tabular text-muted">
                            {{ $row['progress'] }} / {{ $challenge->target }} · {{ $challenge->ruleLabel() }}
                        </span>

                        <span class="text-subtle">
                            @if($challenge->endsInLabel()){{ $challenge->endsInLabel() }} · @endif
                            {{ __('+:points نقطة', ['points' => $challenge->reward_points]) }}
                        </span>
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @endif

</x-layouts.student>
