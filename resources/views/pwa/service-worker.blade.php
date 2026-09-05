{{-- عامل الخدمة — يُولَّد لكل مشترك ويُقدَّم من الجذر ليغطي الموقع كلّه. --}}
const VERSION = '{{ substr(md5((string) (tenant('id') ?? 'central').filemtime(public_path('build/manifest.json') ?? __FILE__)), 0, 8) }}';
const SHELL = `shell-${VERSION}`;
const RUNTIME = `runtime-${VERSION}`;
const OFFLINE_URL = '{{ url('/offline') }}';

/* ما يُخزَّن مسبقاً: صفحة «بلا اتصال» وحدها.
   تخزين الصفحات كلّها يجعل المستخدم يرى محتوى قديماً بلا أن يدري. */
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL)
            .then((cache) => cache.addAll([OFFLINE_URL]))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key !== SHELL && key !== RUNTIME && key !== 'lessons')
                    .map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) {
        return;
    }

    /* الدروس المحفوظة للمشاهدة بلا اتصال.

       المخزن `lessons` يملؤه الطالب بزرّ الحفظ، ولا يُمسح مع تغيّر
       النسخة كما تُمسح مخازن البناء: هو ما حفظه بنفسه، ومحوُه عند
       كل تحديثٍ للمنصة يجعل الحفظ بلا معنى.

       والكاش أولاً بلا شبكة: من حفظ درساً يريده يعمل في المترو، لا
       أن ينتظر مهلة اتصالٍ فاشل قبل أن يُقرأ من جهازه. */
    if (request.url.includes('/offline') && /\/lessons\/\d+\/offline/.test(request.url)) {
        event.respondWith(
            caches.open('lessons')
                .then((cache) => cache.match(request.url))
                .then((hit) => hit || fetch(request))
        );
        return;
    }

    /* الملفات المبنية تحمل بصمة في اسمها: الكاش أولاً بلا خوف من القِدم. */
    if (request.url.includes('/build/')) {
        event.respondWith(
            caches.match(request).then((hit) => hit || fetch(request).then((response) => {
                const copy = response.clone();
                caches.open(RUNTIME).then((cache) => cache.put(request, copy));
                return response;
            }))
        );
        return;
    }

    /* الصفحات: الشبكة أولاً — المحتوى التعليمي يتغيّر، والقديم يضلّل. */
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(RUNTIME).then((cache) => cache.put(request, copy));
                    return response;
                })
                .catch(() => caches.match(request).then((hit) => hit || caches.match(OFFLINE_URL)))
        );
    }
});

/* ---------- إشعارات الدفع ---------- */
self.addEventListener('push', (event) => {
    if (!event.data) return;

    let payload = {};
    try { payload = event.data.json(); } catch (e) { payload = { title: event.data.text() }; }

    event.waitUntil(
        self.registration.showNotification(payload.title || '{{ __('إشعار جديد') }}', {
            body: payload.body || '',
            icon: '{{ url('/icon.svg') }}',
            badge: '{{ url('/icon.svg') }}',
            dir: '{{ in_array(app()->getLocale(), ['ar', 'fa', 'ur', 'he'], true) ? 'rtl' : 'ltr' }}',
            lang: '{{ app()->getLocale() }}',
            data: { url: payload.url || '{{ url('/notifications') }}' },
            tag: payload.tag || 'general',
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = event.notification.data && event.notification.data.url;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            /* نافذة مفتوحة على الموقع تُستخدم بدل فتح ثانية. */
            for (const client of clients) {
                if ('focus' in client) {
                    if (target) client.navigate(target);
                    return client.focus();
                }
            }
            return self.clients.openWindow(target || '{{ url('/') }}');
        })
    );
});
