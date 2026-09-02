@props(['name', 'value', 'label', 'hint' => null, 'icon' => null, 'checked' => false])
<label class="relative flex items-start gap-3 p-4 rounded-lg border cursor-pointer transition-colors
              border-line-strong has-checked:border-primary has-checked:bg-primary-subtle hover:border-primary">
    <input type="radio" name="{{ $name }}" value="{{ $value }}" @checked($checked)
           class="mt-0.5 size-4 shrink-0 accent-[var(--color-primary)]">
    <span class="min-w-0">
        <span class="flex items-center gap-2 text-sm font-semibold">
            @if($icon)<span aria-hidden="true">{{ $icon }}</span>@endif{{ $label }}
        </span>
        @if($hint)<span class="block text-xs text-muted mt-1">{{ $hint }}</span>@endif
    </span>
</label>
