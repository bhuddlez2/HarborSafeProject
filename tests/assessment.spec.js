import { test, expect } from '@playwright/test';

/*
The civilian lethality assessment (website/frontend/src/app/assessment), moved here
from portal/frontend.

playwright.config.js boots only the website, so the API is stubbed. That also lets
these specs check what the page SENDS: one request to the public endpoint, contact
details only when the submitter chose to give them, and nothing to any API path
outside /api/public. What the backend does with it is covered by
portal/backend/tests/Feature/Public/CivilianAssessmentTest.php.
*/

const SAFETY_NOTICE_KEY = 'hshac-safety-notice-dismissed';

const RELATIONSHIPS = [
  { value: 'spouse', label: 'Spouse' },
  { value: 'dating_partner', label: 'Dating Partner' },
  { value: 'other', label: 'Other (Please Specify)' },
];

async function stubAssessmentApi(page, { status = 201 } = {}) {
  const submissions = [];
  const strayApiCalls = [];

  await page.route('**/api/**', (route) => {
    const url = new URL(route.request().url());

    if (url.pathname === '/api/public/relationships') {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(RELATIONSHIPS) });
    }

    if (url.pathname === '/api/public/civilian-assessments' && route.request().method() === 'POST') {
      submissions.push(route.request().postDataJSON());
      return route.fulfill({
        status,
        contentType: 'application/json',
        body: JSON.stringify(status === 201 ? { message: 'Assessment received' } : { message: 'Too Many Attempts.' }),
      });
    }

    strayApiCalls.push(`${route.request().method()} ${url.pathname}`);
    return route.fulfill({ status: 404, body: '' });
  });

  return { submissions, strayApiCalls };
}

test.beforeEach(async ({ page }) => {
  // The site-wide safety notice (layout.js) covers the page; this spec is about the
  // assessment's own confidentiality notice instead.
  await page.addInitScript((key) => window.sessionStorage.setItem(key, '1'), SAFETY_NOTICE_KEY);
});

async function acceptNotice(page) {
  const notice = page.getByRole('dialog', { name: 'Confidentiality notice' });
  await expect(notice).toBeVisible();
  await notice.getByRole('button', { name: 'I understand, continue' }).click();
  await expect(notice).toBeHidden();
}

async function fillVictim(page) {
  await page.getByLabel('First name').fill('Jane');
  await page.getByLabel('Last name').fill('Doe');
  await page.getByLabel('Date of birth').fill('1990-04-12');
  await page.getByLabel('Sex').selectOption('F');
  await page.getByLabel(/phone number/i).fill('(423) 555-0100');
  await page.getByRole('button', { name: 'Continue' }).click();
}

async function fillOffender(page) {
  await page.getByLabel('First name').fill('John');
  await page.getByLabel('Last name').fill('Roe');
  await page.getByLabel('Sex').selectOption('M');
  await page.getByLabel('Relationship to victim').selectOption('spouse');
  await page.getByRole('button', { name: 'Continue' }).click();
}

async function answerAll(page, pattern = (n) => n % 2 === 0) {
  await page.getByRole('button', { name: 'Begin assessment' }).click();
  for (let n = 1; n <= 11; n++) {
    // The answer buttons exist twice (desktop card, mobile bar); the desktop ones are visible here.
    await page.getByRole('button', { name: pattern(n) ? 'Yes' : 'No', exact: true }).first().click();
  }
  await expect(page.getByRole('heading', { name: 'Review & submit' })).toBeVisible();
}

test('Get Support links to the assessment on this site', async ({ page }) => {
  await page.goto('/get-support/');
  const link = page.getByRole('link', { name: 'Take the assessment' });
  await expect(link).toHaveAttribute('href', '/assessment/');
});

test('a submission for yourself sends one request with every answer and no contact details', async ({ page }) => {
  const api = await stubAssessmentApi(page);
  await page.goto('/assessment/');
  await acceptNotice(page);

  await page.getByRole('button', { name: 'Myself' }).click();
  await page.getByRole('button', { name: 'Continue' }).click();
  await fillVictim(page);
  await fillOffender(page);
  await answerAll(page);

  await page.getByRole('button', { name: 'Submit assessment' }).click();
  await expect(page.getByRole('heading', { name: 'Assessment submitted' })).toBeVisible();

  expect(api.submissions).toHaveLength(1);
  const sent = api.submissions[0];
  expect(sent).toMatchObject({
    ForWhom: 'self',
    VictimFirstName: 'Jane',
    VictimSafePhoneNumber: '(423) 555-0100',
    OffenderVictimRelationship: 'spouse',
    OffenderVictimRelationshipOther: null,
    RiskIndicator1: false,
    RiskIndicator2: true,
    RiskIndicator11: false,
  });
  expect(Object.keys(sent).filter((k) => k.startsWith('Submitter') || k === 'Anonymous')).toEqual([]);
  expect(Object.keys(sent).filter((k) => /ID$/.test(k))).toEqual([]);
  expect(api.strayApiCalls).toEqual([]);
});

