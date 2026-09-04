@php
    /*
     | الشبكة تُبنى بدقائق لا بساعات: حصة من ٤:٣٠ إلى ٦:٠٠ لا تقع
     | على حدود ساعة، ورسمها بالساعات يكذب على من يقرأ الجدول.
     */
    $startHour = $hours[0] ?? 14;
    $endHour   = ($hours[count($hours) - 1] ?? 21) + 1;
    $span      = max(1, ($endHour - $startHour) * 60);
    $offset    = fn (string $time): float => max(0, ((int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2)) - $startHour * 60);
@endphp

<x-layouts.admin :title="__('إشغال القاعات')" current="rooms-occupancy">
<div class="max-w-[1400px]">

    <x-ui.page-header :title="__('إشغال القاعات')"
                      :subtitle="__('«فين قاعة فاضية السبت الساعة ٤؟» — الجواب بنظرة.')" />

    <x-ui.card class="mb-4">
        <form method="GET" action="{{ url('/admin/rooms-occupancy') }}"
              class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
            <x-ui.field :label="__('اليوم')" name="weekday">
                <x-ui.select name="weekday" onchange="this.form.submit()">
                    @foreach($weekdays as $key => $name)
                        <option value="{{ $key }}" @selected($weekday === $key)>{{ __($name) }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>

            <x-ui.field :label="__('الفرع')" name="branch">
                <x-ui.select name="branch" onchange="this.form.submit()">
                    <option value="">{{ __('كل الفروع') }}</option>
                    @foreach($branches as $item)
                        <option value="{{ $item->id }}" @selected((string) $branch === (string) $item->id)>{{ $item->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>

            <x-ui.button type="submit" variant="secondary">{{ __('اعرض') }}</x-ui.button>
        </form>
    </x-ui.card>

    @if($rooms->isEmpty())
        <x-ui.empty :title="__('لا قاعات')">
            {{ __('أضف قاعات الفرع لتظهر شبكة الإشغال.') }}
            <x-slot:action>
                <x-ui.button size="sm" :href="url('/admin/rooms/create')">{{ __('أضف قاعة') }}</x-ui.button>
            </x-slot:action>
        </x-ui.empty>
    @else
        <x-ui.card :padding="false">
            <div class="overflow-x-auto">
                <div class="min-w-[720px] p-4">

                    {{-- شريط الساعات --}}
                    <div class="flex items-end gap-2 mb-2 ps-32">
                        <div class="relative flex-1 h-5">
                            @foreach($hours as $hour)
                                <span class="absolute text-2xs text-subtle font-mono tabular -translate-x-1/2"
                                      style="inset-inline-start: {{ round((($hour - $startHour) * 60) / $span * 100, 3) }}%">
                                    {{ sprintf('%02d:00', $hour) }}
                                </span>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        @foreach($rooms as $room)
                            @php $booked = $slots[$room->id] ?? collect(); @endphp
                            <div class="flex items-stretch gap-2">
                                <div class="w-32 shrink-0 min-w-0 flex flex-col justify-center">
                                    <span class="text-sm font-semibold truncate">{{ $room->name }}</span>
                                    <span class="text-2xs text-subtle truncate">
                                        {{ $room->branch?->name }} ·
                                        {{ trans_choice('{1}مقعد واحد|{2}مقعدان|[3,10]:count مقاعد|[11,*]:count مقعداً', (int) $room->capacity, ['count' => $room->capacity]) }}
                                    </span>
                                </div>

                                <div class="relative flex-1 h-14 rounded-md bg-surface-sunken border border-line overflow-hidden">
                                    {{-- خطوط الساعات تُقرأ خلف الحجز --}}
                                    @foreach($hours as $hour)
                                        <span class="absolute inset-y-0 w-px bg-line" aria-hidden="true"
                                              style="inset-inline-start: {{ round((($hour - $startHour) * 60) / $span * 100, 3) }}%"></span>
                                    @endforeach

                                    @foreach($booked as $slot)
                                        @php
                                            $from = $offset((string) $slot->starts_at);
                                            $to   = $offset((string) $slot->ends_at);
                                        @endphp
                                        <a href="{{ url('/admin/groups/'.$slot->group_id.'/slots') }}"
                                           class="absolute inset-y-1 rounded-md px-2 py-1 overflow-hidden
                                                  bg-primary-subtle border border-primary hover:bg-primary hover:text-primary-on transition-colors"
                                           style="inset-inline-start: {{ round($from / $span * 100, 3) }}%; width: {{ round(max(2, $to - $from) / $span * 100, 3) }}%"
                                           title="{{ $slot->group?->name }} · {{ $slot->timeLabel() }} · {{ $slot->group?->teacher?->name }}">
                                            <span class="block text-2xs font-semibold truncate">{{ $slot->group?->name }}</span>
                                            <span class="block text-2xs opacity-80 truncate font-mono tabular">{{ $slot->timeLabel() }}</span>
                                        </a>
                                    @endforeach

                                    @if($booked->isEmpty())
                                        <span class="absolute inset-0 grid place-items-center text-2xs text-subtle">{{ __('فارغة طوال اليوم') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-ui.card>

        <p class="text-xs text-muted mt-3">
            {{ __('المعروض هو الموعد الأسبوعي المتكرر. الحصة المُلغاة في يوم بعينه تظهر في جدول الحصص لا هنا.') }}
        </p>
    @endif
</div>
</x-layouts.admin>
