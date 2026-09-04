/**
 * محرّك عرض المعادلات — يُحمَّل عند الطلب لا مع كل صفحة.
 *
 * KaTeX وخطوطه ربع ميجابايت: تحميلها على صفحة المدوّنة وسلّة الشراء
 * ثمنٌ يدفعه كل زائر لأجل شاشة لا يفتحها. `math.js` يستدعي هذا
 * الملفّ فقط حين يجد معادلة في الصفحة.
 *
 * مادة كالرياضيات لا تُكتب نصّاً عادياً: «س² + ٣س − ٤ = ٠» مكتوبةً
 * بحروف عادية تُقرأ خطأً وتُطبَع أسوأ. نستعمل TeX داخل النصّ، ونعرضه
 * بـKaTeX **مُجمَّعاً مع أصولنا** لا من CDN: المنصّة تعمل خلف جدار
 * ناري، وفي مدرسة بشبكة بطيئة، وسكربت خارجي يعني معادلات لا تظهر.
 *
 * الصياغة:
 *   $ ... $   معادلة داخل السطر
 *   $$ ... $$ معادلة في سطر مستقل
 *
 * ويُعرض النصّ الأصلي إن فشل التحويل — لا شاشة حمراء أمام طالب.
 */
import katex from 'katex';
import 'katex/dist/katex.min.css';
import './math.css';

const INLINE = '$';
const BLOCK = '$$';

/** يقسم النصّ إلى قطع: نصّ عادي ومعادلات. */
export function split(text) {
    const parts = [];
    let buffer = '';
    let i = 0;

    while (i < text.length) {
        const isBlock = text.startsWith(BLOCK, i);
        const isInline = ! isBlock && text[i] === INLINE;

        if (! isBlock && ! isInline) {
            buffer += text[i];
            i += 1;
            continue;
        }

        const open = isBlock ? BLOCK : INLINE;
        const close = text.indexOf(open, i + open.length);

        // فاصل بلا إغلاق: علامة دولار عادية في جملة عن السعر لا معادلة
        if (close === -1) {
            buffer += text[i];
            i += 1;
            continue;
        }

        if (buffer !== '') {
            parts.push({ math: false, value: buffer });
            buffer = '';
        }

        parts.push({
            math: true,
            block: isBlock,
            value: text.slice(i + open.length, close),
        });

        i = close + open.length;
    }

    if (buffer !== '') {
        parts.push({ math: false, value: buffer });
    }

    return parts;
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value;

    return div.innerHTML;
}

/*
 | الفراغ يُرسَم مربّعاً متقطّعاً لا مربّعاً صامتاً.
 |
 | «ما هذه المربّعات؟» سؤالٌ يطرحه كل من أدرج كسراً لأول مرة: المربّع
 | الصلب يبدو رمزاً رياضياً، لا مكاناً ينتظر رقماً. المتقطّع الملوّن
 | يقول ما هو بلا شرح.
 |
 | `trust` دالّة لا `true`: نأذن لأمر واحد بصنف واحد من عندنا، ويبقى
 | ما يكتبه المستخدم بلا أي إذن.
 */
const HOLE_MACRO = { '\\square': '\\htmlClass{math-hole}{\\phantom{x}}' };
const trustHole = (context) => context.command === '\\htmlClass' && context.class === 'math-hole';

/** يرسم صياغة TeX واحدة — للمعادلة المفردة داخل رقاقة المحرّر. */
export function renderTex(tex, block = false) {
    try {
        return katex.renderToString(String(tex ?? ''), {
            displayMode: block,
            throwOnError: false,
            strict: false,
            maxExpand: 400,
            macros: HOLE_MACRO,
            trust: trustHole,
        });
    } catch (e) {
        return escapeHtml(tex);
    }
}

/** يحوّل نصّاً فيه TeX إلى HTML آمن. */
export function renderMath(text) {
    return split(String(text ?? ''))
        .map((part) => {
            if (! part.math) {
                return escapeHtml(part.value).replace(/\n/g, '<br>');
            }

            try {
                return katex.renderToString(part.value, {
                    displayMode: part.block,
                    throwOnError: false,
                    strict: false,
                    // الماكرو الذي يُعرّف نفسه بنفسه يُعلّق المتصفّح
                    maxExpand: 400,
                    macros: HOLE_MACRO,
                    trust: trustHole,
                });
            } catch (e) {
                return escapeHtml(part.value);
            }
        })
        .join('');
}

/**
 * يعرض كل عنصر يحمل [data-math] مرة واحدة.
 *
 * المصدر يُقرأ من textContent لا من innerHTML: ما يكتبه الطالب في
 * سؤاله نصٌّ لا HTML، وتمريره خاماً بابُ حقنٍ مفتوح.
 */
export function renderAll(root = document) {
    root.querySelectorAll('[data-math]:not([data-math-done])').forEach((el) => {
        el.innerHTML = renderMath(el.textContent);
        el.setAttribute('data-math-done', '');
    });
}

