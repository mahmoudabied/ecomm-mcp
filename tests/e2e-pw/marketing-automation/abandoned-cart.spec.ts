/**
 * Abandoned cart recovery — end-to-end smoke test (spec US1 Independent Test).
 *
 * This suite drives a real browser against a running Bagisto instance.
 * The scheduler-driven steps (detect + dispatch) run through artisan, which a
 * Playwright spec cannot invoke; the spec therefore pauses and documents the
 * two commands an operator runs between the browser steps (see runAutomation()
 * below, or execute them manually while the test is paused).
 *
 * Prerequisites (see specs/002-marketing-automation/quickstart.md):
 * - A local install with MAIL_MAILER=log and a real queue driver.
 * - npm --prefix packages/Webkul/AmacommerceTheme run build
 * - php artisan automation:install
 *
 * Manual step between the two browser phases (the suite cannot run artisan):
 *   php artisan automation:detect-abandoned-carts
 *   php artisan automation:dispatch-due --now
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

  await expect(page).not.toHaveURL(new RegExp(adminUrl));
}

test('admin creates the abandoned cart flow and a guest gets the recovery email', async ({ page }) => {
  // Phase 1 — create and activate the flow from the template.
  await loginAsAdmin(page);

  await page.goto(`${adminUrl}/marketing/automation/flows`);

  await page.getByRole('button', { name: /create flow/i }).click();

  await page.getByLabel(/name/i).fill('E2E Abandoned Cart');

  const templateSelect = page.getByLabel(/template|start from/i);
  if (await templateSelect.count()) {
    await templateSelect.selectOption({ label: /abandoned cart/i });
  }

  await page.getByRole('button', { name: /save|create/i }).click();

  await page.goto(`${adminUrl}/marketing/automation/flows`);
  await page.getByRole('row', { name: /E2E Abandoned Cart/i }).getByRole('link', { name: /edit/i }).click();

  // One-minute trigger delay: a short enough window for the e2e run.
  await page.getByLabel(/abandon delay/i).fill('1');

  // Replace the WhatsApp step with a plain email step, since no provider is configured.
  const whatsappCard = page.locator('[data-step-type="send_whatsapp"]');
  if (await whatsappCard.count()) {
    await whatsappCard.getByRole('button', { name: /remove|delete/i }).click();
  }

  await page.getByRole('button', { name: /^activate$/i }).click();

  await expect(page.getByText(/active/i).first()).toBeVisible();

  // Phase 2 — a guest shops, leaves an email at checkout and abandons the cart.
  await page.goto(shopUrl);

  await page.getByRole('link', { name: /shop|category/i }).first().click();
  await page.getByRole('link', { name: /product|view/i }).first().click();

  await page.getByRole('button', { name: /add to cart/i }).click();
  await expect(page.getByRole('link', { name: /cart/i })).toBeVisible();

  await page.goto('/checkout/cart');
  await page.getByRole('link', { name: /continue|checkout/i }).click();

  await page.getByRole('textbox', { name: /email/i }).fill(guestEmail);

  // Leave before completing checkout: the cart goes idle with the email captured.
  await page.close();
});

test('the restore link in the logged email rebuilds the cart', async ({ page }) => {
  test.setTimeout(120_000);

  // Phase 3 — run by the operator while this spec is paused, because a
  // Playwright spec cannot invoke artisan on the host:
  //
  //   php artisan automation:detect-abandoned-carts
  //   php artisan automation:dispatch-due --now
  //
  // Then read storage/logs/laravel.log for the message with the restore URL.
  const logPath = process.env.E2E_MAIL_LOG ?? 'storage/logs/laravel.log';
  const { readFileSync } = await import('node:fs');

  const log = readFileSync(logPath, 'utf8');
  const matches = log.match(/automation\/cart\/restore\/[A-Za-z0-9._-]+/g);

  expect(matches, 'a restore URL in the mail log').not.toBeNull();

  await page.goto(`/${matches.at(-1)}`);

  await expect(page).toHaveURL(/checkout\/cart/);

  await expect(page.getByRole('main')).not.toContainText(/your cart is empty/i);
});
