/**
 * فحص الاستجابة الآلي — بوابة CI.
 * يفشل إذا: وُجد تمرير أفقي للصفحة · تجاوز عنصر حدود الشاشة ·
 * صغر مساحة لمس عن 32px على الأجهزة اللمسية.
 *
 *   node tests/Browser/responsive.mjs [baseUrl] [path...]
 */
import { chromium } from 'playwright';

const BASE  = process.argv[2] || 'http://127.0.0.1:8899';
const PATHS = process.argv.slice(3).length ? process.argv.slice(3) : ['/design-system', '/en/design-system'];

const VIEWPORTS = [
    { name: 'mobile-sm', width: 320,  height: 720,  touch: true },
    { name: 'mobile',    width: 375,  height: 812,  touch: true },
    { name: 'mobile-lg', width: 430,  height: 900,  touch: true },
    { name: 'tablet',    width: 768,  height: 1024, touch: true },
    { name: 'laptop',    width: 1024, height: 900,  touch: false },
    { name: 'desktop',   width: 1440, height: 900,  touch: false },
];

const audit = () => {
    const de = document.documentElement;
    const viewport = de.clientWidth;
    const overflow = de.scrollWidth - viewport;

    const escapes = [];
    document.querySelectorAll('body *').forEach((el) => {
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.height === 0) return;
        if (el.closest('[class*="overflow-x-auto"]')) return;               // مسموح: يمرّر داخل حاويته
        if (getComputedStyle(el).position === 'fixed') return;
        const over = Math.max(r.right - viewport, -r.left);
        if (over > 4) {
            escapes.push({ tag: el.tagName.toLowerCase(), cls: String(el.className).slice(0, 60), over: Math.round(over) });
        }
    });

    // مساحة اللمس تُقاس على أقرب عنصر قابل للنقر (التسمية تحيط بمربّع الاختيار)
    const targets = [];
    document.querySelectorAll('a[href], button, [role="button"], input, select, textarea, summary').forEach((el) => {
        if (el.classList.contains('sr-only')) return;
        const box = el.closest('label') ?? el;
        const r = box.getBoundingClientRect();
        if (r.width === 0) return;
        if (r.height < 32) {
            targets.push({ tag: el.tagName.toLowerCase(), h: Math.round(r.height), txt: (el.textContent || '').trim().slice(0, 22) });
        }
    });

    return { overflow, escapes: escapes.slice(0, 6), escapeCount: escapes.length, targets: targets.slice(0, 6), targetCount: targets.length };
};

const browser = await chromium.launch(process.env.PLAYWRIGHT_CHROMIUM ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM } : {});
let failed = 0;

for (const path of PATHS) {
    for (const vp of VIEWPORTS) {
        const page = await browser.newPage({
            viewport: { width: vp.width, height: vp.height },
            hasTouch: vp.touch,
            isMobile: vp.touch,
        });
        await page.goto(BASE + path, { waitUntil: 'networkidle' });
        const r = await page.evaluate(audit);
        await page.close();

        const problems = [];
        if (r.overflow > 0)   problems.push(`تمرير أفقي ${r.overflow}px`);
        if (r.escapeCount)    problems.push(`${r.escapeCount} عنصر خارج الشاشة`);
        if (vp.touch && r.targetCount) problems.push(`${r.targetCount} هدف لمس < 32px`);

        if (problems.length) {
            failed++;
            console.log(`✕ ${path} @ ${vp.name} (${vp.width}px) — ${problems.join(' · ')}`);
            r.escapes.forEach((e) => console.log(`    ↳ <${e.tag}> +${e.over}px  ${e.cls}`));
            r.targets.forEach((t) => console.log(`    ↳ <${t.tag}> ${t.h}px  ${t.txt}`));
        } else {
            console.log(`✓ ${path} @ ${vp.name} (${vp.width}px)`);
        }
    }
}

await browser.close();
console.log(failed ? `\n${failed} فحص فاشل` : '\nكل فحوص الاستجابة ناجحة');
process.exit(failed ? 1 : 0);
