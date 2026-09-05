<x-layouts.admin :title="__('أجهزة الحضور')" current="devices">
<div class="max-w-[1000px]">

    <x-ui.page-header :title="__('أجهزة الحضور')"
                      :subtitle="__('بصمة أو كارت أو ماسح QR — يكتب في المنصة مباشرةً بلا ملفّات Excel.')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif

    @if(session('device_token'))
        <x-ui.alert tone="warning" :title="__('مفتاح الجهاز')" class="mb-5">
            <p class="text-sm mb-3">{{ __('ضعه في إعداد الجهاز أو في السكربت الوسيط. لا نحفظه نصّاً عندنا، فلن يُعرض مرة أخرى.') }}</p>

            <div x-data="{ copied: false }" class="flex flex-wrap items-center gap-2">
                <code class="min-w-0 flex-1 text-xs font-mono break-all bg-surface-sunken rounded-md px-3 py-2"
                      x-ref="tok">{{ session('device_token') }}</code>
                <x-ui.button type="button" size="sm" variant="secondary"
                             x-on:click="navigator.clipboard.writeText($refs.tok.textContent.trim()); copied = true; setTimeout(() => copied = false, 2000)">
                    <span x-text="copied ? '{{ __('نُسخ') }}' : '{{ __('انسخ') }}'"></span>
                </x-ui.button>
            </div>
        </x-ui.alert>
    @endif

    <x-ui.card :title="__('تسجيل جهاز')" class="mb-6">
        <form method="POST" action="{{ route('admin.center.devices.store') }}" class="grid gap-4">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field :label="__('اسم الجهاز')" for="name" required class="mb-0"
                            :hint="__('مثال: بصمة المدخل الرئيسي.')">
                    <x-ui.input id="name" name="name" required maxlength="100" />
                </x-ui.field>

                <x-ui.field :label="__('النوع')" for="kind" class="mb-0">
                    <select id="kind" name="kind"
                            class="w-full min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
                        @foreach($kinds as $value => $label)
                            <option value="{{ $value }}">{{ __($label) }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>

            @if($branches->isNotEmpty() || $rooms->isNotEmpty())
                <div class="grid gap-4 sm:grid-cols-2">
                    @if($branches->isNotEmpty())
                        <x-ui.field :label="__('الفرع')" for="branch" class="mb-0">
                            <select id="branch" name="branch_id"
                                    class="w-full min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
                                <option value="">{{ __('— بلا فرع —') }}</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    @endif

                    @if($rooms->isNotEmpty())
                        <x-ui.field :label="__('القاعة')" for="room" class="mb-0">
                            <select id="room" name="room_id"
                                    class="w-full min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
                                <option value="">{{ __('— بلا قاعة —') }}</option>
                                @foreach($rooms as $room)
                                    <option value="{{ $room->id }}">{{ $room->name }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    @endif
                </div>
            @endif

            <div><x-ui.button type="submit">{{ __('سجّل الجهاز') }}</x-ui.button></div>
        </form>
    </x-ui.card>

    @if($devices->isNotEmpty())
        <div class="grid gap-2 mb-6">
            @foreach($devices as $device)
                <div class="surface-card p-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">
                            {{ $device->name }}
                            <span class="text-2xs text-muted font-normal">· {{ $device->kindLabel() }}</span>
                        </p>
                        <p class="text-2xs text-subtle font-mono mt-0.5">{{ $device->masked() }}</p>
                        <p class="text-2xs text-muted mt-1">
                            {{-- «آخر اتصال» هو ما يسأل عنه صاحب السنتر: أشغّال أم لا؟ --}}
                            {{ $device->last_seen_at
                                ? __('آخر اتصال :when', ['when' => $device->last_seen_at->diffForHumans()])
                                : __('لم يتّصل بعد') }}
                            @if($device->branch) · {{ $device->branch->name }} @endif
                            @if($device->room) · {{ $device->room->name }} @endif
                        </p>
                    </div>

                    <form method="POST" action="{{ route('admin.center.devices.destroy', $device->id) }}"
                          onsubmit="return confirm('{{ __('حذف هذا الجهاز؟ لن تُقبل بصماته بعد الآن.') }}')">
                        @csrf @method('DELETE')
                        <x-ui.button type="submit" size="sm" variant="danger">{{ __('حذف') }}</x-ui.button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    <x-ui.card :title="__('آخر البصمات')" class="mb-6">
        @if($punches->isEmpty())
            <x-ui.empty :title="__('لا بصمات بعد')">
                {{ __('يظهر هنا كل ما يصل من الأجهزة — حتى ما لم يُطابَق، لتعرف أن الجهاز يعمل وأين الخلل.') }}
            </x-ui.empty>
        @else
            <div class="grid gap-1">
                @foreach($punches as $punch)
                    <div class="flex flex-wrap items-center gap-3 py-2 border-b border-line last:border-0 text-sm">
                        <span class="font-mono text-2xs tabular text-subtle shrink-0">
                            {{ $punch->punched_at?->format('H:i:s') }}
                        </span>
                        <span class="font-mono text-xs shrink-0">{{ $punch->code }}</span>
                        <span class="min-w-0 flex-1 text-xs text-muted truncate">
                            {{ $punch->device?->name }}
                            @if($punch->session?->group) · {{ $punch->session->group->name }} @endif
                        </span>
                        <x-ui.badge :tone="$punch->resultTone()">{{ $punch->resultLabel() }}</x-ui.badge>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    <x-ui.card :title="__('كيف تربط جهازك')">
        <p class="text-sm text-muted leading-relaxed mb-3">
            {{ __('أجهزة السوق طُرُزٌ كثيرة ببروتوكولات مختلفة، فالباب هنا واحد بسيط: أرسل كود الطالب ووقته. أي جهاز يستطيع ذلك — مباشرةً أو بسكربتٍ صغير يقرأ سجلّه ويُرسل.') }}
        </p>

        <pre class="text-2xs font-mono bg-surface-sunken rounded-md p-3 overflow-x-auto" dir="ltr">curl -X POST {{ url('/api/v1/punch') }} -H "Authorization: Bearer dev_..." -H "Content-Type: application/json" -d '{"code":"ST00001","at":"{{ now()->toIso8601String() }}"}'</pre>

        <p class="text-2xs text-subtle leading-relaxed mt-3">
            {{ __('البصمة تُطابَق بأقرب حصة للطالب خلال ٤٥ دقيقة من موعدها، وتُعلَّم «متأخّراً» بعد مهلة السماح. وما سجّله المدرّس بيده لا يُبدَّل.') }}
        </p>
    </x-ui.card>

</div>
</x-layouts.admin>
