@php
    $label = fn ($item) => $item === null ? '—'
        : (\Illuminate\Support\Str::limit(strip_tags((string) ($item->itemable->title ?? '')), 45) ?: __('عنصر'));
@endphp

<x-layouts.admin :title="__('المسار التكيّفي')" current="courses">
<div class="max-w-[900px]">

    <x-ui.page-header :title="__('المسار التكيّفي: :course', ['course' => $course->title])"
                      :subtitle="__('المنهج يتفرّع بالنتيجة: من رسب يُفتح له علاج، ومن أتقن يُفتح له تقدّم.')"
                      :back="url('/admin/courses/'.$course->getKey().'/edit')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>@endif

    @if($quizzes->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا اختبارات في هذا المنهج')">
                {{ __('التفريع يقيس نتيجة اختبار — أضف اختباراً إلى المنهج أولاً.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <x-ui.card :title="__('قاعدة جديدة')" class="mb-6">
            <form method="POST" action="{{ url('/admin/courses/'.$course->getKey().'/rules') }}" class="grid gap-4">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field :label="__('في اختبار')" for="trigger" required class="mb-0">
                        <select id="trigger" name="trigger_item_id" required
                                class="w-full min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
                            @foreach($quizzes as $q)
                                <option value="{{ $q->id }}">{{ $label($q) }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <div class="grid grid-cols-2 gap-2">
                        <x-ui.field :label="__('إن كانت النتيجة')" for="cmp" class="mb-0">
                            <select id="cmp" name="comparison"
                                    class="w-full min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
                                <option value="below">{{ __('أقلّ من') }}</option>
                                <option value="above">{{ __('تساوي أو تزيد على') }}</option>
                            </select>
                        </x-ui.field>

                        <x-ui.field :label="__('النسبة')" for="th" class="mb-0">
                            <x-ui.input id="th" name="threshold" type="number" min="0" max="100" value="50" required />
                        </x-ui.field>
                    </div>
                </div>

                <x-ui.field :label="__('افتح هذا العنصر')" for="target" required class="mb-0"
                            :hint="__('يبقى مخفياً عن المنهج حتى يُستحقّ: «مراجعة للراسبين» لمن نجح إهانةٌ صغيرة تتكرّر.')">
                    <select id="target" name="target_item_id" required
                            class="w-full min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
                        @foreach($items as $i)
                            <option value="{{ $i->id }}">{{ $label($i) }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <label class="flex items-start gap-2.5 text-sm">
                    <input type="checkbox" name="blocks_progress" value="1" class="mt-1 accent-[var(--sem-primary)]">
                    <span>
                        <span class="block font-semibold">{{ __('يمنع التقدّم حتى يُتمّه') }}</span>
                        <span class="block text-xs text-muted leading-relaxed mt-0.5">
                            {{ __('إلزام الراسب بمراجعةٍ قبل أن يمضي صحيحٌ تربوياً وثقيلٌ نفسياً — القرار قرارك.') }}
                        </span>
                    </span>
                </label>

                <div><x-ui.button type="submit">{{ __('أضف القاعدة') }}</x-ui.button></div>
            </form>
        </x-ui.card>

        @if($rules->isEmpty())
            <x-ui.card>
                <x-ui.empty :title="__('لا قواعد بعد')">
                    {{ __('بلا قاعدة يبقى المنهج خطّاً واحداً للجميع — وهو الافتراض السليم حتى تحتاج غيره.') }}
                </x-ui.empty>
            </x-ui.card>
        @else
            <div class="grid gap-2">
                @foreach($rules as $rule)
                    <div class="surface-card p-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                        <p class="min-w-0 flex-1 text-sm leading-relaxed">
                            {{ __('في') }} <strong>{{ $label($rule->trigger) }}</strong> —
                            {{ $rule->describe() }}
                            {{ __('افتح') }} <strong>{{ $label($rule->target) }}</strong>
                            @if($rule->blocks_progress)
                                <span class="text-2xs text-warning">· {{ __('يمنع التقدّم') }}</span>
                            @endif
                        </p>

                        <form method="POST" action="{{ url('/admin/courses/'.$course->getKey().'/rules/'.$rule->id) }}"
                              onsubmit="return confirm('{{ __('حذف هذه القاعدة؟') }}')">
                            @csrf @method('DELETE')
                            <x-ui.button type="submit" size="sm" variant="danger">{{ __('حذف') }}</x-ui.button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

</div>
</x-layouts.admin>
