/**
 * لوحة رموز المعادلات — تُملي على سطح الكتابة المرئي.
 *
 * المدرّس لا يحفظ صياغة TeX ولا يراها: يضغط الرمز فيُدرَج في المعادلة
 * المفتوحة، أو تُنشأ معادلة جديدة عند المؤشّر وتُفتح لتحريرها مرئياً.
 * السطح نفسه (`math-surface.js`) هو من يعرف أين المؤشّر وأي معادلة
 * مفتوحة؛ هذه اللوحة تُرسل الطلب ولا تمسّ النصّ.
 *
 * لوحة واحدة تخدم كل حقول النموذج لا لوحة لكل حقل: نتتبّع آخر حقل
 * لمسه المدرّس، فتعمل مع نصّ السؤال وخياراته وخطوات حلّه بالتساوي.
 */
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
        fieldLabel: '',

        /*
         | ترسو أسفل الشاشة ما دام المدرّس يكتب معادلة.
         |
         | اللوحة في أعلى النموذج والحقل قد يكون في آخره: من يكتب خطوات
         | الحلّ لا يجوز أن يصعد ليضغط رمزاً ثم ينزل ليرى أين وقع.
         */
        docked: false,

        init() {
            // من لمس حقل معادلة يريد اللوحة: تُفتح له وترسو معه
            document.addEventListener('math:focus', (event) => {
                this.fieldLabel = event.detail?.label ?? '';
                this.open = true;
                this.docked = true;
                this.$nextTick(() => this.announce());
            });

            document.addEventListener('math:blur', () => {
                this.docked = false;
                this.announce();
            });

            /*
             | نراقب ارتفاع اللوحة نفسه لا نقيسه مرة واحدة.
             |
             | الطيّ والفتح حركةٌ لها زمن، وتبديل مجموعة الرموز يغيّر عدد
             | الصفوف؛ فالقياس في `$nextTick` يقيس ارتفاعاً في منتصف
             | الطريق — فتُحشى الصفحة بأقلّ ممّا تحجبه اللوحة.
             */
            const panel = this.$el.firstElementChild;

            if (panel && 'ResizeObserver' in window) {
                new ResizeObserver(() => this.announce()).observe(panel);
            }

            window.addEventListener('resize', () => this.announce());
        },

        /**
         * تُعلن ارتفاع ما تحجبه من الشاشة.
         *
         | اللوحة المرساة تغطّي أسفل الصفحة — وفيه الحقل الذي يُكتب فيه.
         | فنُفسح للصفحة أسفلها بمقدار ارتفاعها، ونُخبر السطح ليرفع
         | نفسه فوقها. بغير هذا يكتب المدرّس في حقل لا يراه.
         */
        announce() {
            const panel = this.$el.firstElementChild;
            const height = this.docked ? (panel?.offsetHeight ?? 0) : 0;

            document.documentElement.style.setProperty('--math-dock-height', height + 'px');
            document.body.classList.toggle('math-docked', height > 0);

            document.dispatchEvent(new CustomEvent('math:dock', { detail: { height } }));
        },

        insert(tex) {
            document.dispatchEvent(new CustomEvent('math:insert', { detail: { tex } }));
        },

        /** الفراغ التالي داخل المعادلة المفتوحة — للتنقّل بين خانات الكسر والمصفوفة. */
        nextHole() {
            document.dispatchEvent(new CustomEvent('math:next-hole'));
        },
    };
}
