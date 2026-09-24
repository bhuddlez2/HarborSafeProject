"use client";

import { useState, useEffect } from "react";
import Image from "next/image";
import Modal from "../components/Modal";
import {
  getEvents,
  getNewsletters,
  formatEventDate,
  formatEventDateShort,
  formatEventTimeRange,
  formatIssueDate,
  formatFileSize,
} from "../lib/content";

/*
EventsContent: the Events & News page

The content is loaded through lib/content.js rather than being written into this
file, so switching to a real CMS does not touch anything here.

Everything is on one route with tabs instead of /events/[slug] detail pages.
This is forced by next.config.mjs; the site is a static export, so
generateStaticParams runs at build time and could never produce a route for an
event the client adds afterwards. Details open in a dialog instead.
*/

// ── Small shared pieces ───────────────────────────────────────────────────────

// Icons from feathericons.com (MIT)
const ICONS = {
  calendar: (
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <rect x="3" y="4" width="18" height="18" rx="2" ry="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" />
    </svg>
  ),
  clock: (
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" />
    </svg>
  ),
  mapPin: (
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" />
    </svg>
  ),
  video: (
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <polygon points="23 7 16 12 23 17 23 7" /><rect x="1" y="5" width="15" height="14" rx="2" ry="2" />
    </svg>
  ),
  repeat: (
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <polyline points="17 1 21 5 17 9" /><path d="M3 11V9a4 4 0 0 1 4-4h14" /><polyline points="7 23 3 19 7 15" /><path d="M21 13v2a4 4 0 0 1-4 4H3" />
    </svg>
  ),
  download: (
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" />
    </svg>
  ),
  external: (
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" /><polyline points="15 3 21 3 21 9" /><line x1="10" y1="14" x2="21" y2="3" />
    </svg>
  ),
  chevronLeft: (
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <polyline points="15 18 9 12 15 6" />
    </svg>
  ),
  chevronRight: (
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <polyline points="9 18 15 12 9 6" />
    </svg>
  ),
};

// Renders the card/dialog image inside a fixed 16:9 frame
function EventImage({ image, className = "" }) {
  if (!image?.src) {
    return (
      <div className={`relative aspect-video bg-purple-100 flex items-center justify-center text-brand ${className}`}>
        <span className="opacity-40 scale-[2]" aria-hidden="true">{ICONS.calendar}</span>
      </div>
    );
  }

  return (
    <div className={`relative aspect-video bg-purple-100 overflow-hidden ${className}`}>
      {/* fill + object-cover crops to the frame instead of distorting */}
      <Image
        src={image.src}
        alt={image.alt ?? ""}
        fill
        sizes="(max-width: 768px) 100vw, 33vw"
        className="object-cover"
      />
    </div>
  );
}


// a small pill showing "Support Group", "Fundraiser" etc

function CategoryBadge({ category }) {
  if (!category) return null;
  return (
    <span className="inline-block text-xs font-semibold tracking-wide uppercase text-brand bg-purple-50 border border-purple-100 rounded-full px-3 py-1">
      {category}
    </span>
  );
}

// ── Event card ────────────────────────────────────────────────────────────────

