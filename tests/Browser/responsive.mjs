/**
 * فحص الاستجابة الآلي — بوابة CI.
 * يفشل إذا: وُجد تمرير أفقي للصفحة · تجاوز عنصر حدود الشاشة ·
 * صغر مساحة لمس عن 32px على الأجهزة اللمسية.
 *
 *   node tests/Browser/responsive.mjs [baseUrl] [path...]
 */
import { chromium } from 'playwright';
import { openContext } from './session.mjs';

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

    /*
     | نقيس التمرير الفعلي لا scrollWidth: في RTL مع حاوية تمرير
     | أفقية داخلية، يبلّغ scrollWidth عن عرض المحتوى المقصوص
     | فيعطي إنذاراً كاذباً. ما يهمّ المستخدم هو: هل تتحرّك الصفحة؟
     */
    const startX = window.scrollX;
    window.scrollTo(viewport * 4, window.scrollY);
    const overflow = Math.max(0, Math.round(Math.abs(window.scrollX - startX)) - 1);
    window.scrollTo(startX, window.scrollY);

    /*
     | نقيس عرض العنصر لا موضعه: في RTL مع حاوية تمرير داخلية،
     | تُحسب إحداثيات كل العناصر على لوحة أعرض من الشاشة فتبدو
     | جميعها «خارجة» بينما لا يتحرّك شيء فعلياً. العرض هو المقياس
     | الصادق: كتلة أعرض من الشاشة ولا حاوية تمرير لها = عيب حقيقي.
     */
    const escapes = [];
    document.querySelectorAll('body *').forEach((el) => {
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.height === 0) return;
        if (getComputedStyle(el).position === 'fixed') return;

        /*
         | مسموح: عنصر عريض داخل حاوية تمرير أفقية خاصة به،
         | أو **مقصوص** بحاوية تُخفي ما يفيض.
         |
         | القصّ ليس عيباً: المستخدم لا يصل إليه ولا يوسّع الصفحة.
         | وKaTeX ترسم جذر العدد وأقواسه بمسار SVG عرضه 400em تقصّه
         | حاويته عمداً — فكانت البوابة تُبلّغ عن «عنصر خارج الشاشة»
         | في صفحة لا تتحرّك أفقياً بمقدار بكسل.
         */
        for (let n = el.parentElement; n && n !== document.body; n = n.parentElement) {
            const ox = getComputedStyle(n).overflowX;
            if (ox === 'auto' || ox === 'scroll' || ox === 'hidden' || ox === 'clip') return;
        }

        const over = r.width - viewport;
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
        const context = await openContext(browser, BASE, {
            viewport: { width: vp.width, height: vp.height },
            hasTouch: vp.touch,
            isMobile: vp.touch,
        });
        const page = await context.newPage();
        await page.goto(BASE + path, { waitUntil: 'networkidle' });
        const r = await page.evaluate(audit);
        await context.close();

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
