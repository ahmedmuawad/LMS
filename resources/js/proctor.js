/*
 | مراقبة الامتحان في المتصفّح.
 |
 | ## ما تَعِد به وما لا تَعِد
 |
 | لا تمنع الغشّ منعاً تامّاً: من فتح كتاباً ورقيّاً بجواره أو
 | استعمل هاتفاً ثانياً لا يمنعه متصفّح. وما تفعله أنها **تجعل ما
 | يقع داخل الجهاز مرئياً**: خروجٌ من النافذة، لصقُ نصّ، تصغيرُ
 | الشاشة — كلٌّ منها يُسجَّل بوقته، ويرى المدرّس التقرير مع الورقة
 | فيقرّر هو.
 |
 | والردع بالمعرفة أصدق من وعدٍ بمنعٍ لا يتحقّق: الطالب يُخبَر قبل
 | أن يبدأ بأن خروجه مسجَّل، فيمتنع لأنه يعلم لا لأنه عاجز.
 |
 | ## ولماذا لا تُنهى المحاولة افتراضياً
 |
 | إشعارٌ يقفز على الهاتف يُخرج الطالب من النافذة بلا إرادته،
 | وإنهاءُ امتحانه لذلك ظلمٌ يقع كثيراً. فالإنهاء التلقائي خيارٌ
 | يضبطه المدرّس بعدد، والافتراض تسجيلٌ بلا إنهاء.
 */

const KINDS = {
    blur: 'خروج من نافذة الامتحان',
    hidden: 'إخفاء الصفحة',
    paste: 'لصق نصّ',
    copy: 'نسخ نصّ',
};

export function initProctor(root) {
    const url = root.dataset.proctorUrl;
    const token = root.dataset.proctorToken;
    const startedAt = Number(root.dataset.proctorStarted || 0);
    const max = Number(root.dataset.proctorMax || 0);

    if (!url || !token) return;

    let violations = Number(root.dataset.proctorCount || 0);
    let lastAt = 0;

    const counter = root.querySelector('[data-proctor-counter]');
    const notice = root.querySelector('[data-proctor-notice]');

    const seconds = () => Math.max(0, Math.floor(Date.now() / 1000) - startedAt);

    /**
     * الإرسال بـ`sendBeacon` لا `fetch`.
     *
     * الحدث الذي نرصده هو مغادرة الصفحة نفسها، و`fetch` يُلغى مع
     * المغادرة فيضيع أهمّ ما نرصده. و`sendBeacon` يُسلَّم للمتصفّح
     * فيرسله ولو أُغلقت الصفحة.
     */
    const record = (kind) => {
        const now = Date.now();

        // تجميع الأحداث المتلاحقة: التبديل السريع حدثٌ واحد لا خمسة
        if (now - lastAt < 1500) return;
        lastAt = now;

        const body = new FormData();
        body.append('_token', token);
        body.append('kind', kind);
        body.append('at_second', String(seconds()));

        navigator.sendBeacon(url, body);

        violations++;

        if (counter) counter.textContent = String(violations);

        if (notice) {
            notice.hidden = false;
            notice.textContent = (KINDS[kind] || kind) + ' — ' + (max > 0
                ? `مسجَّلة (${violations} من ${max})`
                : `مسجَّلة (${violations})`);
        }

        if (max > 0 && violations >= max) {
            const form = root.querySelector('form[data-quiz-form]');

            if (form) {
                form.querySelector('[name="auto_submitted"]')?.setAttribute('value', '1');
                form.submit();
            }
        }
    };

    window.addEventListener('blur', () => record('blur'));
    document.addEventListener('visibilitychange', () => document.hidden && record('hidden'));
    document.addEventListener('paste', () => record('paste'), true);
    document.addEventListener('copy', () => record('copy'), true);
}
