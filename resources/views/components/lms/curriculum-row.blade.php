@props(['item', 'course'])
<li class="px-5 py-3 flex flex-wrap items-center gap-3">
    <span aria-hidden="true" class="text-subtle w-4 text-center shrink-0">{{ $item->icon() }}</span>

    <span class="flex-1 min-w-0">
        <span class="block text-sm font-medium truncate">{{ $item->title() }}</span>
        <span class="block text-2xs text-subtle">{{ $item->label() }}</span>
    </span>

    <form method="POST" action="{{ url('/admin/courses/'.$course->id.'/items/'.$item->id) }}"
          class="flex items-center gap-2 shrink-0">
        @csrf @method('PUT')

        <label class="flex items-center gap-1.5 text-2xs text-muted cursor-pointer py-2">
            <input type="hidden" name="is_preview" value="0">
            <input type="checkbox" name="is_preview" value="1" @checked($item->is_preview)
                   class="size-4 accent-[var(--color-primary)] rounded-sm">
            {{ __('معاينة') }}
        </label>

        <label class="flex items-center gap-1 text-2xs text-muted">
            <span class="sr-only">{{ __('يُفتح بعد كم يوم') }}</span>
            <input type="number" name="available_after_days" min="0" max="3650" value="{{ (int) $item->available_after_days }}"
                   class="w-16 bg-surface border border-line-strong rounded-md px-2 py-1.5 font-mono text-xs"
                   aria-label="{{ __('يُفتح بعد كم يوم من التسجيل') }}">
            {{ __('يوم') }}
        </label>

        <x-ui.button size="sm" variant="ghost" type="submit">{{ __('حفظ') }}</x-ui.button>
    </form>

    <form method="POST" action="{{ url('/admin/courses/'.$course->id.'/items/'.$item->id) }}" class="shrink-0"
          x-data @submit="if (! confirm(@js(__('سيُزال من هذا الكورس ويبقى في مكتبتك. متابعة؟')))) $event.preventDefault()">
        @csrf @method('DELETE')
        <x-ui.button size="sm" variant="ghost" type="submit">{{ __('إزالة') }}</x-ui.button>
    </form>
</li>
