/*
content.js: the data source for the Events & News page.

Every component on the page reads its data from getEvents() and getNewsletters()
below.
*/

export const TIME_ZONE = "America/New_York";

// ── Loading the data ──────────────────────────────────────────────────────────

/*
A promise that never settles, for ?preview=hang.

The page's real failure mode is not a load that rejects - that was always
handled - but one that never comes back at all: a chunk request that hangs, a
blocked script, a stalled connection. The symptom is the page sitting on
"Loading" forever, and since nothing but that word is in the prerendered HTML,
that is the entire page.

This exists so EventsContent's load timeout is actually testable, in the same
spirit as the "error" and "empty" hooks beside it.
*/
const NEVER = new Promise(() => {});

/*
getEvents returns every published event, sorted soonest-first, each with a
computed isPast flag.
*/
export async function getEvents({ simulate } = {}) {
  if (simulate === "error") throw new Error("Simulated content failure");
  if (simulate === "empty") return [];
  if (simulate === "hang") return NEVER;

  const { default: events } = await import("./mock-events.json");

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
  if (simulate === "error") throw new Error("Simulated content failure");
  if (simulate === "empty") return [];
  if (simulate === "hang") return NEVER;

  const { default: newsletters } = await import("./mock-newsletters.json");

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
