/**
 * سطح الكتابة المرئي لحقول الرياضيات.
 *
 * المدرّسة لا تعرف TeX ولا يجوز أن تراه. الحقل الأصلي (الذي يُرسَل مع
 * النموذج) يُخفى، ويقوم مقامه سطحٌ قابل للكتابة: النصّ العادي يُكتب
 * فيه كما هو، والمعادلة تظهر **رقاقةً مرسومة** بـKaTeX. النقر على
 * الرقاقة يفتح لوحة تحرير مرئية (MathLive) تُظهر الكسر كسراً والجذر
 * جذراً وهو يُكتب، وتُعيد الصياغة إلى الرقاقة والحقل الأصلي.
 *
 * يُحمَّل عند الطلب فقط — صفحة بلا حقل رياضيات لا تدفع ثمنه.
 */
import { split, renderTex } from './math-render.js';

const HOLE = /\\square/g;
const PLACEHOLDER = /\\placeholder\{[^}]*\}/g;

/** الفراغ عندنا مربّع، وعند MathLive خانة تُكتب فوقها — نترجم بينهما. */
const toField = (tex) => String(tex ?? '').replace(HOLE, '\\placeholder{}');
const fromField = (tex) => String(tex ?? '').replace(PLACEHOLDER, '\\square');
const hasHole = (tex) => /\\square|\\placeholder\{/.test(String(tex ?? ''));

let popover = null;        // نافذة التحرير — واحدة للصفحة كلّها
let mathField = null;      // عنصر MathLive داخلها
let editing = null;        // الرقاقة المفتوحة الآن
let active = null;         // آخر سطح لمسه المدرّس
let engine = null;         // وعد تحميل MathLive

/* ------------------------------------------------------------------
   الرقاقة
   ------------------------------------------------------------------ */

function chipFor(tex, block = false) {
    const chip = document.createElement('span');
    chip.className = 'math-chip';
    chip.contentEditable = 'false';
    chip.dataset.tex = tex;
    if (block) chip.dataset.block = '';
    chip.setAttribute('role', 'button');
    chip.setAttribute('tabindex', '0');
    chip.setAttribute('aria-label', 'معادلة — اضغط للتعديل');
    paint(chip);

    return chip;
}

function paint(chip) {
    // KaTeX لا يعرف \placeholder — يُرسَم مربّعاً كما في السطح
    const tex = fromField(chip.dataset.tex ?? '');
    chip.classList.toggle('is-empty', tex.trim() === '');
    chip.innerHTML = tex.trim() === '' ? '' : renderTex(tex, 'block' in chip.dataset);
}

/* ------------------------------------------------------------------
   بين الصياغة والسطح
   ------------------------------------------------------------------ */

/** من نصّ الحقل إلى عناصر السطح. */
function hydrate(surface, value) {
    surface.innerHTML = '';

    for (const part of split(String(value ?? ''))) {
        if (part.math) {
            surface.appendChild(chipFor(part.value, part.block));
        } else {
            const lines = part.value.split('\n');
            lines.forEach((line, i) => {
                if (i > 0) surface.appendChild(document.createElement('br'));
                if (line !== '') surface.appendChild(document.createTextNode(line));
            });
        }
    }
}

/** من عناصر السطح إلى نصّ الحقل. */
function serialize(surface) {
    let out = '';

    const walk = (node, isBlockContainer = false) => {
        for (const child of node.childNodes) {
            if (child.nodeType === Node.TEXT_NODE) {
                out += child.textContent;
            } else if (child.nodeName === 'BR') {
                out += '\n';
            } else if (child.classList?.contains('math-chip')) {
                const tex = child.dataset.tex ?? '';
                out += 'block' in child.dataset ? `$$${tex}$$` : `$${tex}$`;
            } else if (child.nodeName === 'DIV' || child.nodeName === 'P') {
                // المتصفّح يلفّ السطر الجديد في div: نقرؤه سطراً
                if (out !== '' && ! out.endsWith('\n')) out += '\n';
                walk(child, true);
            } else {
                walk(child);
            }
        }
    };

    walk(surface);

    return out.replace(/ /g, ' ');
}

function commit(surface) {
    const input = surface.__input;
    input.value = serialize(surface);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

/* ------------------------------------------------------------------
   لوحة التحرير المرئية
   ------------------------------------------------------------------ */

function loadEngine() {
    engine ??= Promise.all([
        import('mathlive'),
        import('mathlive/fonts.css'),
        import('mathlive/static.css'),
    ]).then(([mod]) => {
        const { MathfieldElement } = mod;
        // الخطوط والأصوات مُجمَّعة مع أصولنا، لا من مجلّد خارجي
        MathfieldElement.fontsDirectory = null;
        MathfieldElement.soundsDirectory = null;

        return mod;
    });

    return engine;
}

async function openEditor(chip) {
    await loadEngine();

    if (! popover) {
        popover = document.createElement('div');
        popover.className = 'math-popover';
        popover.setAttribute('role', 'dialog');
        popover.setAttribute('aria-label', 'تحرير المعادلة');
        popover.innerHTML = `
            <math-field></math-field>
            <div class="flex items-center justify-between gap-2 mt-2">
                <span class="text-2xs text-subtle">المربّع المتقطّع مكان فارغ — اضغطه واكتب · Tab للفراغ التالي · Enter للإنهاء</span>
                <span class="inline-flex gap-1">
                    <button type="button" data-act="remove"
                            class="min-h-9 px-3 rounded-md text-xs font-medium text-danger hover:bg-danger-subtle">حذف</button>
                    <button type="button" data-act="done"
                            class="min-h-9 px-3 rounded-md text-xs font-semibold bg-primary text-primary-on">تم</button>
                </span>
            </div>`;
        document.body.appendChild(popover);

        mathField = popover.querySelector('math-field');
        mathField.mathVirtualKeyboardPolicy = 'manual';
        mathField.smartFence = true;
        mathField.smartSuperscript = true;

        mathField.addEventListener('input', () => {
            if (! editing) return;
            editing.dataset.tex = mathField.value;
            paint(editing);
            commit(editing.closest('.math-surface'));
        });

        /*
         | Enter لا يصل إلينا كـkeydown: MathLive تلتقط المفاتيح في مقبس
         | داخل shadow DOM وتوقف انتشارها، وتُطلق `change` بدلاً منه.
         | فهي إشارة «انتهيت» عندنا، ومعها زرّ «تم» لمن يستعمل الفأرة.
         */
        mathField.addEventListener('change', () => closeEditor());

        // ضغط رمز في اللوحة لا يسحب التركيز من المعادلة المفتوحة
        document.addEventListener('mousedown', (event) => {
            if (editing && event.target.closest?.('[data-math-toolbar] button')) event.preventDefault();
        }, true);

        popover.querySelector('[data-act="done"]').addEventListener('click', () => closeEditor());
        popover.querySelector('[data-act="remove"]').addEventListener('click', () => {
            if (! editing) return;
            const surface = editing.closest('.math-surface');
            editing.remove();
            editing = null;
            closeEditor();
            commit(surface);
        });

        document.addEventListener('mousedown', (event) => {
            if (! editing) return;
            if (popover.contains(event.target) || event.target.closest?.('.math-chip')) return;
            if (event.target.closest?.('[data-math-toolbar]')) return;   // اللوحة تُدرج في المعادلة المفتوحة
            closeEditor();
        });
    }

    if (editing && editing !== chip) editing.classList.remove('is-editing');
    editing = chip;
    chip.classList.add('is-editing');

    mathField.value = toField(chip.dataset.tex);

    position(chip);
    popover.hidden = false;
    mathField.focus();

    // أول فراغ يُحدَّد فوراً: من أدرج كسراً يريد أن يكتب بسطه لا أن يبحث عنه
    if (hasHole(chip.dataset.tex)) {
        mathField.executeCommand('moveToMathfieldStart');
        mathField.executeCommand('moveToNextPlaceholder');
    } else {
        mathField.executeCommand('moveToMathfieldEnd');
    }
}

function position(chip) {
    const rect = chip.getBoundingClientRect();
    const top = rect.bottom + window.scrollY + 6;
    const width = popover.offsetWidth || 448;
    let left = rect.left + window.scrollX;

    if (left + width > window.scrollX + document.documentElement.clientWidth - 12) {
        left = Math.max(12, window.scrollX + document.documentElement.clientWidth - width - 12);
    }

    popover.style.top = `${top}px`;
    popover.style.left = `${left}px`;
}

function closeEditor() {
    if (! popover) return;

    if (editing) {
        // الصياغة من اللوحة: نعيد الفراغ الفارغ مربّعاً كي يُرى في السطح
        editing.dataset.tex = fromField(mathField.value);
        paint(editing);
        editing.classList.remove('is-editing');

        const surface = editing.closest('.math-surface');
        const chip = editing;
        editing = null;

        // معادلة تُركت فارغة لا معنى لها — تُحذف بصمت
        if ((chip.dataset.tex ?? '').trim() === '') chip.remove();

        if (surface) commit(surface);
    }

    popover.hidden = true;
}

/* ------------------------------------------------------------------
   الإدراج من لوحة الرموز
   ------------------------------------------------------------------ */

function caretRangeIn(surface) {
    const selection = document.getSelection();

    if (selection && selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        if (surface.contains(range.startContainer)) return range;
    }

    const range = document.createRange();
    range.selectNodeContents(surface);
    range.collapse(false);

    return range;
}

/**
 * زرّ في اللوحة ضُغط.
 *  - ومعادلة مفتوحة: يُكتب فيها.
 *  - وإلا: تُنشأ معادلة عند المؤشّر وتُفتح لتحريرها.
 */
async function insert(surface, tex) {
    await loadEngine();

    if (editing && popover && ! popover.hidden) {
        const isBlockTemplate = tex.trim().startsWith('$$');
        const clean = toField(tex.trim().replace(/^\$+|\$+$/g, ''));
        mathField.insert(clean, { focus: true, feedback: false, selectionMode: 'placeholder' });
        mathField.dispatchEvent(new Event('input'));
        if (isBlockTemplate) editing.dataset.block = '';

        return;
    }

    const block = tex.trim().startsWith('$$');
    const clean = tex.trim().replace(/^\$+|\$+$/g, '');
    const chip = chipFor(clean, block);

    const range = caretRangeIn(surface);
    range.deleteContents();
    range.insertNode(chip);

    // مسافة بعد الرقاقة كي يُكمل النصّ خارجها لا داخلها
    const gap = document.createTextNode(' ');
    chip.after(gap);

    const selection = document.getSelection();
    if (selection) {
        const after = document.createRange();
        after.setStartAfter(gap);
        after.collapse(true);
        selection.removeAllRanges();
        selection.addRange(after);
    }

    commit(surface);
    openEditor(chip);
}

function nextHole() {
    if (editing && mathField) mathField.executeCommand('moveToNextPlaceholder');
}

/* ------------------------------------------------------------------
   التركيب على الحقول
   ------------------------------------------------------------------ */

function mount(input) {
    if (input.__surface) return;

    const surface = document.createElement('div');
    surface.className = (input.className || '') + ' math-surface';
    surface.classList.remove('sr-only');
    surface.contentEditable = 'true';
    surface.dataset.mathSurface = '';
    surface.dataset.placeholder = input.placeholder || input.dataset.mathLabel || '';
    surface.dir = input.dir || 'auto';
    surface.setAttribute('role', 'textbox');
    surface.setAttribute('aria-multiline', 'true');
    if (input.dataset.mathLabel) surface.setAttribute('aria-label', input.dataset.mathLabel);
    surface.__input = input;
    input.__surface = surface;

    hydrate(surface, input.value);

    input.hidden = true;
    input.setAttribute('tabindex', '-1');
    input.after(surface);

    // ما يكتبه أو يلصقه المدرّس نصٌّ عادي — لا HTML يتسرّب إلى الحقل
    surface.addEventListener('paste', (event) => {
        event.preventDefault();
        document.execCommand('insertText', false, event.clipboardData?.getData('text/plain') ?? '');
    });

    surface.addEventListener('input', () => commit(surface));

    surface.addEventListener('focusin', () => {
        active = surface;
        document.dispatchEvent(new CustomEvent('math:focus', { detail: { label: input.dataset.mathLabel || '' } }));
    });

    // الخروج من كل حقول المعادلات يُنهي الإرساء — اللوحة لا تحجب ما لا يخصّها
    surface.addEventListener('focusout', () => {
        setTimeout(() => {
            const inside = document.activeElement?.closest?.('[data-math-surface], .math-popover, [data-math-toolbar]');
            if (! inside) document.dispatchEvent(new CustomEvent('math:blur'));
        }, 120);
    });

    surface.addEventListener('click', (event) => {
        const chip = event.target.closest?.('.math-chip');
        if (chip) { event.preventDefault(); openEditor(chip); }
    });

    surface.addEventListener('keydown', (event) => {
        const chip = event.target.closest?.('.math-chip');
        if (chip && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); openEditor(chip); }
    });

    // القيمة قد تتغيّر من الخارج (Alpine يملأ الخيار مثلاً): السطح يتبعها
    input.addEventListener('math:sync', () => hydrate(surface, input.value));
}

function mountAll(root = document) {
    root.querySelectorAll('[data-math-input]').forEach(mount);
}

/* ------------------------------------------------------------------
   الواجهة للوحة الرموز
   ------------------------------------------------------------------ */

document.addEventListener('math:insert', (event) => {
    const surface = active ?? document.querySelector('[data-math-surface]');
    if (surface) insert(surface, String(event.detail?.tex ?? ''));
});

/* زرّ «لوحة المعادلات» بجانب الحقل: يُركّز الحقل فتُفتح اللوحة وترسو */
document.addEventListener('click', (event) => {
    const trigger = event.target.closest?.('[data-math-open]');
    if (! trigger) return;

    event.preventDefault();

    const input = trigger.closest('fieldset, .grid, div')?.querySelector('[data-math-input]');
    const surface = input?.__surface;

    if (surface) {
        surface.focus();
        surface.dispatchEvent(new Event('focusin', { bubbles: true }));
    }
});

document.addEventListener('math:next-hole', () => nextHole());

/**
 * يرفع الحقل فوق اللوحة المرساة.
 *
 * اللوحة تُثبَّت أسفل الشاشة، والحقل الذي يُكتب فيه قد يقع تحتها —
 * فيكتب المدرّس فيما لا يرى. نُمرّر الصفحة حتى يظهر الحقل كاملاً
 * فوق اللوحة بهامش يسير.
 */
function liftAboveDock(height) {
    if (! active || height <= 0) return;

    const rect = active.getBoundingClientRect();
    const limit = window.innerHeight - height - 16;
    const overlap = rect.bottom - limit;

    if (overlap > 0) window.scrollBy({ top: overlap, behavior: 'smooth' });
}

document.addEventListener('math:dock', (event) => {
    const height = Number(event.detail?.height ?? 0);
    // بعد أن تستقرّ اللوحة في مكانها: القياس قبلها يقيس ارتفاعاً قديماً
    requestAnimationFrame(() => liftAboveDock(height));
});

mountAll();

// حقول تُضاف لاحقاً (خيار جديد في سؤال) تُركَّب هي الأخرى
new MutationObserver((mutations) => {
    for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
            if (node.nodeType !== Node.ELEMENT_NODE) continue;
            if (node.matches?.('[data-math-input]')) mount(node);
            else mountAll(node);
        }
    }
}).observe(document.body, { childList: true, subtree: true });

window.addEventListener('resize', () => { if (editing && popover && ! popover.hidden) position(editing); });
