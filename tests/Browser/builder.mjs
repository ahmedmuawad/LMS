/**
 * فحص باني الصفحات — بوابة CI.
 *
 * الباني يحرّر بنية تُحفظ نصّاً واحداً: إضافة كتلة وتحريك وحذف
 * وتكرار كلّها تغيّر ما يُرسَل، ولا يكشف الخلل فيها إلا متصفّح حقيقي.
 *
 *   GATE_EMAIL=… GATE_PASSWORD=… node tests/Browser/builder.mjs [baseUrl] [pageId]
 */
import { chromium } from 'playwright';
import { openContext } from './session.mjs';

const BASE = process.argv[2] || 'http://demo.localhost:8899';
const PAGE_ID = process.argv[3] || '1';
const results = [];
const check = (name, ok, detail = '') => {
    results.push({ name, ok, detail });
    console.log(`${ok ? '✓' : '✕'} ${name}${detail && !ok ? ' — ' + detail : ''}`);
};

const browser = await chromium.launch(process.env.PLAYWRIGHT_CHROMIUM ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM } : {});
const context = await openContext(browser, BASE, { viewport: { width: 1280, height: 1000 } });
const page = await context.newPage();

await page.goto(`${BASE}/admin/page-builder/${PAGE_ID}`, { waitUntil: 'networkidle' });

const payloads = () => page.$$eval('input[name="blocks[]"]', (nodes) => nodes.map((n) => JSON.parse(n.value)));
const cards = () => page.locator('main article');

const before = (await payloads()).length;

// --- إضافة كتلة ---
await page.getByRole('button', { name: 'نص', exact: true }).first().click();
await page.waitForTimeout(200);
const added = await payloads();
check('إضافة كتلة تزيد البنية المرسلة', added.length === before + 1, `${before} → ${added.length}`);
check('الكتلة المضافة تحمل نوعها', added[added.length - 1]?.type === 'text');

// --- الكتابة تصل إلى البنية ---
// أول حقل نصّي في البطاقة المضافة هو عنوانها بالعربية
const heading = cards().last().locator('input[type="text"]:not([name])').first();
await heading.fill('عنوان من الفحص');
await page.waitForTimeout(200);
const typed = await payloads();
check(
    'ما يُكتب في الحقل يظهر في البنية',
    JSON.stringify(typed[typed.length - 1]?.content ?? {}).includes('عنوان من الفحص'),
);

// --- الترتيب ---
if (added.length >= 2) {
    const firstType = (await payloads())[0].type;
    await page.getByRole('button', { name: 'حرّك لأسفل' }).first().click();
    await page.waitForTimeout(200);
    const moved = await payloads();
    check('التحريك يغيّر ترتيب البنية', moved[1].type === firstType, `${firstType} → ${moved[1].type}`);
} else {
    check('التحريك يغيّر ترتيب البنية', true, 'تخطّي: كتلة واحدة');
}

// --- التكرار ---
const beforeCopy = (await payloads()).length;
await page.getByRole('button', { name: 'تكرار' }).first().click();
await page.waitForTimeout(200);
check('التكرار يضيف نسخة', (await payloads()).length === beforeCopy + 1);

// --- الحذف ---
const beforeDelete = (await payloads()).length;
await page.getByRole('button', { name: 'حذف' }).first().click();
await page.waitForTimeout(200);
check('الحذف يزيل الكتلة من البنية', (await payloads()).length === beforeDelete - 1);

// --- كل بطاقة لها حمولتها ---
check('لكل بطاقة حمولة واحدة', (await cards().count()) === (await payloads()).length,
    `${await cards().count()} بطاقة · ${(await payloads()).length} حمولة`);

// --- الحفظ يبقي البنية بعد إعادة التحميل ---
const expected = (await payloads()).length;
await Promise.all([
    page.waitForLoadState('networkidle'),
    page.getByRole('button', { name: 'حفظ' }).click(),
]);
await page.waitForTimeout(400);
const saved = await payloads();
check('الحفظ يبقي الكتل بعد إعادة التحميل', saved.length === expected, `${expected} → ${saved.length}`);

await browser.close();
const failed = results.filter((r) => !r.ok).length;
console.log(failed ? `\n${failed} فحص باني فاشل` : `\nكل فحوص الباني ناجحة (${results.length})`);
process.exit(failed ? 1 : 0);
