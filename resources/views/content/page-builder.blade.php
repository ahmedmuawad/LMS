<x-layouts.admin :title="__('محرّر الصفحة')">

@php
    /*
     | مخطّط الكتل يُصدَّر مرّة واحدة إلى المحرّر: نفس التعريف الذي
     | تحفظ به الخادمة هو الذي يُحرَّر به، فلا يتفرّق الاثنان.
     */
    $locales = array_keys(config('locales.supported', ['ar' => [], 'en' => []]));

    $schema = collect($registry->all())->map(function ($block) {
        $fields = [];

        foreach ($block->fields() as $field) {
            $props = $field->props();

            $fields[] = [
                'name' => $field->name,
                'label' => $field->getLabel(),
                'hint' => $field->getHint(),
                'kind' => str($field->component())->afterLast('.')->value(),
                'span' => $field->getSpan(),
                'options' => $props['options'] ?? null,
                'long' => (bool) ($props['long'] ?? false),
                'default' => $field->getDefault(),
            ];
        }

        return [
            'key' => $block->key(),
            'label' => $block->label(),
            'icon' => $block->icon(),
            'fields' => $fields,
            'defaults' => $block->defaults(),
        ];
    })->all();

    $palette = collect($groups)->map(fn (array $blocks): array => array_map(
        fn ($block): array => ['key' => $block->key(), 'label' => $block->label(), 'icon' => $block->icon()],
        $blocks,
    ))->all();

    $groupLabels = config('blocks.groups', []);
@endphp

