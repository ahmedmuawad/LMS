/**
 * فحص السلوك والوصولية — بوابة CI.
 * الطبقات (نافذة/درج/قائمة) تُختبر فعلياً: تفتح، تحبس التركيز،
 * تُغلق بـ Escape، وتُغلق بالنقر خارجها. والتبويبات تعمل بلوحة المفاتيح.
 *
 *   node tests/Browser/behaviour.mjs [baseUrl]
 */
import { chromium } from 'playwright';

const BASE = process.argv[2] || 'http://127.0.0.1:8899';
const results = [];
const check = (name, ok, detail = '') => {
    results.push({ name, ok, detail });
    console.log(`${ok ? '✓' : '✕'} ${name}${detail && !ok ? ' — ' + detail : ''}`);
};

const browser = await chromium.launch(process.env.PLAYWRIGHT_CHROMIUM ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM } : {});
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
await page.goto(BASE + '/design-system', { waitUntil: 'networkidle' });

const modal  = page.locator('[role="dialog"]', { hasText: 'حذف المجموعة' }).first();
const opener = page.getByRole('button', { name: 'افتح نافذة' });

// --- النافذة ---
await opener.click();
await page.waitForTimeout(300);
check('النافذة تفتح عند الضغط', await modal.isVisible());

const trapped = await page.evaluate(() => {
    // ملاحظة: offsetParent يساوي null للعناصر position:fixed حتى وهي ظاهرة
    const visible = (el) => el.getBoundingClientRect().width > 0 && getComputedStyle(el).display !== 'none';
    const dlg = [...document.querySelectorAll('[role="dialog"]')].find(visible);
    return dlg ? dlg.contains(document.activeElement) : false;
});
check('التركيز محبوس داخل النافذة', trapped);

await page.keyboard.press('Escape');
await page.waitForTimeout(300);
check('Escape يغلق النافذة', !(await modal.isVisible()));

// --- الدرج ---
const drawer = page.locator('[role="dialog"]', { hasText: 'تصفية الطلاب' }).first();
await page.getByRole('button', { name: 'افتح درجاً' }).click();
await page.waitForTimeout(300);
check('الدرج يفتح', await drawer.isVisible());
await page.keyboard.press('Escape');
await page.waitForTimeout(300);
check('Escape يغلق الدرج', !(await drawer.isVisible()));

// --- التنبيه العائم ---
await page.getByRole('button', { name: 'أظهر تنبيهاً' }).click();
await page.waitForTimeout(300);
check('التنبيه العائم يظهر', await page.getByText('تم حفظ التغييرات').isVisible());

// --- القائمة المنسدلة ---
await page.getByRole('button', { name: /إجراءات/ }).click();
await page.waitForTimeout(250);
const item = page.getByRole('menuitem', { name: 'تكرار الكورس' });
check('القائمة المنسدلة تفتح', await item.isVisible());
await page.mouse.click(20, 20);
await page.waitForTimeout(250);
check('النقر خارج القائمة يغلقها', !(await item.isVisible()));

// --- التبويبات ---
const tabStudents = page.getByRole('tab', { name: 'الطلاب' });
await tabStudents.click();
await page.waitForTimeout(200);
check('التبويب يبدّل المحتوى', await page.getByText('١٬٣٨٤ طالباً مسجّلاً.').isVisible());
check('التبويب النشط معلَّم بـ aria-selected', (await tabStudents.getAttribute('aria-selected')) === 'true');

// --- الوضع الداكن ---
await page.getByRole('button', { name: 'داكن' }).click();
await page.waitForTimeout(200);
check('زر الوضع الداكن يضبط data-theme', (await page.getAttribute('html', 'data-theme')) === 'dark');
await page.reload({ waitUntil: 'networkidle' });
check('الوضع يبقى محفوظاً بعد إعادة التحميل', (await page.getAttribute('html', 'data-theme')) === 'dark');

// --- رابط التخطّي ---
await page.evaluate(() => localStorage.removeItem('theme'));
await page.reload({ waitUntil: 'networkidle' });
await page.keyboard.press('Tab');
check('أول عنصر بالـ Tab هو رابط تخطّي المحتوى',
    (await page.evaluate(() => document.activeElement?.textContent?.trim())) === 'تخطَّ إلى المحتوى');

await browser.close();
const failed = results.filter((r) => !r.ok).length;
console.log(failed ? `\n${failed} فحص سلوك فاشل` : `\nكل فحوص السلوك ناجحة (${results.length})`);
process.exit(failed ? 1 : 0);
