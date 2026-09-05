<x-layouts.admin :title="__('رموز المذكرات')" current="print-codes">
<div class="max-w-[1000px]">

    <x-ui.page-header :title="__('رموز المذكرات المطبوعة')"
                      :subtitle="__('رمزٌ تطبعه بجوار المسألة في مذكرتك، يمسحه الطالب فيفتح شرحها عندك.')">
        <x-slot:actions>
            @if($codes->total() > 0)
                <x-ui.button size="sm" variant="secondary" :href="url('/admin/print-codes/sheet')"
                             target="_blank" rel="noopener">
                    {{ __('ورقة الطباعة') }}
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('status'))<x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>@endif

    @if($errors->any())
        <x-ui.alert tone="danger" :title="__('راجع ما يلي')" class="mb-5">
            <ul class="list-disc list-inside grid gap-1 mt-1">
                @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </x-ui.alert>
    @endif

    <x-ui.card :title="__('رمز جديد')" class="mb-6">
        <form method="POST" action="{{ url('/admin/print-codes') }}" class="grid gap-4"
              x-data="{ target: 'lesson' }">
            @csrf

            <x-ui.field :label="__('الوصف')" for="label" required class="mb-0"
                        :hint="__('لك أنت، ليعرف أين تلصقه — مثل «مسألة ٧ صفحة ٢٤».')">
                <x-ui.input id="label" name="label" required maxlength="180" value="{{ old('label') }}" />
            </x-ui.field>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field :label="__('يفتح')" for="target_type" required class="mb-0">
                    <select id="target_type" name="target_type" x-model="target"
                            class="w-full min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
                        @foreach(App\Modules\Lms\Models\PrintCode::TARGETS as $key => $label)
                            <option value="{{ $key }}">{{ __($label) }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                {{--
                    قائمةٌ لكل نوع، والمخفيّة مُعطَّلة.

                    `x-show` على `<option>` لا يُعوَّل عليه — بعض
                    المتصفّحات تتجاهل إخفاء الخيار، فيرى المدرّس
                    الدروس والاختبارات والكورسات مختلطةً في قائمة
                    واحدة. والحقل المعطَّل لا يُرسَل أصلاً، فلا
                    يصل إلى الخادم هدفٌ من نوعٍ آخر.
                --}}
                @foreach(['lesson' => $lessons, 'quiz' => $quizzes, 'course' => $courses] as $kind => $list)
                    <x-ui.field :label="__('الهدف')" :for="'target_'.$kind" class="mb-0"
                                x-show="target === '{{ $kind }}'" x-cloak>
                        <select id="target_{{ $kind }}" name="target_id"
                                x-bind:disabled="target !== '{{ $kind }}'"
                                class="w-full min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
                            @forelse($list as $row)
                                <option value="{{ $row->id }}">{{ $row->title }}</option>
                            @empty
                                <option value="">{{ __('— لا شيء بعد —') }}</option>
                            @endforelse
                        </select>
                    </x-ui.field>
                @endforeach

                <x-ui.field :label="__('الرابط')" for="target_url" class="mb-0" x-show="target === 'url'" x-cloak>
                    <x-ui.input id="target_url" name="target_url" type="url"
                                x-bind:disabled="target !== 'url'"
                                placeholder="https://…" value="{{ old('target_url') }}" />
                </x-ui.field>

            </div>

            <div><x-ui.button type="submit">{{ __('أنشئ الرمز') }}</x-ui.button></div>
        </form>
    </x-ui.card>

    @if($codes->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا رموز بعد')">
                {{ __('أنشئ رمزاً لكل موضع تريد شرحه في مذكرتك — ثم اطبع ورقة الرموز وقصّها والصقها.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach($codes as $code)
                <x-ui.card>
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 bg-white rounded p-1.5 leading-none">
                            {!! $code->svg(96) !!}
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="font-semibold truncate">{{ $code->label }}</p>

                            <p class="text-2xs text-subtle font-mono mt-0.5">{{ $code->code }}</p>

                            <p class="text-xs text-muted mt-1">
                                {{ $code->targetLabel() }}
                                @unless($code->is_active) · <span class="text-danger">{{ __('موقوف') }}</span> @endunless
                            </p>

                            <p class="text-2xs text-subtle font-mono tabular mt-1">
                                {{ __('مُسح :count مرّة', ['count' => number_format($code->scans)]) }}
                                @if($code->last_scan_at) · {{ $code->last_scan_at->diffForHumans() }} @endif
                            </p>

                            <div class="flex flex-wrap gap-2 mt-3">
                                <x-ui.button size="sm" variant="ghost" :href="$code->url()"
                                             target="_blank" rel="noopener">{{ __('جرّبه') }}</x-ui.button>

                                <form method="POST" action="{{ url('/admin/print-codes/'.$code->id) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="is_active" value="{{ $code->is_active ? 0 : 1 }}">
                                    <x-ui.button size="sm" variant="ghost" type="submit">
                                        {{ $code->is_active ? __('أوقفه') : __('شغّله') }}
                                    </x-ui.button>
                                </form>

                                <form method="POST" action="{{ url('/admin/print-codes/'.$code->id) }}"
                                      onsubmit="return confirm('{{ __('حذفه يجعل ما طُبع منه لا يفتح شيئاً. متابعة؟') }}')">
                                    @csrf @method('DELETE')
                                    <x-ui.button size="sm" variant="danger" type="submit">{{ __('احذف') }}</x-ui.button>
                                </form>
                            </div>
                        </div>
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        <div class="mt-5">{{ $codes->links() }}</div>
    @endif

</div>
</x-layouts.admin>