<div x-data="pageBuilder({
        schema: {{ Js::from($schema) }},
        blocks: {{ Js::from($page->blocks ?? []) }},
        locales: {{ Js::from($locales) }},
     })">

    <form method="POST" action="{{ route('admin.page-builder.update', ['id' => $page->getKey()]) }}">
        @csrf
        @method('PUT')

        <x-ui.page-header :title="$page->title ?: __('صفحة بلا عنوان')"
                          :subtitle="__('اسحب الكتل ورتّبها — ما تراه هنا هو ما يُنشر.')"
                          :back="route('admin.resource.index', ['resource' => 'pages'])">
            <x-slot:actions>
                <x-ui.button as="a" :href="url('/'.$page->slug)" variant="secondary" target="_blank" rel="noopener">
                    {{ __('معاينة') }}
                </x-ui.button>
                <x-ui.button type="submit">{{ __('حفظ') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if(session('status'))
            <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
        @endif

        <div class="grid gap-5 lg:grid-cols-[1fr_320px] items-start">

            {{-- ---------- عمود الكتل ---------- --}}
            <div class="min-w-0 flex flex-col gap-3">

                <template x-if="blocks.length === 0">
                    <x-ui.card>
                        <x-ui.empty :title="__('الصفحة فارغة')">{{ __('أضف كتلة من اللوح المجاور لتبدأ.') }}</x-ui.empty>
                    </x-ui.card>
                </template>

                <template x-for="(block, index) in blocks" :key="block.uid">
                    <article class="surface-card overflow-hidden">
                        <header class="flex items-center gap-2 p-3 bg-surface-sunken border-b border-default">
                            <span class="text-lg shrink-0" aria-hidden="true" x-text="schema[block.type]?.icon"></span>
                            <span class="font-semibold text-sm truncate flex-1 min-w-0" x-text="schema[block.type]?.label"></span>

                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button" @click="move(index, -1)" :disabled="index === 0"
                                        class="size-9 grid place-items-center rounded-md text-muted hover:bg-surface hover:text-content disabled:opacity-40 disabled:pointer-events-none transition-colors"
                                        :aria-label="'{{ __('حرّك لأعلى') }}'">↑</button>
                                <button type="button" @click="move(index, 1)" :disabled="index === blocks.length - 1"
                                        class="size-9 grid place-items-center rounded-md text-muted hover:bg-surface hover:text-content disabled:opacity-40 disabled:pointer-events-none transition-colors"
                                        :aria-label="'{{ __('حرّك لأسفل') }}'">↓</button>
                                <button type="button" @click="duplicate(index)"
                                        class="size-9 grid place-items-center rounded-md text-muted hover:bg-surface hover:text-content transition-colors"
                                        :aria-label="'{{ __('تكرار') }}'">⧉</button>
                                <button type="button" @click="remove(index)"
                                        class="size-9 grid place-items-center rounded-md text-danger hover:bg-danger-subtle transition-colors"
                                        :aria-label="'{{ __('حذف') }}'">✕</button>
                                <button type="button" @click="block.open = ! block.open"
                                        class="size-9 grid place-items-center rounded-md text-muted hover:bg-surface hover:text-content transition-colors"
                                        :aria-expanded="block.open ? 'true' : 'false'"
                                        :aria-label="'{{ __('تحرير الكتلة') }}'"
                                        x-text="block.open ? '▴' : '▾'"></button>
                            </div>
                        </header>

                        <div class="p-4 grid gap-4 sm:grid-cols-2" x-show="block.open" x-cloak>
                            <template x-for="field in (schema[block.type]?.fields || [])" :key="field.name">
                                <div :class="field.span === 'half' ? 'sm:col-span-1' : 'sm:col-span-2'">
                                    <label class="text-sm font-semibold block mb-1.5" x-text="field.label"></label>

                                    {{-- نصّ بلغتين --}}
                                    <template x-if="field.kind === 'translatable'">
                                        <div class="flex flex-col gap-2">
                                            <template x-for="locale in locales" :key="locale">
                                                <div class="flex items-start gap-2">
                                                    <span class="font-mono text-2xs text-subtle mt-3 w-6 shrink-0" x-text="locale"></span>
                                                    <template x-if="field.long">
                                                        <textarea rows="3" x-model="block.content[field.name][locale]"
                                                                  class="w-full bg-surface text-content text-sm rounded-md border border-line-strong px-3 py-2.5 focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-subtle"></textarea>
                                                    </template>
                                                    <template x-if="! field.long">
                                                        <input type="text" x-model="block.content[field.name][locale]"
                                                               class="w-full min-h-11 bg-surface text-content text-sm rounded-md border border-line-strong px-3 focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-subtle">
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    <template x-if="field.kind === 'text'">
                                        <input type="text" x-model="block.content[field.name]"
                                               class="w-full min-h-11 bg-surface text-content text-sm rounded-md border border-line-strong px-3 focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-subtle">
                                    </template>

                                    <template x-if="field.kind === 'textarea'">
                                        <textarea rows="4" x-model="block.content[field.name]"
                                                  class="w-full bg-surface text-content text-sm rounded-md border border-line-strong px-3 py-2.5 focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-subtle"></textarea>
                                    </template>

                                    <template x-if="field.kind === 'number'">
                                        <input type="number" x-model="block.content[field.name]"
                                               class="w-full min-h-11 bg-surface text-content text-sm rounded-md border border-line-strong px-3 font-mono tabular focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-subtle">
                                    </template>

                                    <template x-if="field.kind === 'select'">
                                        <select x-model="block.content[field.name]"
                                                class="w-full min-h-11 bg-surface text-content text-sm rounded-md border border-line-strong px-3 focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-subtle">
                                            <template x-for="(label, value) in (field.options || {})" :key="value">
                                                <option :value="value" x-text="label"></option>
                                            </template>
                                        </select>
                                    </template>

                                    <template x-if="field.kind === 'switch'">
                                        <label class="inline-flex items-center gap-2 min-h-11 cursor-pointer">
                                            <input type="checkbox" x-model="block.content[field.name]" class="size-5 accent-[var(--color-primary)]">
                                            <span class="text-sm text-muted">{{ __('مفعّل') }}</span>
                                        </label>
                                    </template>

                                    <p class="text-xs text-subtle mt-1" x-show="field.hint" x-text="field.hint" x-cloak></p>
                                </div>
                            </template>

                            {{-- إعدادات العرض المشتركة بين كل الكتل --}}
                            <div class="sm:col-span-2 grid gap-3 sm:grid-cols-3 pt-3 border-t border-default">
                                <div>
                                    <label class="text-sm font-semibold block mb-1.5">{{ __('الخلفية') }}</label>
                                    <select x-model="block.settings.background"
                                            class="w-full min-h-11 bg-surface text-content text-sm rounded-md border border-line-strong px-3 focus:outline-none focus:border-primary">
                                        <option value="">{{ __('عادية') }}</option>
                                        <option value="sunken">{{ __('غائرة') }}</option>
                                        <option value="primary">{{ __('لون العلامة') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold block mb-1.5">{{ __('العرض') }}</label>
                                    <select x-model="block.settings.width"
                                            class="w-full min-h-11 bg-surface text-content text-sm rounded-md border border-line-strong px-3 focus:outline-none focus:border-primary">
                                        <option value="wide">{{ __('عريض') }}</option>
                                        <option value="narrow">{{ __('ضيّق') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold block mb-1.5">{{ __('معرّف الرابط') }}</label>
                                    <input type="text" x-model="block.settings.anchor" placeholder="pricing"
                                           class="w-full min-h-11 bg-surface text-content text-sm rounded-md border border-line-strong px-3 font-mono focus:outline-none focus:border-primary">
                                </div>
                            </div>
                        </div>
                    </article>
                </template>

                {{-- البنية كاملة تُرسل نصّاً واحداً لكل كتلة: الترتيب جزء منها --}}
                <template x-for="block in blocks" :key="'payload-' + block.uid">
                    <input type="hidden" name="blocks[]" :value="serialize(block)">
                </template>
            </div>

            {{-- ---------- لوح الإضافة وبيانات الصفحة ---------- --}}
            <aside class="flex flex-col gap-4 lg:sticky lg:top-6">

                <div class="surface-card p-4 flex flex-col gap-3">
                    <h2 class="font-bold text-sm">{{ __('بيانات الصفحة') }}</h2>

                    @foreach($locales as $locale)
                        <x-ui.field :label="__('العنوان').' ('.$locale.')'" for="title-{{ $locale }}" class="mb-0"
                                    :error="$errors->first('title.'.$locale)">
                            <x-ui.input name="title[{{ $locale }}]" id="title-{{ $locale }}"
                                        value="{{ old('title.'.$locale, $page->getTranslation('title', $locale)) }}" />
                        </x-ui.field>
                    @endforeach

                    <x-ui.field :label="__('الرابط')" for="slug" class="mb-0" :error="$errors->first('slug')"
                                :hint="$page->is_system ? __('صفحة إلزامية — رابطها ثابت.') : null">
                        <x-ui.input name="slug" id="slug" value="{{ old('slug', $page->slug) }}"
                                    class="font-mono" :readonly="(bool) $page->is_system" />
                    </x-ui.field>

                    <x-ui.field :label="__('الحالة')" for="status" class="mb-0" :error="$errors->first('status')">
                        <x-ui.select name="status" id="status">
                            @foreach(['draft' => 'مسودّة', 'published' => 'منشورة', 'scheduled' => 'مجدولة'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $page->status) === $value)>{{ __($label) }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                </div>

                <div class="surface-card p-4">
                    <h2 class="font-bold text-sm mb-3">{{ __('أضف كتلة') }}</h2>

                    <div class="flex flex-col gap-4">
                        @foreach($palette as $group => $items)
                            <div>
                                <p class="text-2xs text-subtle mb-2">{{ __($groupLabels[$group] ?? $group) }}</p>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach($items as $item)
                                        <button type="button" @click="add('{{ $item['key'] }}')"
                                                class="flex items-center gap-2 min-h-11 px-3 rounded-md border border-line-strong text-xs font-semibold hover:bg-surface-sunken transition-colors text-start">
                                            <span class="text-base shrink-0" aria-hidden="true">{{ $item['icon'] }}</span>
                                            <span class="truncate min-w-0">{{ $item['label'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </aside>
        </div>
    </form>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('pageBuilder', (config) => ({
            schema: config.schema,
            locales: config.locales,
            blocks: [],
            seq: 0,

            init() {
                this.blocks = (config.blocks || [])
                    .filter((b) => this.schema[b.type])
                    .map((b) => this.hydrate(b.type, b.content || {}, b.settings || {}, false));
            },

            /* الكتلة تُملأ بكل حقول نوعها: حقل غائب عن الكائن لا يربطه x-model */
            hydrate(type, content, settings, open) {
                const filled = {};

                for (const field of this.schema[type].fields) {
                    if (field.kind === 'translatable') {
                        const value = content[field.name] || {};
                        filled[field.name] = {};
                        for (const locale of this.locales) filled[field.name][locale] = value[locale] ?? '';
                    } else {
                        filled[field.name] = content[field.name] ?? field.default ?? '';
                    }
                }

                return {
                    uid: 'b' + (++this.seq),
                    type,
                    open,
                    content: filled,
                    settings: {
                        background: settings.background ?? '',
                        width: settings.width ?? 'wide',
                        anchor: settings.anchor ?? '',
                    },
                };
            },

            add(type) {
                if (! this.schema[type]) return;
                this.blocks.push(this.hydrate(type, {}, {}, true));
            },

            duplicate(index) {
                const source = this.blocks[index];
                const copy = this.hydrate(source.type, JSON.parse(JSON.stringify(source.content)), source.settings, false);
                this.blocks.splice(index + 1, 0, copy);
            },

            remove(index) {
                this.blocks.splice(index, 1);
            },

            move(index, step) {
                const target = index + step;
                if (target < 0 || target >= this.blocks.length) return;
                const [block] = this.blocks.splice(index, 1);
                this.blocks.splice(target, 0, block);
            },

            /* uid وopen حالة محرّر لا محتوى: لا تُحفظان */
            serialize(block) {
                return JSON.stringify({
                    type: block.type,
                    content: block.content,
                    settings: block.settings,
                });
            },
        }));
    });
</script>
@endpush

</x-layouts.admin>
