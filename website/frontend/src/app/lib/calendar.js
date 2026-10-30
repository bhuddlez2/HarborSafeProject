/*
calendar.js: the Events page's Add to Calendar menu (B22)

Google Calendar and Office 365 get a link that opens their own new-event screen
already filled in. Everything else (Apple Calendar, and personal Outlook.com, whose
prefill link opened an empty calendar when tried) gets an iCalendar (.ics) file
built in the browser and downloaded, since the site is a static export with no
server to serve one.

Recurrence is free text typed by staff ("Every Tuesday"), which a calendar app
can't read as a rule, so the entry is the next date only and the text goes in
its notes.
*/

// Events with no end time get an hour, so the entry has a visible length
const DEFAULT_LENGTH_MS = 60 * 60 * 1000;

// iCalendar text escaping: backslash first, then the separators, then newlines
function escapeText(text) {
  return String(text)
    .replace(/\\/g, "\\\\")
    .replace(/;/g, "\\;")
    .replace(/,/g, "\\,")
    .replace(/\r?\n/g, "\\n");
}

// Lines over 75 characters are folded onto continuation lines starting with a space
function fold(line) {
  const parts = [];
  for (let i = 0; i < line.length; i += 74) parts.push(line.slice(i, i + 74));
  return parts.join("\r\n ");
}

// 2026-11-05T18:00:00.000Z -> 20261105T180000Z
function utcStamp(date) {
  return date.toISOString().replace(/[-:]/g, "").replace(/\.\d{3}/, "");
}

/*
All-day dates are read from the ISO string's own date part, which the API sends
in Eastern, so the day never shifts with the visitor's time zone. The end is the
day after the last day, as every calendar's all-day end is exclusive.
Returns YYYY-MM-DD strings.
*/
function allDayDates(event) {
  const start = event.startsAt.slice(0, 10);
  const last = (event.endsAt ?? event.startsAt).slice(0, 10);
  const end = new Date(`${last}T00:00:00Z`);
  end.setUTCDate(end.getUTCDate() + 1);
  return [start, end.toISOString().slice(0, 10)];
}

// Timed start and end as Dates
function timedDates(event) {
  const start = new Date(event.startsAt);
  const end = event.endsAt ? new Date(event.endsAt) : new Date(start.getTime() + DEFAULT_LENGTH_MS);
  return [start, end];
}

const compactDate = (isoDate) => isoDate.replace(/-/g, "");

// Spaces as %20 rather than URLSearchParams' "+", which some calendars show literally (a real "+" is already %2B)
const query = (params) => params.toString().replaceAll("+", "%20");

function locationText(location) {
  if (!location) return null;
  if (location.isVirtual) {
    return location.virtualNote ? `${location.name} (${location.virtualNote})` : location.name;
  }
  return location.address ? `${location.name}, ${location.address}` : location.name;
}

function notesText(event, pageUrl) {
  const intro = event.summary || (event.description ?? []).join("\n\n");
  return [
    intro,
    event.recurrence && `Repeats: ${event.recurrence}`,
    event.registration?.url && `${event.registration.label ?? "Register"}: ${event.registration.url}`,
    `Details: ${pageUrl}`,
  ]
    .filter(Boolean)
    .join("\n\n");
}

// Google Calendar's prefilled new-event link
export function googleCalendarUrl(event, { pageUrl }) {
  const dates = event.allDay
    ? allDayDates(event).map(compactDate)
    : timedDates(event).map(utcStamp);
  const params = new URLSearchParams({
    action: "TEMPLATE",
    text: event.title,
    dates: dates.join("/"),
    details: notesText(event, pageUrl),
  });
  const location = locationText(event.location);
  if (location) params.set("location", location);
  return `https://calendar.google.com/calendar/render?${query(params)}`;
}

/*
Office 365's prefilled new-event link, for work and school Microsoft accounts.
The same link on outlook.live.com (personal accounts) opened an empty calendar
when tried, so personal Outlook users get the .ics file instead.
*/
export function office365CalendarUrl(event, { pageUrl }) {
  const [start, end] = event.allDay
    ? allDayDates(event)
    : timedDates(event).map((date) => date.toISOString());
  const params = new URLSearchParams({
    path: "/calendar/action/compose",
    rru: "addevent",
    subject: event.title,
    startdt: start,
    enddt: end,
    allday: String(Boolean(event.allDay)),
    body: notesText(event, pageUrl),
  });
  const location = locationText(event.location);
  if (location) params.set("location", location);
  return `https://outlook.office.com/calendar/0/deeplink/compose?${query(params)}`;
}

export function buildIcs(event, { pageUrl, host, now = new Date() }) {
  let when;
  if (event.allDay) {
    const [start, end] = allDayDates(event).map(compactDate);
    when = [`DTSTART;VALUE=DATE:${start}`, `DTEND;VALUE=DATE:${end}`];
  } else {
    const [start, end] = timedDates(event);
    when = [`DTSTART:${utcStamp(start)}`, `DTEND:${utcStamp(end)}`];
  }

  const location = locationText(event.location);

  const lines = [
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    "PRODID:-//Harbor Safe House//Events//EN",
    "CALSCALE:GREGORIAN",
    "METHOD:PUBLISH",
    "BEGIN:VEVENT",
    // a stable UID means adding the same event twice updates it rather than duplicating it
    `UID:event-${event.id}@${host}`,
    `DTSTAMP:${utcStamp(now)}`,
    ...when,
    `SUMMARY:${escapeText(event.title)}`,
    location && `LOCATION:${escapeText(location)}`,
    `DESCRIPTION:${escapeText(notesText(event, pageUrl))}`,
    `URL:${pageUrl}`,
    "END:VEVENT",
    "END:VCALENDAR",
  ];

  // iCalendar requires CRLF line endings
  return lines.filter(Boolean).map(fold).join("\r\n") + "\r\n";
}

// "Fall Fundraiser Gala!" -> "fall-fundraiser-gala.ics"
function fileName(title) {
  const slug = title.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
  return `${slug || "event"}.ics`;
}

export function downloadIcs(event) {
  const ics = buildIcs(event, {
    pageUrl: `${window.location.origin}/events/`,
    host: window.location.hostname,
  });
  const url = URL.createObjectURL(new Blob([ics], { type: "text/calendar;charset=utf-8" }));
  const link = document.createElement("a");
  link.href = url;
  link.download = fileName(event.title);
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}
