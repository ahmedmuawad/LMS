/**
 * جلسة مشتركة لفحوص المتصفح.
 *
 * الشاشات المحمية لا تُفحص وهي خلف صفحة الدخول: بغير جلسة كانت
 * البوابات تفحص شاشة الدخول وتظنّها اللوحة. تُمرَّر البيانات عبر
 * البيئة حتى لا تُكتب كلمة مرور في أي ملف:
 *
 *   GATE_EMAIL · GATE_PASSWORD · GATE_LOGIN_PATH (افتراضياً /login)
 */

export async function openContext(browser, base, options = {}) {
    const context = await browser.newContext(options);

    const email = process.env.GATE_EMAIL;
    const password = process.env.GATE_PASSWORD;

    if (!email || !password) {
        return context;
    }

    const path = process.env.GATE_LOGIN_PATH || '/login';
    const page = await context.newPage();

    await page.goto(base + path, { waitUntil: 'networkidle' });
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await Promise.all([
        page.waitForLoadState('networkidle'),
        page.click('button[type="submit"]'),
    ]);

    // الوقوف على صفحة الدخول بعد الإرسال يعني فشل الدخول — نوقف الفحص
    // بدل أن نمضي فنقيس شاشة الدخول ونحسبها اللوحة.
    if (new URL(page.url()).pathname === path) {
        const error = await page.textContent('body').catch(() => '');
        throw new Error(`تعذّر تسجيل الدخول إلى ${base}${path}\n${error.slice(0, 400)}`);
    }

    await page.close();

    return context;
}