/*
One event fills the carousel at a time, so the card runs side-by-side from md up
(image left, details right) rather than stacking.
*/
function EventCard({ event, onOpen }) {
  return (
    <article
      className="w-full h-full bg-white border border-gray-200 rounded-xl overflow-hidden
      flex flex-col md:flex-row md:min-h-[20rem]"
    >
      <div className="relative w-full aspect-video md:w-2/5 md:aspect-auto shrink-0 overflow-hidden
      bg-purple-100 flex items-center justify-center">
        {event.image?.src ? (
          <Image
            src={event.image.src}
            alt={event.image.alt ?? ""}
            fill
            sizes="(max-width: 768px) 100vw, 40vw"
            className="object-cover"
          />
        ) : (
          <span className="text-brand opacity-40 scale-[2.5]" aria-hidden="true">{ICONS.calendar}</span>
        )}
      </div>

      <div className="flex-1 p-6 md:p-8 flex flex-col gap-4">
        <div className="flex items-center gap-2 flex-wrap">
          <CategoryBadge category={event.category} />
          {event.isCancelled && (
            <span className="inline-block text-xs font-semibold tracking-wide uppercase text-red-700 bg-red-50 border border-red-200 rounded-full px-3 py-1">
              Cancelled
            </span>
          )}
        </div>

        <h3 className="text-2xl md:text-3xl font-semibold text-gray-900 leading-snug">
          {event.title}
        </h3>

        {event.summary && (
          <p className="text-gray-600 leading-relaxed">{event.summary}</p>
        )}

        <div className="pt-2 flex flex-col gap-2 text-gray-700">
          <span className="flex items-center gap-2">
            <span className="text-brand shrink-0" aria-hidden="true">{ICONS.clock}</span>
            <span>
              {formatEventDateShort(event.startsAt)}
              {!event.allDay && ` · ${formatEventTimeRange(event)}`}
            </span>
          </span>

          {event.recurrence && (
            <span className="flex items-center gap-2 text-gray-500">
              <span className="text-brand shrink-0" aria-hidden="true">{ICONS.repeat}</span>
              <span>{event.recurrence}</span>
            </span>
          )}

          {event.location && (
            <span className="flex items-center gap-2 text-gray-500">
              <span className="text-brand shrink-0" aria-hidden="true">
                {event.location.isVirtual ? ICONS.video : ICONS.mapPin}
              </span>
              <span>{event.location.name}</span>
            </span>
          )}
        </div>

        {/*
        self-start keeps the button the width of its text rather than stretching
        across the card. The accessible name names the event, so a screen reader
        user hears which event the button belongs to rather than a bare
        "View details".
        */}
        <button
          onClick={() => onOpen(event)}
          aria-label={`View details for ${event.title}`}
          className="self-start mt-2 bg-brand text-white px-5 py-2.5 rounded-lg font-semibold
          hover:bg-purple-800 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-brand"
        >
          View details
        </button>
      </div>
    </article>
  );
}

// ── Event dialog ──────────────────────────────────────────────────────────────

function EventModal({ event, onClose }) {
  return (
    <Modal title={event.title} subtitle={event.category} onClose={onClose}>
      {event.image && <EventImage image={event.image} />}

      <div className="p-6 flex flex-col gap-5">

        {event.isCancelled && (
          <p className="text-sm font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
            This event has been cancelled.
          </p>
        )}

        {/* facts block */}
        <dl className="flex flex-col gap-3 text-sm">
          <div className="flex items-start gap-3">
            <dt className="text-brand shrink-0 mt-0.5" aria-hidden="true">{ICONS.clock}</dt>
            <dd className="text-gray-700">
              <span className="block font-semibold text-gray-900">{formatEventDate(event.startsAt)}</span>
              <span>{formatEventTimeRange(event)} Eastern</span>
              {event.recurrence && <span className="block text-gray-500 mt-1">{event.recurrence}</span>}
            </dd>
          </div>

          {event.location && (
            <div className="flex items-start gap-3">
              <dt className="text-brand shrink-0 mt-0.5" aria-hidden="true">
                {event.location.isVirtual ? ICONS.video : ICONS.mapPin}
              </dt>
              <dd className="text-gray-700">
                <span className="block font-semibold text-gray-900">{event.location.name}</span>
                {event.location.address && <span className="block">{event.location.address}</span>}
                {event.location.virtualNote && (
                  <span className="block text-gray-500 mt-1">{event.location.virtualNote}</span>
                )}
              </dd>
            </div>
          )}
        </dl>

        {event.description?.length > 0 && (
          <div className="flex flex-col gap-3 text-gray-700 leading-relaxed">
            {event.description.map((paragraph, i) => (
              <p key={i}>{paragraph}</p>
            ))}
          </div>
        )}

        {event.registration && !event.isCancelled && (
          <div className="flex flex-col gap-2">
            <a
              href={event.registration.url}
              target="_blank"
              rel="noopener noreferrer"
              className="w-full bg-brand text-white text-center py-3 rounded-lg font-semibold hover:bg-purple-800 transition-all
              flex items-center justify-center gap-2"
            >
              {event.registration.label ?? "Register"}
              <span aria-hidden="true">{ICONS.external}</span>
            </a>
            <p className="text-xs text-gray-500 text-center">
              Opens another website in a new tab; this will appear in your browser history.
            </p>
          </div>
        )}
      </div>
    </Modal>
  );
}

// ── Events carousel ───────────────────────────────────────────────────────────

