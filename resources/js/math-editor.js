/**
 * محرّر المعادلات المرئي.
 *
 * المدرّس لا يحفظ صياغة TeX ولا يجوز أن نطلب منه ذلك: يضغط الرمز
 * فيُكتب في مكان المؤشّر، ويرى نتيجته مرسومة تحت الحقل وهو يكتب.
 *
 * لوحة واحدة تخدم كل حقول النموذج لا لوحة لكل حقل: نتتبّع آخر حقل
 * لمسه المدرّس، فتعمل اللوحة مع نصّ السؤال وخياراته وخطوات حلّه
 * بالتساوي — وشاشةٌ فيها ثماني لوحات لا تُستعمل.
 */
import { renderMathInto } from './math.js';

/** الفراغ الذي يُملأ — يُرسَم مربّعاً ويُحدَّد بعد الإدراج. */
const HOLE = '\\square';

export default function mathEditor(initialGroup = 'basic') {
    return {
        group: initialGroup,
        /*
         | مفتوحة على الشاشة الواسعة، مطويّة على الضيّقة.
         |
         | من يفتح شاشة سؤال رياضيات جاء ليكتب معادلة، وإخفاء اللوحة
         | خلف ضغطة يُلغي معناها. وعلى الهاتف تدفع النموذج كلّه
         | خارج الشاشة — فتُطوى هناك.
         */
        open: typeof window !== 'undefined' && window.innerWidth >= 1024,
        field: null,          // آخر حقل لمسه المدرّس
        fieldLabel: '',
        preview: '',
        holes: 0,

        init() {
            /*
             | نتتبّع التركيز على مستوى المستند لا بربط كل حقل:
             | حقول الخيارات تُضاف وتُحذف من Alpine أثناء الكتابة،
             | ومستمعٌ لكل واحد يتسرّب مع كل حذف.
             */
            document.addEventListener('focusin', (event) => {
                const el = event.target.closest?.('[data-math-input]');
                if (el) this.attach(el);
            });

            document.addEventListener('input', (event) => {
                if (event.target === this.field) this.refresh();
            });

            const first = document.querySelector('[data-math-input]');
            if (first) this.attach(first, false);
        },

        attach(el, focus = true) {
            this.field = el;
            this.fieldLabel = el.dataset.mathLabel || '';
            if (focus) this.open = true;
            this.refresh();
        },

        refresh() {
            if (! this.field) return;

            const value = this.field.value || '';
            this.holes = (value.match(/\\square/g) || []).length;

            // المعاينة تعرض السطر الذي عليه المؤشّر: خطوات الحل
            // سطرٌ لكل خطوة، ومعاينتها كلّها دفعةً تُخفي ما يُحرَّر
            const upto = value.slice(0, this.field.selectionStart ?? value.length);
            const start = upto.lastIndexOf('\n') + 1;
            const end = value.indexOf('\n', start);
            const line = value.slice(start, end === -1 ? value.length : end);

            this.preview = line.trim() === '' ? value : line;

            const target = this.$refs.preview;
            if (target) renderMathInto(target, this.preview);
        },

        /**
         * يُدرج في مكان المؤشّر، ويلفّ بعلامتَي $ إن لم تكن المعادلة
         * مفتوحة أصلاً — فمن يضغط «كسر» في نصّ عادي يريد معادلة، لا
         * صياغةً تظهر للطالب كما هي.
         */
        insert(tex) {
            const el = this.field ?? document.querySelector('[data-math-input]');
            if (! el) return;

            this.field = el;

            const value = el.value || '';
            const start = el.selectionStart ?? value.length;
            const end = el.selectionEnd ?? start;

            /*
             | أربع حالات لا حالتان.
             |
             | القالب معادلة كاملة بعلامتيها، والرمز جزءٌ منها. وإدراج
             | قالب والمؤشّر داخل معادلة قائمة كان يُنتج تعشيشاً
             | مكسوراً: `$\frac{1}{4$c^2=a^2+b^2$}$`. فنُجرّد القالب
             | من علامتيه حين يدخل معادلةً قائمة، ونُلبسهما الرمز
             | حين يخرج إلى نصّ عادي.
             */
            const selfDelimited = tex.trim().startsWith('$');
            const inside = this.insideMath(value, start);

            const snippet = inside
                ? (selfDelimited ? tex.trim().replace(/^\$+|\$+$/g, '') : tex)
                : (selfDelimited ? tex : '$' + tex + '$');

            /*
             | مسافة تفصل معادلةً عن جارتها.
             |
             | إلصاق معادلتين ينتج `$$` — وهي في صياغتنا فاتحةُ معادلة
             | في سطر مستقلّ، فتُقرأ نيّة المدرّس عكسَ ما أراد.
             */
            const before = value.slice(0, start);
            const after = value.slice(end);
            const pad = (a, b) => (a.endsWith('$') && b.startsWith('$') ? ' ' : '');

            el.value = before + pad(before, snippet) + snippet + pad(snippet, after) + after;

            // نُحدّد أول فراغ داخل ما أُدرج ليكتب فوقه مباشرة
            const holeAt = el.value.indexOf(HOLE, start);
            const inserted = before.length + pad(before, snippet).length + snippet.length;

            if (holeAt !== -1 && holeAt < inserted) {
                el.setSelectionRange(holeAt, holeAt + HOLE.length);
            } else {
                // أُضيفت علامة الإغلاق من عندنا: نقف قبلها لا بعدها
                const caret = ! inside && ! selfDelimited ? inserted - 1 : inserted;
                el.setSelectionRange(caret, caret);
            }

            el.focus();
            el.dispatchEvent(new Event('input', { bubbles: true }));
            this.refresh();
        },

        /** الفراغ التالي — للتنقّل بين خانات الكسر والمصفوفة. */
        nextHole() {
            const el = this.field;
            if (! el) return;

            const from = (el.selectionEnd ?? 0);
            let at = el.value.indexOf(HOLE, from);

            if (at === -1) at = el.value.indexOf(HOLE);   // نلتفّ من البداية
            if (at === -1) return;

            el.focus();
            el.setSelectionRange(at, at + HOLE.length);
            this.refresh();
        },

        /**
         * هل المؤشّر داخل معادلة؟ عددُ علامات الدولار قبله فردٌ يعني نعم.
         *
         * تقدير لا يقين، وهو ما يكفي: أسوأ ما يحدث أن يُدرج المدرّس
         * علامتين زائدتين فيراهما ويحذفهما.
         */
        insideMath(value, index) {
            return (value.slice(0, index).match(/\$/g) || []).length % 2 === 1;
        },
    };
}
