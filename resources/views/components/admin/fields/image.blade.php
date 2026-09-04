@props([
    'name',
    'label',
    'hint' => null,
    'required' => false,
    'value' => null,
    'storesId' => false,
    'folder' => null,
    'ratio' => '16/9',
])
@php
    $current = old($name, $value);
    $config = [
        'value' => (string) ($current ?? ''),
        'preview' => \App\Core\Admin\Fields\ImageField::previewUrl($current, $storesId),
        'storesId' => (bool) $storesId,
        'folder' => $folder,
        'browseUrl' => url('/admin/media/browse'),
        'uploadUrl' => url('/admin/media/upload'),
    ];
@endphp
{{-- نطاق Alpine على عنصر عادي: Blade لا يصرّف @js() داخل خاصية مكوّن --}}
<x-ui.field :label="$label" :for="'f-'.$name" :hint="$hint" :required="$required" :error="$errors->first($name)">
    <div x-data="imageField(@js($config))" @keydown.escape.window="open = false">
        <input type="hidden" id="f-{{ $name }}" name="{{ $name }}" x-model="value">

        <div class="flex items-start gap-3">
            {{-- المعاينة بنسبة العرض التي ستُعرض بها الصورة فعلاً --}}
            <div class="w-28 shrink-0 rounded-md border border-line-strong bg-surface-sunken overflow-hidden grid place-items-center"
                 style="aspect-ratio: {{ $ratio }}">
                <template x-if="preview">
                    <img :src="preview" alt="{{ __('معاينة :field', ['field' => $label]) }}"
                         class="w-full h-full object-cover">
                </template>
                <template x-if="! preview">
                    <span class="text-2xs text-subtle px-2 text-center">{{ __('لا صورة') }}</span>
                </template>
            </div>

            <div class="grow min-w-0 grid gap-2">
                <div class="flex flex-wrap gap-2">
                    <x-ui.button type="button" variant="ghost" size="sm"
                                 x-on:click="$refs.file.click()" x-bind:disabled="busy">
                        <span x-show="! busy">{{ __('ارفع صورة') }}</span>
                        <span x-show="busy" x-cloak>{{ __('جارٍ الرفع…') }}</span>
                    </x-ui.button>

                    <x-ui.button type="button" variant="ghost" size="sm"
                                 x-on:click="openLibrary()">{{ __('من المكتبة') }}</x-ui.button>

                    <x-ui.button type="button" variant="ghost" size="sm" x-show="value !== ''" x-cloak
                                 x-on:click="clear()">{{ __('إزالة') }}</x-ui.button>
                </div>

                <input type="file" x-ref="file" accept="image/*" class="sr-only"
                       x-on:change="upload($event.target.files[0])"
                       aria-label="{{ __('اختيار ملف صورة لـ :field', ['field' => $label]) }}">

                {{-- القيمة تبقى مقروءة وقابلة للتحرير: من يعرف المسار لا يُجبَر على المنتقي --}}
                <input type="text" x-model="value" x-on:change="refreshPreview()" dir="ltr"
                       placeholder="{{ $storesId ? __('معرّف الوسيط') : __('مسار الصورة أو رابطها') }}"
                       class="w-full bg-surface text-content text-xs font-mono rounded-md border border-line px-3 py-2
                              focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-subtle"
                       aria-label="{{ __('قيمة :field', ['field' => $label]) }}">

                <p x-show="error" x-cloak x-text="error" class="text-xs text-danger"></p>
            </div>
        </div>

        {{-- منتقي المكتبة --}}
        <div x-show="open" x-cloak x-transition.opacity
             class="mt-2 rounded-lg border border-line bg-surface p-3 shadow-lg">
            <div class="flex gap-2 mb-3">
                <input type="search" x-model.debounce.400ms="q" x-on:input.debounce.400ms="load(1)"
                       placeholder="{{ __('ابحث باسم الملف…') }}"
                       class="grow bg-surface-sunken text-content text-sm rounded-md border border-line-strong px-3 py-2
                              focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-subtle"
                       aria-label="{{ __('بحث في مكتبة الوسائط') }}">
                <x-ui.button type="button" variant="ghost" size="sm"
                             x-on:click="open = false">{{ __('إغلاق') }}</x-ui.button>
            </div>

            <div class="max-h-72 overflow-y-auto">
                <p x-show="loading" class="text-xs text-subtle py-4 text-center">{{ __('جارٍ التحميل…') }}</p>
                <p x-show="! loading && items.length === 0" x-cloak class="text-xs text-subtle py-4 text-center">
                    {{ __('لا صور في المكتبة بعد — ارفع واحدة.') }}
                </p>
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                    <template x-for="item in items" :key="item.id">
                        <button type="button" x-on:click="choose(item)"
                                x-bind:class="String(value) === String(storesId ? item.id : item.url)
                                    ? 'border-primary ring-[3px] ring-primary-subtle'
                                    : 'border-line hover:border-line-strong'"
                                class="rounded-md border overflow-hidden aspect-square bg-surface-sunken transition-colors
                                       focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-subtle">
                            <img :src="item.url" :alt="item.name" class="w-full h-full object-cover" loading="lazy">
                        </button>
                    </template>
                </div>
                <div x-show="next" x-cloak class="pt-3 text-center">
                    <x-ui.button type="button" variant="ghost" size="sm"
                                 x-on:click="load(next)">{{ __('المزيد') }}</x-ui.button>
                </div>
            </div>
        </div>
    </div>
</x-ui.field>