/*
Only the current event is rendered; the arrows and dots swap it out and a CSS
animation slides the incoming card in from the side the arrow points to.
*/
function EventsCarousel({ events, onOpen }) {
  const count = events.length;
  const [index, setIndex] = useState(0);
  /*
  The card being replaced, kept on screen only for the length of the animation
  so it can slide out while the new one slides in. null when nothing is moving.
  */
  const [outgoing, setOutgoing] = useState(null); // { index, direction }

  const goTo = (target, direction) => {
    if (target === index) return;
    setOutgoing({ index, direction });
    setIndex(target);
  };

  // modulo twice so stepping back from the first slide wraps to the last
  const step = (delta) => goTo((index + delta + count) % count, delta);
  const jumpTo = (target) => goTo(target, target > index ? 1 : -1);

  const direction = outgoing?.direction ?? 1;

  const showControls = count > 1;

  return (
    <>
      {/*
      aria-live announces the new event when the card is swapped, which is
      otherwise silent for a screen reader user pressing the arrows.
      overflow-hidden clips both cards to the card box while they slide.
      */}
      <div
        role="region"
        aria-label="Upcoming events"
        aria-live="polite"
        className="relative overflow-hidden rounded-xl"
      >
        {outgoing && (
          <div
            key={`out-${outgoing.index}`}
            className={`absolute inset-0 ${direction > 0 ? "slide-out-left" : "slide-out-right"}`}
            aria-hidden="true"
            inert
            onAnimationEnd={() => setOutgoing(null)}
          >
            <EventCard event={events[outgoing.index]} onOpen={onOpen} />
          </div>
        )}

        {/* key remounts the card so its entrance animation restarts each time */}
        <div
          key={`in-${index}`}
          className={outgoing ? (direction > 0 ? "slide-in-right" : "slide-in-left") : undefined}
        >
          <EventCard event={events[index]} onOpen={onOpen} />
        </div>
      </div>

      {showControls && (
        <div className="flex items-center justify-center gap-4 mt-8">
          <button
            onClick={() => step(-1)}
            aria-label="Previous event"
            className="p-3 rounded-full border border-purple-200 text-brand hover:bg-purple-50 transition-all"
          >
            {ICONS.chevronLeft}
          </button>

          <div className="flex items-center gap-2">
            {events.map((event, i) => (
              <button
                key={event.id}
                onClick={() => jumpTo(i)}
                aria-label={`Event ${i + 1} of ${count}`}
                aria-current={i === index}
                className={`w-2.5 h-2.5 rounded-full transition-all ${
                  i === index ? "bg-brand scale-125" : "bg-purple-200 hover:bg-purple-300"
                }`}
              />
            ))}
          </div>

          <button
            onClick={() => step(1)}
            aria-label="Next event"
            className="p-3 rounded-full border border-purple-200 text-brand hover:bg-purple-50 transition-all"
          >
            {ICONS.chevronRight}
          </button>
        </div>
      )}
    </>
  );
}

// ── Newsletters ───────────────────────────────────────────────────────────────

/*
Inline PDF embeds are poor on phones and worse with a screen reader;
rather than being put into an embedded PDF viewer, newsletters are shown as
a list of download links.
*/
function NewsletterList({ newsletters }) {
  return (
    <ul className="flex flex-col gap-4">
      {newsletters.map((issue) => {
        const size = formatFileSize(issue.file?.sizeBytes);

        return (
          <li key={issue.id} className="border border-gray-200 rounded-xl p-5 hover:border-brand transition-all">
            <div className="flex items-start justify-between gap-4 flex-wrap">
              <div className="flex-1 min-w-[16rem]">
                <p className="text-xs font-semibold tracking-widest uppercase text-brand mb-1">
                  {formatIssueDate(issue.issueDate)}
                </p>
                <h3 className="text-lg font-semibold text-gray-900 leading-snug">{issue.title}</h3>
                {issue.summary && (
                  <p className="text-sm text-gray-600 leading-relaxed mt-2">{issue.summary}</p>
                )}
              </div>

              <a
                href={issue.file?.url ?? "#"}
                target="_blank"
                rel="noopener noreferrer"
                className="shrink-0 bg-brand text-white px-5 py-3 rounded-lg font-semibold hover:bg-purple-800 transition-all
                flex items-center gap-2"
                aria-label={`Download ${issue.title}${size ? `, PDF, ${size}` : ", PDF"}`}
              >
                <span aria-hidden="true">{ICONS.download}</span>
                Download
              </a>
            </div>

            <p className="text-xs text-gray-500 mt-3">
              PDF{size && ` · ${size}`}{issue.file?.pages && ` · ${issue.file.pages} pages`}
            </p>
          </li>
        );
      })}
    </ul>
  );
}

// ── Page states ───────────────────────────────────────────────────────────────

function EmptyState({ heading, children }) {
  return (
    <div className="text-center max-w-lg mx-auto py-12">
      <span className="inline-flex text-purple-300 scale-150 mb-6" aria-hidden="true">{ICONS.calendar}</span>
      <h3 className="text-xl font-semibold text-gray-900 mb-3">{heading}</h3>
      <p className="text-gray-600 leading-relaxed">{children}</p>
    </div>
  );
}

