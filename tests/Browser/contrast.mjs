/**
 * فحص التباين الآلي — بوابة CI (ADR-013).
 * يقيس التباين الفعلي لكل نص مرئي في الصفحة مقابل الخلفية الفعلية خلفه،
 * في الوضعين الفاتح والداكن. لا يعتمد على قائمة tokens مكتوبة يدوياً،
 * فيكشف أيضاً سوء استخدام لون في مكوّن.
 *
 *   node tests/Browser/contrast.mjs [baseUrl] [path...]
 */
import { chromium } from 'playwright';

const BASE  = process.argv[2] || 'http://127.0.0.1:8899';
const PATHS = process.argv.slice(3).length ? process.argv.slice(3) : ['/design-system'];

const audit = () => {
    const toRgb = (s) => {
        const m = s.match(/rgba?\(([^)]+)\)/);
        if (!m) return null;
        const p = m[1].split(/[,\s/]+/).filter(Boolean).map(Number);
        return { r: p[0], g: p[1], b: p[2], a: p[3] === undefined ? 1 : p[3] };
    };
    const chan = (c) => { c /= 255; return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4; };
    const lum  = (c) => 0.2126 * chan(c.r) + 0.7152 * chan(c.g) + 0.0722 * chan(c.b);
    const ratio = (a, b) => {
        const [l1, l2] = [lum(a), lum(b)].sort((x, y) => y - x);
        return (l1 + 0.05) / (l2 + 0.05);
    };
    const over = (fg, bg) => ({                       // دمج شفافية النص فوق الخلفية
        r: fg.r * fg.a + bg.r * (1 - fg.a),
        g: fg.g * fg.a + bg.g * (1 - fg.a),
        b: fg.b * fg.a + bg.b * (1 - fg.a),
        a: 1,
    });

    const effectiveBg = (el) => {
        for (let n = el; n && n !== document.documentElement.parentNode; n = n.parentElement) {
            const c = toRgb(getComputedStyle(n).backgroundColor);
            if (c && c.a > 0.95) return c;
        }
        return toRgb(getComputedStyle(document.body).backgroundColor) ?? { r: 255, g: 255, b: 255, a: 1 };
    };

    const failures = [];
    let checked = 0;

    document.querySelectorAll('body *').forEach((el) => {
        const text = Array.from(el.childNodes)
            .filter((n) => n.nodeType === 3)
            .map((n) => n.textContent.trim())
            .join('')
            .trim();
        if (!text) return;

        const cs = getComputedStyle(el);
        if (cs.visibility === 'hidden' || cs.display === 'none' || +cs.opacity < 0.1) return;
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.height === 0) return;
        if (el.classList.contains('sr-only')) return;
        if (el.closest('[aria-hidden="true"]')) return;                    // زخرفي
        if (/^[\p{Emoji}\s\u2190-\u21FF\u2600-\u27BF]+$/u.test(text)) return;  // رموز ملوّنة بذاتها

        const fgRaw = toRgb(cs.color);
        if (!fgRaw) return;
        const bg = effectiveBg(el);
        const fg = fgRaw.a < 1 ? over(fgRaw, bg) : fgRaw;

        const px    = parseFloat(cs.fontSize);
        const bold  = parseInt(cs.fontWeight, 10) >= 700;
        const large = px >= 24 || (px >= 18.66 && bold);   // تعريف WCAG للنص الكبير
        const need  = large ? 3 : 4.5;

        checked++;
        const got = ratio(fg, bg);
        if (got < need - 0.01) {
            failures.push({
                text: text.slice(0, 34),
                cls: String(el.className).slice(0, 48),
                px: Math.round(px),
                got: +got.toFixed(2),
                need,
            });
        }
    });

    return { checked, failures: failures.slice(0, 15), total: failures.length };
};

const browser = await chromium.launch(process.env.PLAYWRIGHT_CHROMIUM ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM } : {});
let failed = 0;

for (const path of PATHS) {
    for (const scheme of ['light', 'dark']) {
        const page = await browser.newPage({ viewport: { width: 1280, height: 900 }, colorScheme: scheme });
        await page.goto(BASE + path, { waitUntil: 'networkidle' });
        const r = await page.evaluate(audit);
        await page.close();

        const label = scheme === 'dark' ? 'داكن' : 'فاتح';
        if (r.total) {
            failed += r.total;
            console.log(`✕ ${path} — الوضع ال${label}: ${r.total} نص تحت الحد (من ${r.checked})`);
            r.failures.forEach((f) => console.log(`    ↳ ${f.got}:1 (المطلوب ${f.need}) · ${f.px}px · "${f.text}" · ${f.cls}`));
        } else {
            console.log(`✓ ${path} — الوضع ال${label}: ${r.checked} نص، كلها ضمن WCAG AA`);
        }
    }
}

await browser.close();
console.log(failed ? `\n${failed} إخفاق تباين` : '\nكل فحوص التباين ناجحة');
process.exit(failed ? 1 : 0);
