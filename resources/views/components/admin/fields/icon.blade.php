@props([
    'name',
    'label',
    'hint' => null,
    'required' => false,
    'value' => null,
    'palette' => [],
])
@php $current = old($name, $value); @endphp
{{-- نطاق Alpine على عنصر عادي: Blade لا يصرّف @js() داخل خاصية مكوّن --}}
<x-ui.field :label="$label" :for="'f-'.$name" :hint="$hint" :required="$required" :error="$errors->first($name)">
    <div x-data="{
             icon: @js((string) ($current ?? '')),
             open: false,
             q: '',
             matches(group, icons) {
                 return this.q === '' || group.includes(this.q) || icons.some(i => i === this.q);
             },
         }"
         @keydown.escape.window="open = false">
        <div class="flex items-center gap-2">
            {{-- المعاينة تعرض ما سيراه الزائر بحجمه، لا اسم الرمز --}}
            <div class="size-11 shrink-0 grid place-items-center rounded-md border border-line-strong bg-surface-sunken text-xl leading-none"
                 aria-hidden="true">
                <span x-text="icon || '—'" :class="icon ? '' : 'text-subtle text-sm'"></span>
            </div>

            <x-ui.input :id="'f-'.$name" :name="$name" x-model="icon" maxlength="8"
                        :invalid="$errors->has($name)" :placeholder="__('رمز أو إيموجي')" class="font-mono" />

            <x-ui.button type="button" variant="ghost" size="sm" class="shrink-0"
                         x-on:click="open = ! open"
                         x-bind:aria-expanded="open ? 'true' : 'false'"
                         :aria-label="__('اختيار أيقونة من اللوحة')">{{ __('اختر') }}</x-ui.button>

            <x-ui.button type="button" variant="ghost" size="sm" class="shrink-0"
                         x-show="icon !== ''" x-cloak x-on:click="icon = ''"
                         :aria-label="__('مسح الأيقونة')">✕</x-ui.button>
        </div>

        <div x-show="open" x-cloak x-transition.opacity
             class="mt-2 rounded-lg border border-line bg-surface p-3 shadow-lg">
            <input type="search" x-model="q" placeholder="{{ __('ابحث باسم المجموعة…') }}"
                   class="w-full bg-surface-sunken text-content text-sm rounded-md border border-line-strong px-3 py-2 mb-3
                          focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-subtle"
                   aria-label="{{ __('بحث في مجموعات الأيقونات') }}">

            <div class="max-h-64 overflow-y-auto grid gap-3">
                @foreach($palette as $group => $icons)
                    <div x-show="matches(@js($group), @js($icons))">
                        <p class="text-2xs text-subtle mb-1.5">{{ $group }}</p>
                        <div class="flex flex-wrap gap-1">
                            @foreach($icons as $glyph)
                                <button type="button"
                                        x-on:click="icon = @js($glyph); open = false"
                                        x-bind:class="icon === @js($glyph)
                                            ? 'border-primary ring-[3px] ring-primary-subtle'
                                            : 'border-line hover:border-line-strong hover:bg-surface-sunken'"
                                        class="size-9 grid place-items-center rounded-md border text-lg leading-none transition-colors
                                               focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-subtle"
                                        aria-label="{{ __('اختيار الأيقونة :icon', ['icon' => $glyph]) }}">{{ $glyph }}</button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-ui.field>
