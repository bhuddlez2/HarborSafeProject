/*
Stubs the two public content endpoints for Playwright.

The website fetches events and newsletters from the Laravel API in the
visitor's browser now, and playwright.config.js only boots the website - not
the backend. So a spec that expects content on the page has to supply it, or it
would be testing whether a developer happened to have `php artisan serve`
running.

Stubbing also pins the response shape in the test rather than depending on
whatever happens to be in a developer's database. The shape itself is asserted
against the backend in portal/backend/tests/Feature/Public/ContentEndpointsTest.php.
*/

export const SAMPLE_EVENT = {
  id: 'evt-test-1',
  title: 'Moonlight Walk',
  summary: 'An evening walk.',
  description: ['Join us for the walk.'],
  // Far enough out that isPast stays false without anyone maintaining it.
  startsAt: '2030-10-22T18:00:00-04:00',
  endsAt: '2030-10-22T20:00:00-04:00',
  allDay: false,
  recurrence: null,
  location: { name: 'Johnston Park', address: 'Cleveland, TN', isVirtual: false },
  image: null,
  registration: { url: 'https://example.com/register', label: 'Reserve a seat' },
  category: 'Fundraiser',
  isPublished: true,
  isCancelled: false,
};

export const SAMPLE_NEWSLETTER = {
  id: 'nws-test-1',
  title: 'Autumn Issue',
  issueDate: '2026-09-01',
  summary: null,
  file: { url: 'https://example.com/issue.pdf', sizeBytes: 2411724, pages: 4 },
  isPublished: true,
};

/*
Serves the given lists from both endpoints. Pass [] for either to exercise the
empty state, which is what the site looks like before staff publish anything.
*/
export async function stubContentApi(page, { events = [SAMPLE_EVENT], newsletters = [SAMPLE_NEWSLETTER] } = {}) {
  await page.route('**/api/public/events', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      // Laravel API Resources wrap a collection in { data: [...] }.
      body: JSON.stringify({ data: events }),
    }),
  );

  await page.route('**/api/public/newsletters', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: newsletters }),
    }),
  );
}

// Both endpoints fail, for the error-state paths.
export async function failContentApi(page) {
  await page.route('**/api/public/{events,newsletters}', (route) =>
    route.fulfill({ status: 500, contentType: 'application/json', body: '{}' }),
  );
}
