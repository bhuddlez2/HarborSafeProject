import { expect, test } from '@playwright/test';
import { SAMPLE_NEWSLETTER, stubContentApi } from './support/content-api';

/*
The newsletter preview (NewsletterPreview.js): an issue read one page at a time,
turned with the arrow buttons, the arrow keys, a tap on either half of the page
or a swipe, never scrolled, and closed only by its X in the top left.
*/

const SAFETY_NOTICE_KEY = 'hshac-safety-notice-dismissed';

// Two blank pages; pdf.js rebuilds the missing cross-reference table itself.
const TWO_PAGE_PDF = [
  '%PDF-1.4',
  '1 0 obj <</Type /Catalog /Pages 2 0 R>> endobj',
  '2 0 obj <</Type /Pages /Kids [3 0 R 4 0 R] /Count 2>> endobj',
  '3 0 obj <</Type /Page /Parent 2 0 R /MediaBox [0 0 200 260]>> endobj',
  '4 0 obj <</Type /Page /Parent 2 0 R /MediaBox [0 0 200 260]>> endobj',
  'trailer <</Root 1 0 R>>',
  '%%EOF',
].join('\n');

test.beforeEach(async ({ page }) => {
  await page.addInitScript((key) => window.sessionStorage.setItem(key, '1'), SAFETY_NOTICE_KEY);
  await stubContentApi(page);
});

async function servePdf(page, { status = 200 } = {}) {
  await page.route(SAMPLE_NEWSLETTER.file.url, (route) =>
    route.fulfill({
      status,
      contentType: 'application/pdf',
      // the file comes from the API, a different origin from the website
      headers: { 'Access-Control-Allow-Origin': '*' },
      body: status === 200 ? TWO_PAGE_PDF : '',
    }),
  );
}

async function openPreview(page) {
  await page.goto('/events');
  await page.getByRole('button', { name: `Preview ${SAMPLE_NEWSLETTER.title}` }).click();
  return page.getByRole('dialog', { name: SAMPLE_NEWSLETTER.title });
}

test('pages turn with the buttons, the arrow keys, a tap and a swipe', async ({ page }) => {
  await servePdf(page);
  const dialog = await openPreview(page);
  const counter = dialog.getByText(/^Page \d of 2$/);

  await expect(counter).toHaveText('Page 1 of 2');
  await expect(dialog.getByRole('button', { name: 'Previous' })).toBeDisabled();

  await dialog.getByRole('button', { name: 'Next' }).click();
  await expect(counter).toHaveText('Page 2 of 2');
  await expect(dialog.getByRole('button', { name: 'Next' })).toBeDisabled();

  await page.keyboard.press('ArrowLeft');
  await expect(counter).toHaveText('Page 1 of 2');

  // a tap on the right half turns forward
  const pageImage = dialog.getByRole('img', { name: 'Page 1 of 2' });
  const box = await pageImage.boundingBox();
  await page.mouse.click(box.x + box.width * 0.9, box.y + box.height / 2);
  await expect(counter).toHaveText('Page 2 of 2');

  // a swipe to the right turns back
  const y = box.y + box.height / 2;
  await page.mouse.move(box.x + box.width * 0.2, y);
  await page.mouse.down();
  await page.mouse.move(box.x + box.width * 0.8, y, { steps: 5 });
  await page.mouse.up();
  await expect(counter).toHaveText('Page 1 of 2');

  // the page fits the pop-up instead of scrolling
  const stageScrolls = await pageImage.evaluate((node) => {
    const stage = node.parentElement;
    return stage.scrollHeight > stage.clientHeight + 1;
  });
  expect(stageScrolls).toBe(false);
});

test('on a phone, zoom enlarges the page and a drag moves it instead of turning it', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await servePdf(page);
  const dialog = await openPreview(page);
  const counter = dialog.getByText(/^Page \d of 2$/);
  await expect(counter).toHaveText('Page 1 of 2');

  const pageImage = dialog.getByRole('img', { name: 'Page 1 of 2' });
  const fitted = await pageImage.boundingBox();

  await dialog.getByRole('button', { name: 'Zoom in' }).click();
  await dialog.getByRole('button', { name: 'Zoom in' }).click();
  await expect(dialog.getByText('200%')).toBeVisible();
  const zoomed = await pageImage.boundingBox();
  expect(Math.abs(zoomed.width - fitted.width * 2)).toBeLessThan(2);

  const area = await pageImage.locator('..').boundingBox();
  const middle = { x: area.x + area.width / 2, y: area.y + area.height / 2 };

  // a drag to the left moves the page, and doesn't turn it
  await page.mouse.move(middle.x, middle.y);
  await page.mouse.down();
  await page.mouse.move(middle.x - 100, middle.y - 50, { steps: 5 });
  await page.mouse.up();
  const moved = await pageImage.boundingBox();
  expect(moved.x).toBeLessThan(zoomed.x);
  expect(moved.y).toBeLessThan(zoomed.y);
  await expect(counter).toHaveText('Page 1 of 2');

  // nor does a tap while zoomed
  await page.mouse.click(middle.x + 80, middle.y);
  await expect(counter).toHaveText('Page 1 of 2');

  // Next still turns, keeping the zoom
  await dialog.getByRole('button', { name: 'Next' }).click();
  await expect(counter).toHaveText('Page 2 of 2');
  await expect(dialog.getByText('200%')).toBeVisible();

  await dialog.getByRole('button', { name: 'Zoom out' }).click();
  await dialog.getByRole('button', { name: 'Zoom out' }).click();
  await expect(dialog.getByText('100%')).toBeVisible();
  await expect(dialog.getByRole('button', { name: 'Zoom out' })).toBeDisabled();
});

test('a desktop screen has no zoom, since the whole page fits readably', async ({ page }) => {
  await servePdf(page);
  const dialog = await openPreview(page);
  await expect(dialog.getByText('Page 1 of 2')).toBeVisible();
  await expect(dialog.getByRole('button', { name: 'Zoom in' })).toBeHidden();
});

test('only the X in the top left closes the preview', async ({ page }) => {
  await servePdf(page);
  const dialog = await openPreview(page);
  await expect(dialog.getByText('Page 1 of 2')).toBeVisible();

  // a click on the dimmed backdrop does nothing
  await page.mouse.click(5, 5);
  await expect(dialog).toBeVisible();

  const close = dialog.getByRole('button', { name: 'Close' });
  const title = dialog.getByRole('heading', { name: SAMPLE_NEWSLETTER.title });
  expect((await close.boundingBox()).x).toBeLessThan((await title.boundingBox()).x);

  await close.click();
  await expect(dialog).toHaveCount(0);
});

test('a preview that fails to load still offers the download', async ({ page }) => {
  await servePdf(page, { status: 404 });
  const dialog = await openPreview(page);

  await expect(dialog.getByText("This preview couldn't load.", { exact: false })).toBeVisible();
  await expect(dialog.getByRole('link', { name: /Download PDF/ })).toHaveAttribute('href', SAMPLE_NEWSLETTER.file.url);
});
