@props(['name' => 'file', 'accept' => null, 'hint' => null, 'multiple' => false])
<div x-data="{ over: false, files: [] }"
     @dragover.prevent="over = true" @dragleave.prevent="over = false"
     @drop.prevent="over = false; files = Array.from($event.dataTransfer.files).map((f) => f.name)"
     :class="over ? 'border-primary bg-primary-subtle' : 'border-line-strong'"
     class="rounded-lg border border-dashed p-6 text-center transition-colors">
    <div class="size-10 rounded-lg bg-primary-subtle text-primary grid place-items-center mx-auto mb-3" aria-hidden="true">↑</div>
    <label class="text-sm font-semibold text-primary cursor-pointer hover:underline">
        {{ __('اختر ملفاً') }}
        <input type="file" name="{{ $name }}" @if($accept) accept="{{ $accept }}" @endif @if($multiple) multiple @endif
               class="sr-only" @change="files = Array.from($event.target.files).map((f) => f.name)">
    </label>
    <span class="text-sm text-muted">{{ __('أو اسحبه إلى هنا') }}</span>
    @if($hint)<p class="text-xs text-subtle mt-2">{{ $hint }}</p>@endif
    <ul class="mt-3 flex flex-wrap gap-2 justify-center" x-show="files.length" x-cloak>
        <template x-for="f in files" :key="f">
            <li class="text-xs px-2 py-1 rounded-md bg-surface-sunken text-muted border border-line" x-text="f"></li>
        </template>
    </ul>
</div>
