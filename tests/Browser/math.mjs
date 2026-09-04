/**
 * بوابة محرّر المعادلات — CI.
 *
 * تفشل إذا: عجز KaTeX عن رسم رمز واحد في اللوحة · لم يُدرج الرمز في
 * مكان المؤشّر · لم يُحدَّد الفراغ الأول · نُشِّئ تعشيشٌ مكسور في
 * علامات الدولار.
 *
 * السبب: اللوحة ٨٩ زرّاً مكتوبةً في config، وخطأٌ مطبعيّ في صياغة
 * واحد منها يمرّ صامتاً في كل اختبار PHP — ولا يظهر إلا مربّعاً
 * أحمر أمام المدرّس.
 *
 *   node tests/Browser/math.mjs [baseUrl] [path]
 */
import { chromium } from 'playwright';

const BASE = process.argv[2] || 'http://math.localhost:8899';
const PATH = process.argv[3] || '/admin/questions/create';
const EMAIL = process.env.GATE_EMAIL || 'mona@math.test';
const PASSWORD = process.env.GATE_PASSWORD || 'password';

const browser = await chromium.launch(process.env.PLAYWRIGHT_CHROMIUM ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM } : {});
const context = await browser.newContext({ viewport: { width: 1280, height: 1000 } });
const page = await context.newPage();

let failed = 0;
const check = (ok, label, detail = '') => {
    console.log(`${ok ? '✓' : '✕'} ${label}${detail ? ' — ' + detail : ''}`);
    if (!ok) failed++;
};

await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
await page.fill('input[name="email"]', EMAIL);
await page.fill('input[name="password"]', PASSWORD);
await Promise.all([page.waitForLoadState('networkidle'), page.click('button[type="submit"]')]);

if (new URL(page.url()).pathname === '/login') {
    console.error('تعذّر تسجيل الدخول — البوابة تحتاج حساباً يفتح بنك الأسئلة.');
    process.exit(1);
}

await page.goto(BASE + PATH, { waitUntil: 'networkidle' });
await page.waitForTimeout(1500);

check(await page.$('#math-palette') !== null, 'لوحة الرموز تظهر في شاشة السؤال');

/*
 | كل تبويب يُفتح ويُفحص: الأزرار المخفيّة تُرسَم أيضاً، لكن فتحها
 | يضمن أن ما نعدّه معروضٌ فعلاً لا مُعلَّق في DOM.
 */
const tabs = await page.$$('#math-palette button[role="tab"]');
let symbols = 0;

for (const tab of tabs) {
    await tab.click();
    await page.waitForTimeout(250);
}

await page.waitForTimeout(600);

const errors = await page.$$eval('#math-palette .katex-error',
    ns => ns.map(n => (n.getAttribute('title') || n.textContent || '').slice(0, 80)));
symbols = await page.$$eval('#math-palette .katex', ns => ns.length);

check(symbols > 50, 'الرموز مرسومة لا مكتوبة', `${symbols} رمزاً`);
check(errors.length === 0, 'لا رمز عجز المحرّك عن رسمه', errors.slice(0, 5).join(' · '));

// ---------- الإدراج ----------
const body = await page.$('textarea[name="body[ar]"]');
await body.fill('');
await body.click();
await body.type('احسب ');

await page.click('#math-palette button[role="tab"]:has-text("كسور")');
await page.waitForTimeout(250);
await page.click('#math-palette button[aria-label="كسر"]');
await page.waitForTimeout(300);

let value = await body.inputValue();
check(value === 'احسب $\\frac{\\square}{\\square}$', 'الرمز يُدرج في مكان المؤشّر ملفوفاً بعلامتَي الدولار', value);

const selection = await page.evaluate(() => {
    const el = document.querySelector('textarea[name="body[ar]"]');
    return el.value.slice(el.selectionStart, el.selectionEnd);
});
check(selection === '\\square', 'الفراغ الأول مُحدَّد فيُكتب فوقه مباشرة', selection);

await page.keyboard.type('1');
await page.click('#math-palette button:has-text("الفراغ التالي")');
await page.keyboard.type('4');
value = await body.inputValue();
check(value === 'احسب $\\frac{1}{4}$', 'التنقّل بين الفراغات يملأ الكسر', value);

// ---------- قاعدة علامات الدولار ----------
await page.click('#math-palette button[aria-label="فيثاغورس"]');
await page.waitForTimeout(300);
value = await body.inputValue();
check(! /\$[^$]*\$[^$]*\$/.test(value.replace(/\$[^$]*\$/g, '')) && (value.match(/\$/g) || []).length % 2 === 0,
    'القالب داخل معادلة قائمة لا يُعشِّش علامات الدولار', value);

await body.click();
await page.keyboard.press('End');
await page.click('#math-palette button[aria-label="فيثاغورس"]');
await page.waitForTimeout(300);
value = await body.inputValue();
check((value.match(/\$/g) || []).length % 2 === 0, 'القالب في نصّ عادي يحمل علامتيه', value);

// ---------- المعاينة الحيّة ----------
const preview = await page.$$eval('#math-palette [x-ref="preview"] .katex', ns => ns.length);
check(preview > 0, 'المعاينة الحيّة تعرض ما سيراه الطالب');

// ---------- اللوحة تخدم الخيارات أيضاً ----------
const option = await page.$('input[name="options[0][text]"]');
if (option) {
    await option.click();
    await page.click('#math-palette button[role="tab"]:has-text("كسور")');
    await page.waitForTimeout(200);
    await page.click('#math-palette button[aria-label="جذر تربيعي"]');
    await page.waitForTimeout(300);
    check((await option.inputValue()) === '$\\sqrt{\\square}$', 'اللوحة نفسها تعمل مع حقول الخيارات');
} else {
    check(false, 'حقل الخيارات موجود');
}

await browser.close();

console.log('');
console.log(failed === 0 ? 'كل فحوص محرّر المعادلات ناجحة' : `${failed} فحص فاشل`);
process.exit(failed === 0 ? 0 : 1);
