import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';

Alpine.plugin(focus);
Alpine.plugin(collapse);

/* ---------------------------------------------------------
   الوضع (فاتح/داكن) — يُحفظ لكل متصفّح
   --------------------------------------------------------- */
Alpine.store('theme', {
    current: 'system',
    init() {
        try {
            this.current = localStorage.getItem('theme') || 'system';
        } catch (e) {
            this.current = 'system';
        }
        this.apply();
    },
    set(mode) {
        this.current = mode;
        try { localStorage.setItem('theme', mode); } catch (e) { /* وضع خاص */ }
        this.apply();
    },
    toggle() {
        this.set(this.isDark() ? 'light' : 'dark');
    },
    isDark() {
        if (this.current === 'dark') return true;
        if (this.current === 'light') return false;
        try {
            return window.matchMedia('(prefers-color-scheme: dark)').matches;
        } catch (e) { return false; }
    },
    apply() {
        const root = document.documentElement;
        if (this.current === 'system') root.removeAttribute('data-theme');
        else root.setAttribute('data-theme', this.current);
    },
});

window.Alpine = Alpine;
Alpine.start();
