<div x-data class="inline-flex bg-surface border border-line rounded-full p-[3px] gap-0.5 shadow-sm"
     role="group" aria-label="{{ __('وضع العرض') }}">
    @foreach ([['light', __('فاتح')], ['system', __('تلقائي')], ['dark', __('داكن')]] as [$mode, $label])
        <button type="button"
                @click="$store.theme.set('{{ $mode }}')"
                :aria-pressed="$store.theme.current === '{{ $mode }}' ? 'true' : 'false'"
                :class="$store.theme.current === '{{ $mode }}'
                    ? 'bg-primary text-primary-on'
                    : 'text-muted hover:text-content'"
                class="text-xs font-semibold px-3 py-1.5 rounded-full transition-colors duration-150">
            {{ $label }}
        </button>
    @endforeach
</div>
