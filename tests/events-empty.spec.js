import { expect, test } from '@playwright/test';
import { stubContentApi } from './support/content-api';

/*
The Events & News page with nothing to show.

Having no events and no newsletters is a normal state, not a failure — it is
exactly what the site looks like before staff publish anything in the panel.
The page must say so plainly rather than sitting on "Loading…".

The page fetches both lists from the Laravel API at view time, and
playwright.config.js boots only the website, so these specs stub the endpoints
rather than requiring a running backend. See ./support/content-api.js.
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

  await stubContentApi(page, { events: [], newsletters: [] });
  await page.goto('/events');

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

test('the page renders content served by the API', async ({ page }) => {
  await stubContentApi(page);
  await page.goto('/events');

  await expect(page.getByRole('heading', { name: 'Upcoming Events' })).toBeVisible();
  await expect(page.getByText('Moonlight Walk')).toBeVisible();
  await expect(page.getByText('Autumn Issue')).toBeVisible();
  await expect(page.getByText('Loading…')).toHaveCount(0);
});

test('an event with no location or image still renders', async ({ page }) => {
  /*
  The shape a freshly created event actually has: the panel requires only a
  title and a start time, so every nested object can legitimately be null. The
  page guards each one, and this is the regression test for that.
  */
  await stubContentApi(page, {
    events: [{
      id: 'evt-bare',
      title: 'Bare Minimum Event',
      summary: null,
      description: [],
      startsAt: '2030-12-01T10:00:00-05:00',
      endsAt: null,
      allDay: false,
      recurrence: null,
      location: null,
      image: null,
      registration: null,
      category: null,
      isPublished: true,
      isCancelled: false,
    }],
    newsletters: [],
  });

  const pageErrors = [];
  page.on('pageerror', (error) => pageErrors.push(error.message));

  await page.goto('/events');

  await expect(page.getByText('Bare Minimum Event')).toBeVisible();
  expect(pageErrors).toEqual([]);
});

test('the API returning an error shows the error state, not a blank page', async ({ page }) => {
  await page.route('**/api/public/events', (route) => route.fulfill({ status: 500, body: '{}' }));
  await page.route('**/api/public/newsletters', (route) => route.fulfill({ status: 500, body: '{}' }));

  await page.goto('/events');

  await expect(page.getByText("We couldn't load this right now")).toBeVisible();
  // The crisis line is what a visitor needs when content fails.
  await expect(page.getByRole('button', { name: 'Try again' })).toBeVisible();
});
