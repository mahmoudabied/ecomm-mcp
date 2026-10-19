/**
 * Playwright configuration for the marketing automation E2E specs.
 *
 * Start the store with the log mailer before running:
 *   MAIL_MAILER=log php artisan serve --port=8099
 */
export default {
  testDir: '.',
  timeout: 60_000,
  workers: 1,
  retries: 0,
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8099',
    screenshot: 'only-on-failure',
  },
};
