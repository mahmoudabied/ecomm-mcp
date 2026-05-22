import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

const errors = [];
page.on('pageerror', err => errors.push(err.message));
page.on('console', msg => {
    if (msg.type() === 'error') errors.push(msg.text());
});

await page.goto('http://127.0.0.1:8020', { waitUntil: 'load', timeout: 30000 });
await page.waitForTimeout(3000);

// Full page screenshot
await page.screenshot({ path: '/tmp/theme-full.png', fullPage: true });
// Header area
await page.screenshot({ path: '/tmp/theme-header.png', clip: { x: 0, y: 0, width: 1440, height: 250 } });

// Check header
const headerInfo = await page.evaluate(() => {
    const header = document.querySelector('.hidden.lg\\:block');
    const vueApp = document.querySelector('#app').__vue_app__;
    return {
        headerVisible: header ? getComputedStyle(header).display !== 'none' : false,
        vueMounted: !!vueApp,
        vueComponents: vueApp ? Object.keys(vueApp._context.components) : [],
    };
});
console.log('Header:', JSON.stringify(headerInfo, null, 2));

// Check images
const images = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('img')).map(img => ({
        alt: img.alt,
        loaded: img.naturalWidth > 0,
        size: `${img.naturalWidth}x${img.naturalHeight}`,
    }));
});
console.log('Images:', JSON.stringify(images, null, 2));

// Check JS errors
console.log('Errors:', JSON.stringify(errors, null, 2));

await browser.close();
