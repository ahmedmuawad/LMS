/*
 | حماية المحتوى في المتصفّح.
 |
 | ## ما تفعله وما لا تفعله
 |
 | لا يمنع أي متصفّح تصوير الشاشة منعاً تامّاً: اللقطة تُلتقط خارج
 | الصفحة، من نظام التشغيل، ولا سلطان لنا هناك. ومن يبيع «منع لقطة
 | الشاشة» في متصفّح يبيع وهماً.
 |
 | وما يتحقّق فعلاً:
 |
 |   · إخفاء المحتوى لحظة خروج التركيز من النافذة — وأداة التصوير
 |     في ويندوز وماك تسحب التركيز، فتلتقط صفحةً مطموسة
 |   · منع النسخ والقصّ والسحب والقائمة اليمنى
 |   · إخفاء عند طباعة الصفحة
 |
 | وكلٌّ منها يُشغَّل بإعداده وحده، فمن أطفأه لم يُصبه شيء.
 */

const flag = (name) => document.documentElement.dataset[name] === '1';

/**
 * الطمس عند فقد التركيز.
 *
 * التأخير خمس مئة جزء من الثانية عمداً: التبديل السريع بين نافذتين
 * أثناء المذاكرة عملٌ مشروع، وطمسٌ يومض مع كل نقرة خارج النافذة
 * يجعل الطالب يكره الشاشة قبل أن يكره الحماية.
 */
function guardOnBlur() {
    const veil = document.createElement('div');
    veil.className = 'content-guard-veil';
    veil.setAttribute('aria-hidden', 'true');
    veil.innerHTML =
        '<p>' + (document.documentElement.dataset.guardMessage || 'المحتوى مخفيّ — عُد إلى النافذة لمتابعته.') + '</p>';
    document.body.appendChild(veil);

    let timer = null;

    const hide = () => {
        clearTimeout(timer);
        veil.classList.add('is-on');
    };

    const show = () => {
        clearTimeout(timer);
        timer = setTimeout(() => veil.classList.remove('is-on'), 120);
    };

    window.addEventListener('blur', hide);
    window.addEventListener('focus', show);
    document.addEventListener('visibilitychange', () => (document.hidden ? hide() : show()));

    // الطباعة تلتقط الصفحة كاملةً بلا مرور بالتركيز
    window.addEventListener('beforeprint', hide);
    window.addEventListener('afterprint', show);
}

function blockCopy() {
    document.documentElement.classList.add('no-select');

    for (const event of ['copy', 'cut', 'dragstart', 'contextmenu']) {
        document.addEventListener(
            event,
            (e) => {
                // حقول الإدخال مستثناة: الطالب ينسخ إجابته هو
                if (e.target.closest('input, textarea, [contenteditable]')) return;
                e.preventDefault();
            },
            true,
        );
    }
}

export function initContentGuard() {
    if (flag('guardBlur')) guardOnBlur();
    if (flag('guardCopy')) blockCopy();
}
