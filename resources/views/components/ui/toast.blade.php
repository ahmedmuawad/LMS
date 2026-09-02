{{-- حاوية واحدة في التخطيط: أطلق الحدث من أي مكان
     window.dispatchEvent(new CustomEvent('toast', { detail: { tone: 'success', text: '...' } })) --}}
<div x-data="{
        items: [],
        add(e) {
            const id = Date.now() + Math.random();
            this.items.push({ id, tone: e.detail.tone || 'info', text: e.detail.text });
            setTimeout(() => this.items = this.items.filter((i) => i.id !== id), e.detail.duration || 5000);
        },
     }"
     x-on:toast.window="add($event)"
     class="fixed z-[60] bottom-4 inset-x-4 sm:inset-x-auto sm:end-4 sm:w-80 flex flex-col gap-2 pointer-events-none"
     role="status" aria-live="polite">
    <template x-for="item in items" :key="item.id">
        <div x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:leave-end="opacity-0"
             class="pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-md text-sm shadow-lg border-s-[3px]"
             :class="{
                'bg-success-subtle text-success border-success': item.tone === 'success',
                'bg-warning-subtle text-warning border-warning': item.tone === 'warning',
                'bg-danger-subtle text-danger border-danger':    item.tone === 'danger',
                'bg-info-subtle text-info border-info':          item.tone === 'info',
             }">
            <span x-text="item.text" class="flex-1"></span>
            <button type="button" @click="items = items.filter((i) => i.id !== item.id)"
                    class="opacity-60 hover:opacity-100 shrink-0" aria-label="{{ __('إغلاق') }}">✕</button>
        </div>
    </template>
</div>
