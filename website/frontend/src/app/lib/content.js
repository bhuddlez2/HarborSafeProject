/*
content.js: the data source for the Events & News page.

Every component on the page reads its data from getEvents() and getNewsletters()
below. Those two signatures are the seam: they used to import mock JSON, they
now call the API, and nothing downstream changed.

WHERE THE DATA COMES FROM. Staff publish events and newsletters in the Filament
panel (portal/backend), and these two endpoints serve the published ones:

  GET {API}/api/public/events
  GET {API}/api/public/newsletters

The fetch happens in the visitor's browser, not at build time. That is
deliberate: this site is a static export, so baking content in would mean a
rebuild and redeploy every time staff added an event, which is the thing the
panel exists to avoid. The cost is that the page needs the API reachable at
view time, which is why the loading and error states in EventsContent.js matter
and why that component has a hard timeout.

The response shape is set by PublicEventResource / PublicNewsletterResource on
the backend. It is the same shape the old mock-events.json and
mock-newsletters.json carried - those files were the original contract and were
deleted once these endpoints matched them. The backend has tests asserting the
key structure; if you need to change a field name, change it there and here
together.
*/

export const TIME_ZONE = "America/New_York";

// Same pattern as forms.js, which calls the public feedback endpoints. Inlined
// at build time by Next, so it has to be set before `npm run build`.
const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://127.0.0.1:8000";

// ── Loading the data ──────────────────────────────────────────────────────────

/*
A promise that never settles, for ?preview=hang.

The page's worst failure mode is not a request that fails - that shows the error
state - but one that never comes back at all, which left it on "Loading…"
forever. This exists so EventsContent's timeout is testable, alongside the
"error" and "empty" hooks.
*/
const NEVER = new Promise(() => {});

async function fetchContent(path, { simulate } = {}) {
  if (simulate === "error") throw new Error("Simulated content failure");
  if (simulate === "empty") return [];
  if (simulate === "hang") return NEVER;

  const response = await fetch(`${API_URL}/api/public/${path}`, {
    headers: { Accept: "application/json" },
    // These are public, unauthenticated reads; sending cookies would trip
    // CORS, which is configured with supports_credentials off.
    credentials: "omit",
  });

  if (!response.ok) {
    throw new Error(`${path}: ${response.status} ${response.statusText}`);
  }

  const body = await response.json();

  // Laravel API Resources wrap a collection in { data: [...] }. Tolerate a
  // bare array too, so a change to the backend's wrapping does not blank the
  // page silently.
  const records = Array.isArray(body) ? body : body?.data;

  if (!Array.isArray(records)) {
    throw new Error(`${path}: expected a list, got ${typeof records}`);
  }

  return records;
}

/*
getEvents returns every published event, sorted soonest-first, each with a
computed isPast flag.

The endpoint already filters and sorts; both are repeated here so the page is
correct even if that ever changes, and because isPast has to be computed
client-side regardless - it depends on when the visitor is looking.
*/
export async function getEvents({ simulate } = {}) {
  const events = await fetchContent("events", { simulate });

  const now = Date.now();

  return events
    .filter((event) => event.isPublished)
    .map((event) => {
      const ends = new Date(event.endsAt ?? event.startsAt);
      return { ...event, isPast: ends.getTime() < now };
    })
    .sort((a, b) => new Date(a.startsAt) - new Date(b.startsAt));
}

export async function getNewsletters({ simulate } = {}) {
  const newsletters = await fetchContent("newsletters", { simulate });

  return newsletters
    .filter((issue) => issue.isPublished)
    .sort((a, b) => new Date(b.issueDate) - new Date(a.issueDate));
}

// ── Formatting helpers ────────────────────────────────────────────────────────

export function formatEventDate(isoString) {
  return new Intl.DateTimeFormat("en-US", {
    timeZone: TIME_ZONE,
    weekday: "long",
    month: "long",
    day: "numeric",
    year: "numeric",
  }).format(new Date(isoString));
}

export function formatEventDateShort(isoString) {
  return new Intl.DateTimeFormat("en-US", {
    timeZone: TIME_ZONE,
    month: "short",
    day: "numeric",
  }).format(new Date(isoString));
}

export function formatEventTimeRange(event) {
  if (event.allDay) return "All day";

  const time = (iso) =>
    new Intl.DateTimeFormat("en-US", {
      timeZone: TIME_ZONE,
      hour: "numeric",
      minute: "2-digit",
    }).format(new Date(iso));

  if (!event.endsAt) return time(event.startsAt);
  return `${time(event.startsAt)} – ${time(event.endsAt)}`;
}

export function formatIssueDate(isoString) {
  return new Intl.DateTimeFormat("en-US", {
    // UTC on purpose: issueDate is a calendar date, not a moment, so rendering
    // it in Eastern would push the first of a month back into the previous one.
    timeZone: "UTC",
    month: "long",
    year: "numeric",
  }).format(new Date(isoString));
}

export function formatFileSize(bytes) {
  if (!bytes) return null;
  const mb = bytes / 1_000_000;
  if (mb >= 1) return `${mb.toFixed(1)} MB`;
  return `${Math.round(bytes / 1000)} KB`;
}