test('an anonymous report for someone else sends no contact details', async ({ page }) => {
  const api = await stubAssessmentApi(page);
  await page.goto('/assessment/');
  await acceptNotice(page);

  await page.getByRole('button', { name: 'Someone else' }).click();
  await page.getByRole('button', { name: 'Yes', exact: true }).click();
  await page.getByRole('button', { name: 'Continue' }).click();
  await expect(page.getByRole('heading', { name: 'About the victim' })).toBeVisible();
  await fillVictim(page);
  await fillOffender(page);
  await answerAll(page);
  await page.getByRole('button', { name: 'Submit assessment' }).click();
  await expect(page.getByRole('heading', { name: 'Assessment submitted' })).toBeVisible();

  const sent = api.submissions[0];
  expect(sent.ForWhom).toBe('other');
  expect(sent.Anonymous).toBe(true);
  expect(Object.keys(sent).filter((k) => k.startsWith('Submitter'))).toEqual([]);
});

test('a named report for someone else includes the submitter', async ({ page }) => {
  const api = await stubAssessmentApi(page);
  await page.goto('/assessment/');
  await acceptNotice(page);

  await page.getByRole('button', { name: 'Someone else' }).click();
  await page.getByRole('button', { name: 'No', exact: true }).click();
  await page.getByRole('button', { name: 'Continue' }).click();

  await expect(page.getByRole('heading', { name: 'Your information' })).toBeVisible();
  await page.getByLabel('First name').fill('Sam');
  await page.getByLabel('Last name').fill('Friend');
  await page.getByLabel('Email').fill('sam@example.com');
  await page.getByRole('button', { name: 'Continue' }).click();

  await fillVictim(page);
  await fillOffender(page);
  await answerAll(page);
  await expect(page.getByText('Sam Friend')).toBeVisible();
  await page.getByRole('button', { name: 'Submit assessment' }).click();
  await expect(page.getByRole('heading', { name: 'Assessment submitted' })).toBeVisible();

  expect(api.submissions[0]).toMatchObject({
    ForWhom: 'other',
    Anonymous: false,
    SubmitterFirstName: 'Sam',
    SubmitterLastName: 'Friend',
    SubmitterEmail: 'sam@example.com',
    SubmitterPhoneNumber: null,
  });
});

test('fields are not remembered by the browser, and the details are cleared after submitting', async ({ page }) => {
  await stubAssessmentApi(page);
  await page.goto('/assessment/');
  await acceptNotice(page);
  await page.getByRole('button', { name: 'Myself' }).click();
  await page.getByRole('button', { name: 'Continue' }).click();

  for (const input of await page.locator('input').all()) {
    await expect(input).toHaveAttribute('autocomplete', 'off');
  }

  await fillVictim(page);
  await fillOffender(page);
  await answerAll(page);
  await page.getByRole('button', { name: 'Submit assessment' }).click();
  await page.getByRole('button', { name: 'Start new assessment' }).click();

  await page.getByRole('button', { name: 'Myself' }).click();
  await page.getByRole('button', { name: 'Continue' }).click();
  await expect(page.getByLabel('First name')).toHaveValue('');
  await expect(page.getByLabel(/phone number/i)).toHaveValue('');
});

test('a throttled submission says so and keeps the answers for another try', async ({ page }) => {
  await stubAssessmentApi(page, { status: 429 });
  await page.goto('/assessment/');
  await acceptNotice(page);
  await page.getByRole('button', { name: 'Myself' }).click();
  await page.getByRole('button', { name: 'Continue' }).click();
  await fillVictim(page);
  await fillOffender(page);
  await answerAll(page);

  await page.getByRole('button', { name: 'Submit assessment' }).click();
  // filter: Next.js renders its own (empty) role="alert" route announcer too
  await expect(page.getByRole('alert').filter({ hasText: /wait a minute/i })).toBeVisible();
  await expect(page.getByText('Jane Doe')).toBeVisible();
});

test('the Safe Exit button stays clickable above the confidentiality notice', async ({ page }) => {
  await stubAssessmentApi(page);
  await page.goto('/assessment/');
  await expect(page.getByRole('dialog', { name: 'Confidentiality notice' })).toBeVisible();

  // globals.css raises #exit-button above any open Modal (body.modal-open)
  await expect(page.locator('body')).toHaveClass(/modal-open/);
  await expect(page.locator('#exit-button')).toBeVisible();
  await page.locator('#exit-button').click({ trial: true });
});
