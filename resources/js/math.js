/**
 * بوّابة المعادلات — خفيفة، ولا تُحمّل المحرّك إلا عند الحاجة.
 *
 * KaTeX وخطوطه تزيد الحزمة الرئيسية أربعة أضعاف. صفحة المدوّنة
 * والسلّة والكورسات لا معادلة فيها، فلا يجوز أن تدفع ثمنها. هنا
 * نبحث عن `[data-math]`، وعند وجوده وحده نستورد المحرّك.
 */
let loading = null;

function engine() {
    loading ??= import('./math-render.js');

    return loading;
}

export function renderMathIn(root = document) {
    if (! root.querySelector('[data-math]:not([data-math-done])')) {
        return Promise.resolve();
    }

    return engine().then((module) => module.renderAll(root));
}

document.addEventListener('DOMContentLoaded', () => renderMathIn());

// المحتوى الذي يصل بعد التحميل (تبويب يُفتح، سؤال يُجلب) يُعرض أيضاً
document.addEventListener('math:render', (event) => renderMathIn(event.detail?.root ?? document));

window.renderMathIn = renderMathIn;
