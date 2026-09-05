/**
 * الأرقام الهندية — عرضاً لا تخزيناً.
 *
 * ## لماذا في المتصفّح لا في الخادم
 *
 * الأرقام تخرج من مئة موضع: أسعارٌ من `Money`، وعدّاداتٌ من Blade،
 * ونِسَبٌ من Alpine، ومؤقّتاتٌ تتغيّر كل ثانية. وتحويلُها في الخادم
 * يعني تعديل مئة موضع ونسيان تسعين — ويبقى ما يرسمه جافاسكربت
 * بعد التحميل عربياً على أي حال.
 *
 * وهنا موضعٌ واحد يمرّ على النصّ المعروض بعد رسمه، فيلتقط ما
 * كُتب في القالب وما رسمه Alpine سواء.
 *
 * ## وما لا يُحوَّل
 *
 * ما يُنسخ ويُلصق ويُبحث به يبقى عربياً: الحقول، والشيفرة، والروابط،
 * والرموز (`ORD-2026`)، وكل ما وُسم `data-digits="keep"`. ورقمُ
 * طلبٍ هنديّ لا يجده صاحبه حين يبحث عنه في بريده.
 */

const EASTERN = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

/* عناصر لا يُمسّ نصُّها */
const SKIP_TAGS = new Set([
    'INPUT', 'TEXTAREA', 'SELECT', 'OPTION', 'SCRIPT', 'STYLE',
    'CODE', 'PRE', 'KBD', 'SAMP',
]);

/* أصنافٌ ومحدّداتٌ تعني «هذا نصّ آلي» */
const SKIP_SELECTOR = '[data-digits="keep"],[dir="ltr"],.font-mono,.tabular-nums-latin,[contenteditable]';

/**
 * رقمٌ ملتصقٌ بحرفٍ لاتيني ليس رقماً — هو جزءُ رمز.
 *
 * `A1` قاعةٌ و`ORD-2026` طلبٌ و`v2` نسخة؛ وتحويلُ رقمها يجعلها
 * لا تُقرأ ولا يُبحث عنها.
 */
const CODE_LIKE = /[A-Za-z][A-Za-z0-9-]*\d|\d[A-Za-z0-9-]*[A-Za-z]/;

function convert(text) {
    if (!/\d/.test(text)) return text;
    if (CODE_LIKE.test(text)) return text;

    return text.replace(/\d/g, (d) => EASTERN[+d]);
}

function skipped(node) {
    for (let el = node.parentElement; el; el = el.parentElement) {
        if (SKIP_TAGS.has(el.tagName)) return true;
        if (el.matches(SKIP_SELECTOR)) return true;
    }
    return false;
}

function walk(root) {
    if (root.nodeType === Node.TEXT_NODE) {
        if (!skipped(root)) {
            const next = convert(root.nodeValue);
            if (next !== root.nodeValue) root.nodeValue = next;
        }
        return;
    }

    if (root.nodeType !== Node.ELEMENT_NODE) return;

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode: (n) => (/\d/.test(n.nodeValue) && !skipped(n)
            ? NodeFilter.FILTER_ACCEPT
            : NodeFilter.FILTER_REJECT),
    });

    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);

    /*
     | تُجمع أولاً ثم تُبدَّل.
     |
     | تعديل النصّ أثناء المشي على الشجرة يُربك المؤشّر فيتخطّى
     | عقداً أو يعود إليها.
     */
    nodes.forEach((n) => {
        const next = convert(n.nodeValue);
        if (next !== n.nodeValue) n.nodeValue = next;
    });
}

export function initNumerals() {
    if (document.documentElement.dataset.numerals !== 'eastern') return;

    walk(document.body);

    /*
     | وما يُرسم بعدُ يُحوَّل أيضاً.
     |
     | نصفُ المنصّة يرسمه Alpine بعد التحميل — سلّةٌ تتغيّر ومؤقّتٌ
     | ينزل وجدولٌ يُصفّى. وبلا مراقبٍ يعود كل تحديثٍ بأرقامٍ عربية
     | وسط هندية، فتبدو الصفحة معطوبة.
     */
    const observer = new MutationObserver((records) => {
        observer.disconnect();

        records.forEach((record) => {
            if (record.type === 'characterData') {
                walk(record.target);
                return;
            }
            record.addedNodes.forEach((n) => walk(n));
        });

        observe();
    });

    const observe = () => observer.observe(document.body, {
        childList: true, subtree: true, characterData: true,
    });

    observe();
}
