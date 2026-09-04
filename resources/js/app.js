import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';

import './math.js';
import mathEditor from './math-editor.js';

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

Alpine.data('mathEditor', mathEditor);

window.Alpine = Alpine;
Alpine.start();

/* ---------------------------------------------------------
   تطبيق الويب التقدّمي — التسجيل والتثبيت وإشعارات المتصفّح
   --------------------------------------------------------- */
const pwa = {
    /* عامل الخدمة يُسجَّل من الجذر: نطاقه هو مساره، وملف تحت /build
       لا يستطيع خدمة الموقع كلّه. */
    async register() {
        if (!('serviceWorker' in navigator)) return null;

        try {
            return await navigator.serviceWorker.register('/service-worker.js', { scope: '/' });
        } catch (e) {
            return null;   // متصفّح قديم أو سياق غير آمن — الموقع يعمل بدونه
        }
    },

    /* مفتاح VAPID يصل نصّاً بترميز base64url، وواجهة الدفع تطلب بايتات. */
    urlBase64ToUint8Array(base64) {
        const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4))
            .replace(/-/g, '+').replace(/_/g, '/');
        const raw = window.atob(padded);
        return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
    },

    async subscribe(publicKey, csrf) {
        const registration = await navigator.serviceWorker.ready;

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') return false;

        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: this.urlBase64ToUint8Array(publicKey),
        });

        const response = await fetch('/account/push', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(subscription.toJSON()),
        });

        return response.ok;
    },

    async unsubscribe(csrf) {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        if (!subscription) return true;

        await fetch('/account/push', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ endpoint: subscription.endpoint }),
        });

        return subscription.unsubscribe();
    },

    async isSubscribed() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return false;
        const registration = await navigator.serviceWorker.ready;
        return (await registration.pushManager.getSubscription()) !== null;
    },
};

window.pwa = pwa;

Alpine.data('pushToggle', (publicKey, csrf) => ({
    supported: 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
    on: false,
    busy: false,
    denied: false,

    async init() {
        if (!this.supported) return;
        this.denied = Notification.permission === 'denied';
        this.on = await pwa.isSubscribed();
    },

    async toggle() {
        if (this.busy || this.denied) return;
        this.busy = true;

        try {
            this.on = this.on ? !(await pwa.unsubscribe(csrf)) : await pwa.subscribe(publicKey, csrf);
            this.denied = Notification.permission === 'denied';
        } finally {
            this.busy = false;
        }
    },
}));

/* زرّ التثبيت لا يظهر إلا حين يعرضه المتصفّح فعلاً: زرّ لا يفعل شيئاً
   أسوأ من غياب الزرّ. */
Alpine.data('installPrompt', () => ({
    prompt: null,
    available: false,

    init() {
        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            this.prompt = event;
            this.available = true;
        });

        window.addEventListener('appinstalled', () => {
            this.available = false;
            this.prompt = null;
        });
    },

    async install() {
        if (!this.prompt) return;
        this.prompt.prompt();
        await this.prompt.userChoice;
        this.available = false;
        this.prompt = null;
    },
}));

pwa.register();
