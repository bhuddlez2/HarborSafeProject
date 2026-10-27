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
  const notice = page.getByRole('dialog', { name: 'Confidentiality Notice' });
  await expect(notice).toBeVisible();
  await notice.getByRole('button', { name: 'I understand, continue' }).click();
  await expect(notice).toBeHidden();
}

async function fillVictim(page) {
  await page.getByLabel('First Name').fill('Jane');
  await page.getByLabel('Last Name').fill('Doe');
  await page.getByLabel('Date of Birth').fill('1990-04-12');
  await page.getByLabel('Sex').selectOption('F');
  await page.getByLabel(/phone number/i).fill('(423) 555-0100');
  await page.getByRole('button', { name: 'Continue' }).click();
}

async function fillOffender(page) {
  await page.getByLabel('First Name').fill('John');
  await page.getByLabel('Last Name').fill('Roe');
  await page.getByLabel('Sex').selectOption('M');
  await page.getByLabel('Relationship to Victim').selectOption('spouse');
  await page.getByRole('button', { name: 'Continue' }).click();
}

async function answerAll(page, pattern = (n) => n % 2 === 0) {
  await page.getByRole('button', { name: 'Begin assessment' }).click();
  for (let n = 1; n <= 11; n++) {
    // The answer buttons exist twice (desktop card, mobile bar); the desktop ones are visible here.
    await page.getByRole('button', { name: pattern(n) ? 'Yes' : 'No', exact: true }).first().click();
  }
  await expect(page.getByRole('heading', { name: 'Review & Submit' })).toBeVisible();
}

