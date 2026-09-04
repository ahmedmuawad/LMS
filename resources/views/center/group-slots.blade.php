<x-layouts.admin :title="__('مواعيد المجموعة')" current="groups">
<div class="max-w-[1000px]">

    <x-ui.page-header :title="__('مواعيد :group', ['group' => $group->name])"
                      :subtitle="__('الموعد الأسبوعي المتكرر: يوم ووقت وقاعة. تُولَّد منه الحصص.')"
                      :back="url('/admin/groups')">
        <x-slot:actions>
            <form method="POST" action="{{ url('/admin/groups/'.$group->id.'/generate') }}">
                @csrf
                <x-ui.button type="submit" variant="secondary">{{ __('ولّد الحصص') }}</x-ui.button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('status'))
        <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @error('schedule')<x-ui.alert tone="warning" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    <div class="grid gap-4 sm:grid-cols-4 mb-4">
        <x-ui.stat :label="__('المادة')" :value="$group->subject?->name ?? '—'" />
        <x-ui.stat :label="__('المدرّس')" :value="$group->teacher?->name ?? '—'" />
        <x-ui.stat :label="__('الطلاب')" :value="$group->enrolled_count.' / '.$group->capacity" />
        <x-ui.stat :label="__('المكان')" :value="$group->venueLabel()" />
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">

        <x-ui.card :title="__('موعد جديد')">
            @error('slot')<x-ui.alert tone="danger" class="mb-3">{{ $message }}</x-ui.alert>@enderror

            <form method="POST" action="{{ url('/admin/groups/'.$group->id.'/slots') }}" class="grid gap-3">
                @csrf

                <x-ui.field :label="__('اليوم')" name="weekday" required>
                    <x-ui.select name="weekday">
                        @foreach($weekdays as $key => $name)
                            <option value="{{ $key }}" @selected((string) old('weekday') === (string) $key)>{{ __($name) }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.field :label="__('من')" name="starts_at" required :error="$errors->first('starts_at')">
                        <x-ui.input type="time" name="starts_at" :value="old('starts_at', '16:00')" />
                    </x-ui.field>

                    <x-ui.field :label="__('إلى')" name="ends_at" required :error="$errors->first('ends_at')">
                        <x-ui.input type="time" name="ends_at" :value="old('ends_at', '17:30')" />
                    </x-ui.field>
                </div>

                <x-ui.field :label="__('القاعة')" name="room_id"
                            :hint="__('القاعة تُحجز طوال هذه الفترة كل أسبوع.')">
                    <x-ui.select name="room_id">
                        <option value="">{{ __('بلا قاعة — أونلاين أو خارج الفرع') }}</option>
                        @foreach($rooms as $room)
                            <option value="{{ $room->id }}" @selected((string) old('room_id') === (string) $room->id)>
                                {{ $room->name }} ({{ $room->capacity }})
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.field :label="__('يسري من')" name="effective_from"
                                :hint="__('اتركه فارغاً ليسري من بداية المجموعة.')">
                        <x-ui.input type="date" name="effective_from" :value="old('effective_from')" />
                    </x-ui.field>

                    <x-ui.field :label="__('حتى')" name="effective_to" :error="$errors->first('effective_to')">
                        <x-ui.input type="date" name="effective_to" :value="old('effective_to')" />
                    </x-ui.field>
                </div>

                <div><x-ui.button type="submit">{{ __('أضف الموعد') }}</x-ui.button></div>

                <p class="text-2xs text-subtle">
                    {{ __('يُفحص التعارض قبل الحفظ: القاعة والمدرّس والمجموعة وسعة القاعة وفرعها.') }}
                </p>
            </form>
        </x-ui.card>

        <x-ui.card :title="__('المواعيد الحالية')" :padding="false">
            @if($slots->isEmpty())
                <div class="p-5">
                    <x-ui.empty :title="__('لا مواعيد بعد')">
                        {{ __('أضف موعداً أسبوعياً ثم ولّد الحصص — بغيره لا حصص ولا حضور.') }}
                    </x-ui.empty>
                </div>
            @else
                <ul class="divide-y divide-line">
                    @foreach($slots as $slot)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold">
                                    {{ $slot->weekdayLabel() }}
                                    <span class="font-mono tabular text-muted ms-1">{{ $slot->timeLabel() }}</span>
                                </p>
                                <p class="text-2xs text-subtle mt-0.5">
                                    {{ $slot->room?->name ?? __('بلا قاعة') }}
                                    @if($slot->effective_from || $slot->effective_to)
                                        · {{ $slot->effective_from?->translatedFormat('j M Y') ?? __('من البداية') }}
                                        → {{ $slot->effective_to?->translatedFormat('j M Y') ?? __('بلا نهاية') }}
                                    @endif
                                </p>
                            </div>

                            <form method="POST" action="{{ url('/admin/groups/'.$group->id.'/slots/'.$slot->id) }}" class="shrink-0">
                                @csrf @method('DELETE')
                                <x-ui.button type="submit" size="sm" variant="danger">{{ __('حذف') }}</x-ui.button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</div>
</x-layouts.admin>