function LoadingState() {
  return (
    <p className="text-center text-gray-500 py-12" role="status">
      Loading…
    </p>
  );
}

function ErrorState() {
  return (
    <div className="text-center max-w-lg mx-auto py-12">
      <h3 className="text-xl font-semibold text-gray-900 mb-3">We couldn&apos;t load this right now</h3>
      <p className="text-gray-600 leading-relaxed">
        Please try again in a few minutes. If you need help now, our crisis line is answered 24 hours a
        day at <a href="tel:423-476-3886" className="text-brand font-semibold hover:underline">(423) 476-3886</a>.
      </p>
    </div>
  );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function EventsContent() {
  const [events, setEvents] = useState([]);
  const [newsletters, setNewsletters] = useState([]);
  const [status, setStatus] = useState("loading"); // loading | ready | error
  const [selectedEvent, setSelectedEvent] = useState(null);

  useEffect(() => {
    const simulate = new URLSearchParams(window.location.search).get("preview");

    let cancelled = false;

    Promise.all([getEvents({ simulate }), getNewsletters({ simulate })])
      .then(([loadedEvents, loadedNewsletters]) => {
        if (cancelled) return;
        setEvents(loadedEvents);
        setNewsletters(loadedNewsletters);
        setStatus("ready");
      })
      .catch(() => {
        if (!cancelled) setStatus("error");
      });

    // guard against a state update after unmount if the visitor navigates away mid-load
    return () => { cancelled = true; };
  }, []);

  const upcoming = events.filter((event) => !event.isPast);

  const renderSections = () => {
    if (status === "loading" || status === "error") {
      return (
        <section className="py-16 px-4 bg-white">
          {status === "loading" ? <LoadingState /> : <ErrorState />}
        </section>
      );
    }

    return (
      <>
        <section className="py-16 px-4 bg-white">
          <div className="max-w-7xl mx-auto">
            <h2 className="text-3xl md:text-4xl font-semibold text-gray-900 mb-8 text-center">Upcoming Events</h2>

            {upcoming.length === 0 ? (
              <EmptyState heading="No upcoming events right now">
                Check back soon; our 24/7 crisis line is always available at{" "}
                <a href="tel:423-476-3886" className="text-brand font-semibold hover:underline">(423) 476-3886</a>.
              </EmptyState>
            ) : (
              <EventsCarousel events={upcoming} onOpen={setSelectedEvent} />
            )}
          </div>
        </section>

        <div className="w-full h-1 bg-brand"></div>

        <section className="py-16 px-4 bg-white">
          <div className="max-w-3xl mx-auto">
            <h2 className="text-3xl md:text-4xl font-semibold text-gray-900 mb-8 text-center">Newsletters</h2>

            {newsletters.length === 0 ? (
              <EmptyState heading="No newsletters yet">
                Newsletters will appear here once the first issue is published.
              </EmptyState>
            ) : (
              <NewsletterList newsletters={newsletters} />
            )}
          </div>
        </section>
      </>
    );
  };

  return (
    <main>

      <section className="bg-purple-950 px-4 pt-20 pb-16 text-center">
        <p className="text-xs font-semibold tracking-widest uppercase text-purple-300 mb-3">
          Harbor Safe House &amp; Advocacy Center
        </p>
        <h1 className="text-5xl md:text-6xl font-semibold text-white mb-4">Events &amp; News</h1>
        <p className="text-white/80 text-lg max-w-xl mx-auto leading-relaxed">
          Upcoming events and past newsletters.
        </p>
      </section>

      <div className="flex items-center justify-center gap-8 px-8 py-5 bg-purple-50 border-b border-purple-100 flex-wrap">
        <span className="text-sm tracking-widest text-purple-700">24/7 Confidential Crisis Hotline</span>
        <div className="flex items-center gap-5">
          <a href="tel:423-476-3886" className="text-lg font-semibold text-brand hover:underline transition-all">Call (423) 476-3886</a>
          <span className="text-purple-300">|</span>
          <a href="sms:423-715-9614" className="text-lg font-semibold text-brand hover:underline transition-all">Text (423) 715-9614</a>
        </div>
        <span className="text-sm text-purple-700">Free &nbsp;·&nbsp; Confidential &nbsp;·&nbsp; 24 hours a day</span>
      </div>

      {renderSections()}

      {/* detail dialog; replaces the per-event routes a static export cannot generate */}
      {selectedEvent && (
        <EventModal event={selectedEvent} onClose={() => setSelectedEvent(null)} />
      )}

    </main>
  );
}
