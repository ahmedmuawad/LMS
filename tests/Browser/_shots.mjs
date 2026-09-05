/** لقطات كاملة الطول للثيم في الوضعين وبمقاسين. */
import { chromium } from 'playwright';

const BASE = process.argv[2] || 'http://demo.localhost:8123';
const OUT = process.argv[3] || '.';
const PATHS = process.argv.slice(4).length ? process.argv.slice(4) : ['/'];

const browser = await chromium.launch();

for (const scheme of ['light', 'dark']) {
    for (const [name, width, height] of [['desktop', 1280, 900], ['mobile', 390, 844]]) {
        const context = await browser.newContext({
            viewport: { width, height },
            colorScheme: scheme,
            deviceScaleFactor: 1,
        });
        const page = await context.newPage();

        for (const path of PATHS) {
            await page.goto(BASE + path, { waitUntil: 'networkidle' });
            await page.waitForTimeout(400);

            const slug = path === '/' ? 'home' : path.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '');
            const file = `${OUT}/${slug}-${name}-${scheme}.png`;

            await page.screenshot({ path: file, fullPage: true });
            console.log(file);
        }

        await context.close();
    }
}

await browser.close();