// The review's safe-phone box: Submit stays disabled until it is ticked.
async function confirmSafePhone(page) {
  await page.getByLabel(/is a safe phone number to contact/).check();
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

  await confirmSafePhone(page);
  await page.getByRole('button', { name: 'Submit assessment' }).click();
  await expect(page.getByRole('heading', { name: 'Assessment Submitted' })).toBeVisible();

  expect(api.submissions).toHaveLength(1);
  const sent = api.submissions[0];
  expect(sent).toMatchObject({
    ForWhom: 'self',
    VictimFirstName: 'Jane',
    VictimSafePhoneNumber: '(423)555-0100',
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
  await confirmSafePhone(page);
  await page.getByRole('button', { name: 'Submit assessment' }).click();
  await expect(page.getByRole('heading', { name: 'Assessment Submitted' })).toBeVisible();

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
  await page.getByLabel('First Name').fill('Sam');
  await page.getByLabel('Last Name').fill('Friend');
  await page.getByLabel('Email').fill('sam@example.com');
  await page.getByRole('button', { name: 'Continue' }).click();

  await fillVictim(page);
  await fillOffender(page);
  await answerAll(page);
  await expect(page.getByText('Sam Friend')).toBeVisible();
  await confirmSafePhone(page);
  await page.getByRole('button', { name: 'Submit assessment' }).click();
  await expect(page.getByRole('heading', { name: 'Assessment Submitted' })).toBeVisible();

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
  await confirmSafePhone(page);
  await page.getByRole('button', { name: 'Submit assessment' }).click();
  await page.getByRole('button', { name: 'Start new assessment' }).click();

  await page.getByRole('button', { name: 'Myself' }).click();
  await page.getByRole('button', { name: 'Continue' }).click();
  await expect(page.getByLabel('First Name')).toHaveValue('');
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

  await confirmSafePhone(page);
  await page.getByRole('button', { name: 'Submit assessment' }).click();
  // filter: Next.js renders its own (empty) role="alert" route announcer too
  await expect(page.getByRole('alert').filter({ hasText: /wait a minute/i })).toBeVisible();
  await expect(page.getByText('Jane Doe')).toBeVisible();
});

test('the Safe Exit button stays clickable above the confidentiality notice', async ({ page }) => {
  await stubAssessmentApi(page);
  await page.goto('/assessment/');
  await expect(page.getByRole('dialog', { name: 'Confidentiality Notice' })).toBeVisible();

  // globals.css raises #exit-button above any open Modal (body.modal-open)
  await expect(page.locator('body')).toHaveClass(/modal-open/);
  await expect(page.locator('#exit-button')).toBeVisible();
  await page.locator('#exit-button').click({ trial: true });
});

test('the confidentiality notice can only be closed with its own button', async ({ page }) => {
  await stubAssessmentApi(page);
  await page.goto('/assessment/');
  const notice = page.getByRole('dialog', { name: 'Confidentiality Notice' });
  await expect(notice).toBeVisible();

  // No X, and a click on the dimmed page doesn't close it. (Escape isn't
  // tried: on this site it is the Safe Exit shortcut and leaves the page.)
  await expect(notice.getByRole('button', { name: 'Close' })).toHaveCount(0);
  await page.mouse.click(5, 5);
  await expect(notice).toBeVisible();

  await acceptNotice(page);
});

test('the safe phone number formats as it is typed and needs all 10 digits', async ({ page }) => {
  await stubAssessmentApi(page);
  await page.goto('/assessment/');
  await acceptNotice(page);
  await page.getByRole('button', { name: 'Myself' }).click();
  await page.getByRole('button', { name: 'Continue' }).click();

  // Labelled as the safe number even when reporting for yourself.
  const phone = page.getByLabel('Safe Phone Number');
  await page.getByLabel('First Name').fill('Jane');
  await page.getByLabel('Last Name').fill('Doe');
  await page.getByLabel('Date of Birth').fill('1990-04-12');
  await page.getByLabel('Sex').selectOption('F');

  // Seven digits: formatted, but refused - no area code.
  await phone.pressSequentially('5550100');
  await expect(phone).toHaveValue('(555)010-0');
  await phone.fill('');
  await phone.pressSequentially('4235550');
  await expect(phone).toHaveValue('(423)555-0');
  await expect(page.getByText('Enter a 10-digit phone number, starting with the area code.')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Continue' })).toBeDisabled();

  // All ten: accepted. A leading country code 1 is dropped as it's typed,
  // and a pasted number keeps every digit, however it was punctuated.
  await phone.fill('');
  await phone.pressSequentially('14235550100');
  await expect(phone).toHaveValue('(423)555-0100');
  await phone.fill('+1 (423) 555-0100');
  await expect(phone).toHaveValue('(423)555-0100');
  await expect(page.getByText('Enter a 10-digit phone number, starting with the area code.')).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Continue' })).toBeEnabled();
});

// The backend's App\Support\DateOfBirth rule: today back to 125 years ago,
// "today" being the Eastern day.
test('a date of birth must be within the last 125 years', async ({ page }) => {
  // 11 am Eastern on 26 October 2026.
  await page.clock.setFixedTime(new Date('2026-10-26T15:00:00Z'));
  await stubAssessmentApi(page);
  await page.goto('/assessment/');
  await acceptNotice(page);
  await page.getByRole('button', { name: 'Myself' }).click();
  await page.getByRole('button', { name: 'Continue' }).click();

  await page.getByLabel('First Name').fill('Jane');
  await page.getByLabel('Last Name').fill('Doe');
  await page.getByLabel('Sex').selectOption('F');
  await page.getByLabel('Safe Phone Number').fill('(423) 555-0100');

  // The calendar only offers the range.
  const dob = page.getByLabel('Date of Birth');
  await expect(dob).toHaveAttribute('min', '1901-10-26');
  await expect(dob).toHaveAttribute('max', '2026-10-26');

  const continueButton = page.getByRole('button', { name: 'Continue' });

  await dob.fill('1901-10-25');
  await expect(page.getByText("Date of birth can't be more than 125 years ago.")).toBeVisible();
  await expect(continueButton).toBeDisabled();

  await dob.fill('2026-10-27');
  await expect(page.getByText("Date of birth can't be in the future.")).toBeVisible();
  await expect(continueButton).toBeDisabled();

  // Both ends are allowed.
  await dob.fill('1901-10-26');
  await expect(continueButton).toBeEnabled();
  await dob.fill('2026-10-26');
  await expect(continueButton).toBeEnabled();
});

/*
The review before Submit (team plan task 31): every detail and answer, Edit and Change
buttons that come straight back to the review, and the safe-phone box - the officer
wizard's check, which is never sent.
*/

async function reachReview(page) {
  await page.goto('/assessment/');
  await acceptNotice(page);
  await page.getByRole('button', { name: 'Myself' }).click();
  await page.getByRole('button', { name: 'Continue' }).click();
  await fillVictim(page);
  await fillOffender(page);
  await answerAll(page);
}

test('the review shows every detail and every answer', async ({ page }) => {
  await stubAssessmentApi(page);
  await reachReview(page);

  for (const text of ['Myself', 'Jane Doe', 'Female', 'Apr 12, 1990', '(423)555-0100', 'John Roe', 'Male', 'Not given', 'Spouse']) {
    await expect(page.getByText(text, { exact: true }).first()).toBeVisible();
  }

  const answers = page.getByRole('listitem');
  await expect(answers).toHaveCount(11);
  await expect(answers.nth(0)).toContainText('NO');
  await expect(answers.nth(1)).toContainText('YES');
  await expect(page.getByRole('button', { name: /^Change answer to question/ })).toHaveCount(11);
});

test('Submit stays disabled until the safe number is confirmed, and the box is never sent', async ({ page }) => {
  const api = await stubAssessmentApi(page);
  await reachReview(page);

  const box = page.getByLabel('I confirm (423)555-0100 is a safe phone number to contact');
  const submit = page.getByRole('button', { name: 'Submit assessment' });
  await expect(box).not.toBeChecked();
  await expect(submit).toBeDisabled();

  await box.check();
  await expect(submit).toBeEnabled();
  await submit.click();
  await expect(page.getByRole('heading', { name: 'Assessment Submitted' })).toBeVisible();

  expect(Object.keys(api.submissions[0]).filter((k) => /confirm|safephoneconfirmed/i.test(k))).toEqual([]);
});

test('editing the victim returns to the review, and a new number has to be confirmed again', async ({ page }) => {
  const api = await stubAssessmentApi(page);
  await reachReview(page);
  await confirmSafePhone(page);

  await page.getByRole('button', { name: "Edit the victim's details" }).click();
  await page.getByLabel('Safe Phone Number').fill('(865) 555-0199');
  await page.getByRole('button', { name: 'Return to review' }).click();

  // Straight back: not through the offender step or the questions again.
  await expect(page.getByRole('heading', { name: 'Review & Submit' })).toBeVisible();
  await expect(page.getByLabel(/\(865\)555-0199 is a safe phone number/)).not.toBeChecked();
  await expect(page.getByRole('button', { name: 'Submit assessment' })).toBeDisabled();

  await confirmSafePhone(page);
  await page.getByRole('button', { name: 'Submit assessment' }).click();
  await expect(page.getByRole('heading', { name: 'Assessment Submitted' })).toBeVisible();
  expect(api.submissions[0].VictimSafePhoneNumber).toBe('(865)555-0199');
});

test('changing one answer from the review comes straight back to it', async ({ page }) => {
  const api = await stubAssessmentApi(page);
  await reachReview(page);

  await page.getByRole('button', { name: 'Change answer to question 1', exact: true }).click();
  await expect(page.getByText('Question 1 of 11')).toBeVisible();
  await page.getByRole('button', { name: 'Yes', exact: true }).first().click();

  await expect(page.getByRole('heading', { name: 'Review & Submit' })).toBeVisible();
  await expect(page.getByRole('listitem').nth(0)).toContainText('YES');

  await confirmSafePhone(page);
  await page.getByRole('button', { name: 'Submit assessment' }).click();
  await expect(page.getByRole('heading', { name: 'Assessment Submitted' })).toBeVisible();
  expect(api.submissions[0]).toMatchObject({ RiskIndicator1: true, RiskIndicator2: true, RiskIndicator3: false });
});

test('switching to a named report from the review asks for the name first', async ({ page }) => {
  const api = await stubAssessmentApi(page);
  await reachReview(page);

  await page.getByRole('button', { name: 'Edit who this report is for' }).click();
  await page.getByRole('button', { name: 'Someone else' }).click();
  await page.getByRole('button', { name: 'No', exact: true }).click();
  await page.getByRole('button', { name: 'Continue' }).click();

  await expect(page.getByRole('heading', { name: 'Your information' })).toBeVisible();
  await page.getByLabel('First Name').fill('Sam');
  await page.getByLabel('Last Name').fill('Friend');
  await page.getByRole('button', { name: 'Return to review' }).click();

  await expect(page.getByRole('heading', { name: 'Review & Submit' })).toBeVisible();
  await expect(page.getByText('Sam Friend')).toBeVisible();

  await confirmSafePhone(page);
  await page.getByRole('button', { name: 'Submit assessment' }).click();
  await expect(page.getByRole('heading', { name: 'Assessment Submitted' })).toBeVisible();
  expect(api.submissions[0]).toMatchObject({ ForWhom: 'other', Anonymous: false, SubmitterFirstName: 'Sam' });
});
