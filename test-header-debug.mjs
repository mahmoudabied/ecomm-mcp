import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

await page.goto('http://127.0.0.1:8020', { waitUntil: 'domcontentloaded', timeout: 30000 });
await page.waitForTimeout(3000);

// Detailed DOM inspection of #app direct children
const appChildren = await page.evaluate(() => {
    const app = document.getElementById('app');
    if (!app) return 'NO #app element';
    return Array.from(app.children).map((child, i) => ({
        index: i,
        tag: child.tagName,
        classes: child.className,
        id: child.id,
        rect: child.getBoundingClientRect().toJSON(),
        firstTextContent: child.textContent?.trim().substring(0, 80),
        childCount: child.children.length,
        display: getComputedStyle(child).display,
        visibility: getComputedStyle(child).visibility,
        opacity: getComputedStyle(child).opacity,
        zIndex: getComputedStyle(child).zIndex,
        position: getComputedStyle(child).position,
        backgroundColor: getComputedStyle(child).backgroundColor,
    }));
});
console.log('App children:\n', JSON.stringify(appChildren, null, 2));

// Check for the header include specifically
const headerCheck = await page.evaluate(() => {
    // Look for the top promo bar (black background)
    const topBar = document.querySelector('[class*="bg-black"]');
    const navBar = document.querySelector('nav');
    const logo = document.querySelector('img[alt*="ogo"], img[src*="logo"]');

    // Check all elements that have "hidden lg:block"
    const hiddenLgBlock = document.querySelectorAll('.hidden.lg\\:block');
    const lgHidden = document.querySelectorAll('.lg\\:hidden');

    return {
        topBarFound: !!topBar,
        topBarClasses: topBar?.className,
        topBarRect: topBar?.getBoundingClientRect().toJSON(),
        topBarBg: topBar ? getComputedStyle(topBar).backgroundColor : null,
        navFound: !!navBar,
        navRect: navBar?.getBoundingClientRect().toJSON(),
        logoFound: !!logo,
        logoSrc: logo?.src,
        hiddenLgBlockCount: hiddenLgBlock.length,
        hiddenLgBlockElements: Array.from(hiddenLgBlock).map(el => ({
            tag: el.tagName,
            classes: el.className,
            rect: el.getBoundingClientRect().toJSON(),
            parentClasses: el.parentElement?.className,
            firstChild: el.children[0]?.className,
        })),
        lgHiddenCount: lgHidden.length,
    };
});
console.log('\nHeader check:\n', JSON.stringify(headerCheck, null, 2));

// Screenshot just the very top 150px
await page.screenshot({ path: '/tmp/header-top150.png', clip: { x: 0, y: 0, width: 1440, height: 150 } });

// Check for any CSS that could be hiding header content
const headerStyles = await page.evaluate(() => {
    // Find the header wrapper - should be first major child of #app after flash-group and modal
    const app = document.getElementById('app');
    const children = Array.from(app.children);

    // Find elements with text content related to header
    const results = [];
    for (const child of children) {
        const html = child.outerHTML.substring(0, 500);
        if (html.includes('Summer') || html.includes('Exclusive') || html.includes('ShopNow') ||
            html.includes('logo') || html.includes('Home') || html.includes('Contact') ||
            html.includes('Sign Up') || html.includes('topbar') || html.includes('promo')) {
            results.push({
                tag: child.tagName,
                classes: child.className,
                htmlSnippet: html.substring(0, 300),
            });
        }
    }
    return results;
});
console.log('\nHeader content elements:\n', JSON.stringify(headerStyles, null, 2));

await browser.close();
