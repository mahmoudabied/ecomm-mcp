import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

const errors = [];
page.on('pageerror', err => errors.push(err.message));

await page.goto('http://127.0.0.1:8020', { waitUntil: 'load', timeout: 30000 });
await page.waitForTimeout(3000);

// Full page screenshot
await page.screenshot({ path: '/tmp/theme-final-full.png', fullPage: true });
// Header
await page.screenshot({ path: '/tmp/theme-final-header.png', clip: { x: 0, y: 0, width: 1440, height: 200 } });

// Check images
const images = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('img')).map(img => ({
        alt: img.alt || img.src.split('/').pop(),
        loaded: img.naturalWidth > 0,
        size: `${img.naturalWidth}x${img.naturalHeight}`,
        src: img.src.substring(img.src.lastIndexOf('/') + 1),
    }));
});
console.log('Images:', JSON.stringify(images, null, 2));

// Header check
const header = await page.evaluate(() => {
    const h = document.querySelector('#app > .hidden.lg\\:block');
    return {
        height: h?.getBoundingClientRect().height,
        topBarVisible: !!h?.querySelector('.bg-black'),
        navVisible: !!h?.querySelector('.border-b'),
    };
});
console.log('Header:', JSON.stringify(header));

console.log('JS Errors:', errors.length ? JSON.stringify(errors) : 'none');

await browser.close();
