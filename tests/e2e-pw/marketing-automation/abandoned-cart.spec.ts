/**
 * Abandoned cart recovery — end-to-end smoke test (spec US1 Independent Test).
 *
 * This suite drives a real browser against a running Bagisto instance.
 * Start the store with the log mailer first:
 *
 *   MAIL_MAILER=log php artisan serve --port=8099
 *
 * Between the two tests an operator runs the scheduler steps (a Playwright
 * spec cannot invoke artisan), which detect the idle cart and send the
 * recovery email into storage/logs/laravel.log:
 *
 *   php artisan automation:detect-abandoned-carts
 *   php artisan automation:dispatch-due --now
 *
 * Run each phase separately:
 *   npx playwright test --config tests/e2e-pw/playwright.config.ts --grep-invert "restore link"
 *   ... operator commands ...
 *   npx playwright test --config tests/e2e-pw/playwright.config.ts --grep "restore link"
 */

import { test, expect } from '@playwright/test';

const adminUrl = '/admin';
const shopUrl = '/';
const adminEmail = process.env.E2E_ADMIN_EMAIL ?? 'admin@example.com';
const adminPassword = process.env.E2E_ADMIN_PASSWORD ?? 'admin123';
const guestEmail = 'e2e-guest@example.com';

async function loginAsAdmin(page) {
  await page.goto(adminUrl);

  await page.getByRole('textbox', { name: /email/i }).fill(adminEmail);
  await page.getByRole('textbox', { name: /password/i }).fill(adminPassword);
  await page.getByRole('button', { name: /sign in|login/i }).click();

  await expect(page).toHaveURL(/admin\/dashboard/);
}

test('admin creates the abandoned cart flow and a guest abandons a cart', async ({ page }) => {
  await loginAsAdmin(page);

  await page.goto(`${adminUrl}/marketing/automation/flows`);

  await page.locator('.primary-button', { hasText: /create flow/i }).first().click();

  const nameInput = page.locator('input[v-model="form.name"]');

  await expect(nameInput).toBeVisible();

  await nameInput.fill('E2E Abandoned Cart');

  await page
    .locator('select[v-model="form.template_key"]')
    .selectOption({ label: /abandoned cart/i });

  await page.locator('.primary-button', { hasText: /^save$/i }).last().click();

  await expect(page.getByText(/e2e abandoned cart/i).first()).toBeVisible();

  await page
    .getByRole('row', { name: /e2e abandoned cart/i })
    .getByTitle(/edit/i)
    .click();

  await page.locator('.secondary-button', { hasText: /^activate$/i }).first().click();

  await expect(page.getByText(/flow activated|active/i).first()).toBeVisible();

  // The guest shops, leaves an email at checkout and abandons the cart.
  await page.goto(shopUrl);

  await page.getByRole('link', { name: /shop|category/i }).first().click();

  await page.getByRole('link', { name: /product|view/i }).first().click();

  await page.getByRole('button', { name: /add to cart/i }).first().click();

  await expect(page.getByRole('link', { name: /cart/i }).first()).toBeVisible();

  await page.goto('/checkout/cart');

  await page.getByRole('link', { name: /continue|checkout/i }).first().click();

  const captureEmail = page.locator('#ma-contact-capture input[type="email"]');

  await expect(captureEmail).toBeVisible();

  await captureEmail.fill(guestEmail);

  // Leave before completing checkout: the cart goes idle with the email captured.
  await page.close();
});

test('the restore link in the logged email rebuilds the cart', async ({ page }) => {
  test.setTimeout(120_000);

  const logPath = process.env.E2E_MAIL_LOG ?? 'storage/logs/laravel.log';
  const { readFileSync } = await import('node:fs');

  const log = readFileSync(logPath, 'utf8');
  const matches = log.match(/automation\/cart\/restore\/[A-Za-z0-9._-]+/g);

  expect(matches, 'a restore URL in the mail log').not.toBeNull();

  await page.goto(`/${matches.at(-1)}`);

  await expect(page).toHaveURL(/checkout\/cart/);

  await expect(page.getByRole('main')).not.toContainText(/your cart is empty/i);
});
