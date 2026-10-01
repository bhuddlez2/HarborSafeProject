import { expect, test } from '@playwright/test';

/*
The Events & News page must never sit on "Loading…" indefinitely.

Every section of that page is client-rendered, and the prerendered HTML carries
nothing but the word "Loading…". So until the page's load effect settles, that
one word IS the whole page — and for this organisation a visitor stuck there has
no crisis line in front of them.

A load that *rejects* was always handled. These cover the two cases that were
not: a load that never settles at all, and getting back out of the error state.

?preview=hang is the hook for the first of those; see NEVER in
website/frontend/src/app/lib/content.js.
*/

/*
The safety notice modal is z-200 and covers the page, so it intercepts clicks.
Start every test with it already dismissed for the tab, matching the pattern in
homepage.spec.js.
*/
const SAFETY_NOTICE_KEY = 'hshac-safety-notice-dismissed';

test.beforeEach(async ({ page }) => {
  await page.addInitScript((key) => window.sessionStorage.setItem(key, '1'), SAFETY_NOTICE_KEY);
});

const ERROR_HEADING = "We couldn't load this right now";
const CRISIS_LINE = '(423) 476-3886';

test('content that never arrives times out into the error state', async ({ page }) => {
  // LOAD_TIMEOUT_MS is 12s, so this test needs more than the 30s default.
  test.setTimeout(60000);

  await page.goto('/events?preview=hang');

  // The loading state is expected at first - that is the point.
  await expect(page.getByText('Loading…')).toBeVisible();

  // ...but it must not be the end state.
  await expect(page.getByText(ERROR_HEADING)).toBeVisible({ timeout: 30000 });
  await expect(page.getByText('Loading…')).toHaveCount(0);

  // The thing that actually matters on this page. Scoped to the error block,
  // since the footer carries the same number.
  const errorBlock = page.locator('div').filter({ hasText: ERROR_HEADING }).last();
  await expect(errorBlock.getByText(CRISIS_LINE)).toBeVisible();
  await expect(page.getByRole('button', { name: 'Try again' })).toBeVisible();
});

test('a simulated failure shows the crisis line and offers a retry', async ({ page }) => {
  await page.goto('/events?preview=error');

  await expect(page.getByText(ERROR_HEADING)).toBeVisible();

  const errorBlock = page.locator('div').filter({ hasText: ERROR_HEADING }).last();
  await expect(errorBlock.getByText(CRISIS_LINE)).toBeVisible();
  await expect(page.getByRole('button', { name: 'Try again' })).toBeVisible();
});

test('retrying from the error state reloads the content', async ({ page }) => {
  await page.goto('/events?preview=error');

  const retry = page.getByRole('button', { name: 'Try again' });
  await expect(retry).toBeVisible();

  /*
  The effect reads ?preview from the URL on every attempt, so clear it first -
  retrying with preview=error still set would be guaranteed to fail again and
  the test would prove nothing. replaceState does not re-render React, so the
  click below is what re-runs the load.
  */
  await page.evaluate(() => window.history.replaceState({}, '', '/events'));
  await retry.click();

  await expect(page.getByRole('heading', { name: 'Upcoming Events' })).toBeVisible();
  await expect(page.getByText('Loading…')).toHaveCount(0);
  await expect(page.getByText(ERROR_HEADING)).toHaveCount(0);
});
