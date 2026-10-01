import { expect, test } from '@playwright/test';

/*
The Events & News page with nothing to show.

Having no events and no newsletters is a normal state, not a failure — it is
exactly what the site looks like before staff publish anything through the
panel. The page must say so plainly rather than sitting on "Loading…".

?preview=empty is the page's own hook for it: getEvents() and getNewsletters()
in website/frontend/src/app/lib/content.js short-circuit to [] when it is set.
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

test('no content reads as empty, not as loading or broken', async ({ page }) => {
  const pageErrors = [];
  page.on('pageerror', (error) => pageErrors.push(error.message));

  await page.goto('/events?preview=empty');

  // Both sections say so in their own words.
  await expect(page.getByText('No upcoming events right now')).toBeVisible();
  await expect(page.getByText('No newsletters yet')).toBeVisible();

  // And neither the loading nor the error state is left on screen.
  await expect(page.getByText('Loading…')).toHaveCount(0);
  await expect(page.getByText("We couldn't load this right now")).toHaveCount(0);

  // An empty list must not throw - the carousel is skipped entirely when there
  // is nothing to put in it.
  expect(pageErrors).toEqual([]);
});

test('content renders normally without a preview flag', async ({ page }) => {
  await page.goto('/events');

  await expect(page.getByRole('heading', { name: 'Upcoming Events' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Newsletters' })).toBeVisible();
  await expect(page.getByText('Loading…')).toHaveCount(0);
});
