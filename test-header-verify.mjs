import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

const errors = [];
page.on('pageerror', err => errors.push(err.message));

await page.goto('http://127.0.0.1:8020', { waitUntil: 'domcontentloaded', timeout: 30000 });
await page.waitForTimeout(3000);

// Full page screenshot
await page.screenshot({ path: '/tmp/theme-full-fixed.png', fullPage: true });
// Header close-up
await page.screenshot({ path: '/tmp/theme-header-fixed.png', clip: { x: 0, y: 0, width: 1440, height: 200 } });

// Check DOM structure
const structure = await page.evaluate(() => {
    const app = document.getElementById('app');
    return Array.from(app.children).map((child, i) => ({
        index: i,
        tag: child.tagName,
        classes: child.className.substring(0, 60),
        height: child.getBoundingClientRect().height,
        y: child.getBoundingClientRect().y,
        text: child.textContent?.trim().substring(0, 60),
    }));
});
console.log('DOM structure:', JSON.stringify(structure, null, 2));

// Header details
const header = await page.evaluate(() => {
    const desktopHeader = document.querySelector('#app > .hidden.lg\\:block');
    if (!desktopHeader) return 'No desktop header found';

    const topBar = desktopHeader.querySelector('.bg-black');
    const navBar = desktopHeader.querySelector('.border-b');

    return {
        headerHeight: desktopHeader.getBoundingClientRect().height,
        topBarFound: !!topBar,
        topBarHeight: topBar?.getBoundingClientRect().height,
        topBarBg: topBar ? getComputedStyle(topBar).backgroundColor : null,
        topBarText: topBar?.textContent?.trim().substring(0, 80),
        navBarFound: !!navBar,
        navBarHeight: navBar?.getBoundingClientRect().height,
        navBarText: navBar?.textContent?.trim().substring(0, 80),
        logoSrc: desktopHeader.querySelector('img')?.src,
    };
});
console.log('Header:', JSON.stringify(header, null, 2));

// Check main starts after header
const positions = await page.evaluate(() => {
    const header = document.querySelector('#app > .hidden.lg\\:block');
    const main = document.querySelector('#main');
    return {
        headerBottom: header?.getBoundingClientRect().bottom,
        mainTop: main?.getBoundingClientRect().top,
        gapBetween: main?.getBoundingClientRect().top - (header?.getBoundingClientRect().bottom || 0),
    };
});
console.log('Positions:', JSON.stringify(positions));

console.log('JS Errors:', errors.length ? errors : 'none');

await browser.close();
