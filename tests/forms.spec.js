import { test, expect } from '@playwright/test';

/*
The two public forms, Request Resources (/contact) and Share Feedback (/feedback):
how they mark required and optional fields (Burchfield B23/B30, 2026-10-28).

  - A required field has a red asterisk (hidden from screen readers) and the
    control's own `required`, so a screen reader says "required".
  - An optional field shows grey "Optional" ghost text, which a screen reader
    can't rely on, so its label also says ", optional" to screen readers only.
  - A form with an asterisk shows "* Required" just above its submit button.

playwright.config.js boots only the website, so the option lists are stubbed.
*/

const SAFETY_NOTICE_KEY = 'hshac-safety-notice-dismissed';

// "Last name, optional" - browsers put a space before the hidden ", optional",
// which screen readers don't voice, so allow it.
const optionalName = (label) => new RegExp(`^${label}\\s*, optional$`);

test.beforeEach(async ({ page }) => {
  await page.addInitScript((key) => window.sessionStorage.setItem(key, '1'), SAFETY_NOTICE_KEY);
  const lists = {
    services: [{ id: 1, Name: 'Counseling' }],
    resources: [{ id: 1, Name: 'Shelter' }],
    counties: [{ id: 1, Name: 'Bradley' }],
  };
  await page.route('**/api/public/*', (route) => {
    const name = new URL(route.request().url()).pathname.split('/').pop();
    if (route.request().method() === 'GET' && lists[name]) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(lists[name]) });
    }
    return route.fulfill({ status: 404, body: '' });
  });
});

test('Request Resources: optional fields are announced as optional, required ones as required', async ({ page }) => {
  await page.goto('/contact/');

  const firstName = page.getByRole('textbox', { name: 'First name', exact: true });
  await expect(firstName).toBeVisible();
  await expect(firstName).toHaveAttribute('required', '');

  await expect(page.getByRole('textbox', { name: optionalName('Last name') })).toBeVisible();
  await expect(page.getByRole('combobox', { name: optionalName('County') })).toBeVisible();
  await expect(page.getByRole('textbox', { name: optionalName('Anything you would like us to know') })).toBeVisible();

  // Email and phone are one-of-two: the group says so, neither box is called optional.
  await expect(page.getByRole('textbox', { name: 'Email address', exact: true })).toBeVisible();
  await expect(page.getByRole('textbox', { name: 'Safe phone number', exact: true })).toBeVisible();
});

// Get Support's "Request this service" links: the named types are ticked by name
// (any case, unknown names ignored), and the address is cleaned at once so
// browser history shows only /contact/.
test('Request Resources: a ?need= link ticks that type, then leaves the address clean', async ({ page }) => {
  await page.goto('/contact/?need=shelter&need=Not+A+Type');

  await expect(page.getByRole('checkbox', { name: 'Shelter' })).toBeChecked();
  expect(new URL(page.url()).search).toBe('');
});

test('Request Resources: "* Required" sits just above Send request', async ({ page }) => {
  await page.goto('/contact/');

  const note = page.getByText('* Required', { exact: true });
  const button = page.getByRole('button', { name: 'Send request' });
  await expect(note).toBeVisible();
  await expect(note).toHaveAttribute('aria-hidden', 'true');

  const noteBox = await note.boundingBox();
  const buttonBox = await button.boundingBox();
  expect(noteBox.y + noteBox.height).toBeLessThanOrEqual(buttonBox.y);
  expect(buttonBox.y - (noteBox.y + noteBox.height)).toBeLessThan(40);
});

test('Request Resources: no help text reference points at nothing', async ({ page }) => {
  await page.goto('/contact/');
  await expect(page.getByRole('textbox', { name: 'First name', exact: true })).toBeVisible();

  const dangling = await page.evaluate(() =>
    [...document.querySelectorAll('[aria-describedby]')].flatMap((el) =>
      el.getAttribute('aria-describedby').split(/\s+/).filter((id) => !document.getElementById(id))));
  expect(dangling).toEqual([]);
});

test('Share Feedback: Comments is announced as optional, and "* Required" sits above Send feedback', async ({ page }) => {
  await page.goto('/feedback/');

  await expect(page.getByRole('textbox', { name: optionalName('Comments') })).toBeVisible();

  const note = page.getByText('* Required', { exact: true });
  const button = page.getByRole('button', { name: 'Send feedback' });
  await expect(note).toBeVisible();
  const noteBox = await note.boundingBox();
  const buttonBox = await button.boundingBox();
  expect(noteBox.y + noteBox.height).toBeLessThanOrEqual(buttonBox.y);
});

test('Share Feedback: after Send feedback, the visitor is taken to the thank-you or the error message', async ({ page }) => {
  let reply = { status: 201, body: '{"message":"ok"}' };
  await page.route('**/api/public/service-feedback', (route) =>
    route.fulfill({ status: reply.status, contentType: 'application/json', body: reply.body }));
  await page.goto('/feedback/');
  await page.getByLabel('Which service is this about?').selectOption('1');
  await page.getByLabel('5', { exact: true }).check({ force: true });

  // Too many submissions: the error appears above the form and takes focus.
  reply = { status: 429, body: '{"message":"Too many"}' };
  await page.getByRole('button', { name: 'Send feedback' }).click();
  const error = page.getByRole('alert').filter({ hasText: 'Too many submissions' });
  await expect(error).toBeFocused();
  await expect(error).toBeInViewport();

  reply = { status: 201, body: '{"message":"ok"}' };
  await page.getByRole('button', { name: 'Send feedback' }).click();
  const thanks = page.getByRole('alert').filter({ hasText: 'your feedback has been received' });
  await expect(thanks).toBeFocused();
  await expect(thanks).toBeInViewport();
});
