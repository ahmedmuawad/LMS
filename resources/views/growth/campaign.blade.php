<x-layouts.admin :title="$campaign->name">

<form method="POST" action="{{ route('admin.campaigns.update', ['id' => $campaign->id]) }}"
      x-data="campaignEditor({{ Js::from($campaign->steps->map(fn ($s) => [
          'id' => $s->id, 'delay_minutes' => (int) $s->delay_minutes,
          'event' => $s->event, 'is_active' => (bool) $s->is_active,
      ])->values()) }})">
    @csrf
    @method('PUT')

    <x-ui.page-header :title="$campaign->name ?: __('تسلسل بلا اسم')"
                      :subtitle="__(App\Modules\Growth\Models\Campaign::TRIGGERS[$campaign->trigger] ?? $campaign->trigger)"
                      :back="route('admin.campaigns.index')">
        <x-slot:actions>
            <x-ui.button type="submit">{{ __('حفظ') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('status'))
        <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @if($errors->any())
        <x-ui.alert tone="danger" class="mb-4">{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_300px] items-start">

        <div class="min-w-0 flex flex-col gap-3">
            <template x-if="steps.length === 0">
                <x-ui.card>
                    <x-ui.empty :title="__('لا خطوات بعد')">{{ __('أضف أول رسالة وحدّد بعد كم تُرسل.') }}</x-ui.empty>
                </x-ui.card>
            </template>

            <template x-for="(step, index) in steps" :key="step.uid">
                <article class="surface-card p-4">
                    <header class="flex items-center gap-3 mb-4">
                        <span class="size-9 shrink-0 rounded-md grid place-items-center bg-primary-subtle text-primary font-mono font-bold"
                              x-text="index + 1"></span>

                        <p class="flex-1 min-w-0 text-sm font-semibold">{{ __('الخطوة') }}</p>

                        <div class="flex items-center gap-1 shrink-0">
                            <button type="button" @click="move(index, -1)" :disabled="index === 0"
                                    class="size-9 grid place-items-center rounded-md text-muted hover:bg-surface-sunken disabled:opacity-40 disabled:pointer-events-none transition-colors"
                                    aria-label="{{ __('حرّك لأعلى') }}">↑</button>
                            <button type="button" @click="move(index, 1)" :disabled="index === steps.length - 1"
                                    class="size-9 grid place-items-center rounded-md text-muted hover:bg-surface-sunken disabled:opacity-40 disabled:pointer-events-none transition-colors"
                                    aria-label="{{ __('حرّك لأسفل') }}">↓</button>
                            <button type="button" @click="steps.splice(index, 1)"
                                    class="size-9 grid place-items-center rounded-md text-danger hover:bg-danger-subtle transition-colors"
                                    aria-label="{{ __('حذف') }}">✕</button>
                        </div>
                    </header>

                    <div class="grid gap-3 sm:grid-cols-[160px_1fr_auto] items-end">
                        <div>
                            <label class="text-sm font-semibold block mb-1.5">{{ __('بعد') }}</label>
                            <div class="flex gap-1">
                                <input type="number" min="1" x-model.number="step.amount" @input="sync(step)"
                                       class="w-full min-h-11 bg-surface text-content text-sm rounded-md border border-line-strong px-3 font-mono tabular focus:outline-none focus:border-primary">
                                <select x-model="step.unit" @change="sync(step)"
                                        class="min-h-11 bg-surface text-content text-sm rounded-md border border-line-strong px-2 focus:outline-none focus:border-primary">
                                    <option value="1">{{ __('دقيقة') }}</option>
                                    <option value="60">{{ __('ساعة') }}</option>
                                    <option value="1440">{{ __('يوم') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="min-w-0">
                            <label class="text-sm font-semibold block mb-1.5">{{ __('الرسالة') }}</label>
                            <select x-model="step.event"
                                    class="w-full min-h-11 bg-surface text-content text-sm rounded-md border border-line-strong px-3 focus:outline-none focus:border-primary">
                                @foreach($events as $key => $event)
                                    <option value="{{ $key }}">{{ __($event->label) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <label class="inline-flex items-center gap-2 min-h-11 cursor-pointer">
                            <input type="checkbox" x-model="step.is_active" class="size-5 accent-[var(--color-primary)]">
                            <span class="text-sm">{{ __('مفعّلة') }}</span>
                        </label>
                    </div>

                    <input type="hidden" :name="`steps[${index}][id]`" :value="step.id ?? ''">
                    <input type="hidden" :name="`steps[${index}][delay_minutes]`" :value="step.delay_minutes">
                    <input type="hidden" :name="`steps[${index}][event]`" :value="step.event">
                    <input type="hidden" :name="`steps[${index}][is_active]`" :value="step.is_active ? 1 : 0">
                </article>
            </template>

            <button type="button" @click="add()"
                    class="min-h-11 rounded-lg border border-dashed border-line-strong text-sm font-semibold text-muted hover:bg-surface-sunken transition-colors">
                {{ __('أضف خطوة') }}
            </button>
        </div>

        <aside class="surface-card p-4 flex flex-col gap-3 lg:sticky lg:top-6">
            <h2 class="font-bold text-sm">{{ __('بيانات التسلسل') }}</h2>

            @foreach($locales as $locale)
                <x-ui.field :label="__('الاسم').' ('.$locale.')'" for="name-{{ $locale }}" class="mb-0">
                    <x-ui.input name="name[{{ $locale }}]" id="name-{{ $locale }}"
                                value="{{ old('name.'.$locale, $campaign->getTranslation('name', $locale)) }}" />
                </x-ui.field>
            @endforeach

            <x-ui.field :label="__('الحالة')" for="status" class="mb-0">
                <x-ui.select name="status" id="status">
                    @foreach(['draft' => 'مسودّة', 'active' => 'يعمل', 'paused' => 'متوقّف'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $campaign->status) === $value)>{{ __($label) }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>

            <div class="pt-3 border-t border-default text-2xs text-subtle leading-relaxed">
                <p class="mb-1">{{ __('دخلوا: :n', ['n' => number_format((int) $campaign->entered_count)]) }}</p>
                <p class="mb-1">{{ __('تحوّلوا: :n', ['n' => number_format((int) $campaign->converted_count)]) }}</p>
                <p>{{ __('من حقّق الهدف يخرج من التسلسل فوراً ولا تصله بقيّة الرسائل.') }}</p>
            </div>
        </aside>
    </div>
</form>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('campaignEditor', (initial) => ({
            steps: [],
            seq: 0,

            init() {
                this.steps = (initial || []).map((step) => this.hydrate(step));
            },

            /* التأخير يُخزَّن بالدقائق ويُعرض بأقرب وحدة مفهومة:
               «١٤٤٠ دقيقة» لا تعني شيئاً لمن يكتبها، و«يوم» تعني. */
            hydrate(step) {
                const minutes = step.delay_minutes ?? 60;
                const unit = minutes % 1440 === 0 ? 1440 : (minutes % 60 === 0 ? 60 : 1);

                return {
                    uid: 's' + (++this.seq),
                    id: step.id ?? null,
                    delay_minutes: minutes,
                    amount: minutes / unit,
                    unit: String(unit),
                    event: step.event ?? '',
                    is_active: step.is_active ?? true,
                };
            },

            sync(step) {
                step.delay_minutes = Math.max(1, Math.round((step.amount || 1) * Number(step.unit)));
            },

            add() {
                this.steps.push(this.hydrate({ delay_minutes: 60, event: '', is_active: true }));
            },

            move(index, offset) {
                const target = index + offset;
                if (target < 0 || target >= this.steps.length) return;
                const [step] = this.steps.splice(index, 1);
                this.steps.splice(target, 0, step);
            },
        }));
    });
</script>
@endpush

</x-layouts.admin>
