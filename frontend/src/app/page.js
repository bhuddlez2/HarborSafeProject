// necessary for useState and client-side interactivity in this component, server components cannot use state or event handlers
"use client";

import { useState, useEffect, startTransition } from "react";
import Image from "next/image";

// Placeholder event data — replace with real content when available
const events = [
  { title: "Event 1", date: "April 5, 2026" },
  { title: "Event 2", date: "April 12, 2026" },
  { title: "Event 3", date: "April 20, 2026" },
  { title: "Event 4", date: "April 27, 2026" },
  { title: "Event 5", date: "May 3, 2026" },
];

export default function Home() {
  /*
  currentIndex tracks which event card is visible in the carousel,
  starts at 0 (first event),
  updated by the prev/next buttons and dot indicators
  */
  const [currentIndex, setCurrentIndex] = useState(0);

  /*
  showSafetyModal controls whether the security alert popup is visible,
  modal - a dialog that blocks all other interactions until dismissed,
  initialized to false so the server-rendered HTML and the first client render match,
  this prevents the React hydration mismatch error that occurs when server and client
  produce different HTML — the server runs without a browser so it must start as false,
  the useEffect below sets it to true after hydration so it shows on every page load,
  intentionally not stored in localStorage — the warning should appear every visit
  so that any person using the browser sees it, not just the first user ever
  */
  const [showSafetyModal, setShowSafetyModal] = useState(false);

  /*
  Shows the security alert modal after the component hydrates on the client,
  always shows regardless of previous visits — no localStorage check,
  runs once on mount after the first render so server and client HTML match first
  */
  useEffect(() => {
    startTransition(() => setShowSafetyModal(true));
  }, []);


  /*
  handleDismissSafetyModal is called when the user clicks OK on the security alert modal,
  hides the modal by setting showSafetyModal to false,
  intentionally does not store anything — the modal will show again on the next page load
  */
  const handleDismissSafetyModal = () => {
    setShowSafetyModal(false);
  };

  // Move to previous event, wraps around from first to last
  const handlePrev = () => {
    setCurrentIndex((prev) => (prev === 0 ? events.length - 1 : prev - 1));
  };

  // Move to next event, wraps around from last to first
  const handleNext = () => {
    setCurrentIndex((prev) => (prev === events.length - 1 ? 0 : prev + 1));
  };

  return (
    <div>
      {/*
      Safety modal — shown on every visit, icons from Feather Icons (https://feathericons.com) MIT License,
      z-200 places it above the navbar (z-50) and exit button (z-100)
      */}
      {showSafetyModal && (
        <div
          className="fixed inset-0 z-200 flex items-center justify-center bg-black/60 px-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby="safety-modal-heading"
        >
          {/* Modal card */}
          <div className="bg-white rounded-4xl shadow-xl max-w-md w-full overflow-hidden">

            {/* Brand header with shield icon and title */}
            <div className="bg-brand px-6 py-5 flex items-center gap-4">
              {/*
              bg-white/15 creates a subtle semi-transparent circle behind the shield icon
              */}
              <div className="w-10 h-10 rounded-full bg-white/15 flex items-center justify-center shrink-0">
                <svg viewBox="0 0 24 24" className="w-5 h-5 stroke-white/85 stroke-5 fill-none">
                  {/* shield icon, feathericons.com */}
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
              </div>
              <div>
                {/* Eyebrow label */}
                <p className="text-xs font-semibold tracking-widest uppercase text-purple-300 mb-0.5">Your safety matters</p>
                {/* Modal heading */}
                <h2 id="safety-modal-heading" className="text-xl font-bold text-white">Browse safely &amp; privately</h2>
              </div>
            </div>

            {/* Modal body */}
            <div className="px-6 py-5">
              {/* Introductory paragraph */}
              <p className="text-sm text-gray-600 leading-relaxed mb-5">
                Internet usage can be monitored and is difficult to erase completely. If you&apos;re
                concerned your activity is being watched, here are some ways to stay safer.
              </p>

              {/* Safety tips */}
              <div className="space-y-4 mb-5">

                {/* Exit quickly tip */}
                <div className="flex gap-3 items-start">
                  <div className="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center shrink-0">
                    <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-brand stroke-2 fill-none">
                      {/* log-out icon, feathericons.com */}
                      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><polyline points="16 17 21 12 16 7" /><line x1="21" y1="12" x2="9" y2="12" />
                    </svg>
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-brand mb-0.5">Exit quickly anytime</p>
                    {/*
                    kbd is styled to look like a physical keyboard key,
                    */}
                    <p className="text-xs text-gray-500 leading-relaxed">
                      Use the red &ldquo;Safe Exit&rdquo; button or press{" "}
                      <kbd className="bg-gray-100 border border-gray-300 rounded px-1 text-xs">Esc</kbd>{" "}
                      to leave this site immediately.
                    </p>
                  </div>
                </div>

                {/* Clear history tip */}
                <div className="flex gap-3 items-start">
                  <div className="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center shrink-0">
                    <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-brand stroke-2 fill-none">
                      {/* trash icon, feathericons.com */}
                      <polyline points="3 6 5 6 21 6" /><path d="M19 6l-1 14H6L5 6" /><path d="M10 11v6M14 11v6" /><path d="M9 6V4h6v2" />
                    </svg>
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-brand mb-0.5">Clear your history after visiting</p>
                    <p className="text-xs text-gray-500 leading-relaxed">
                      Delete your browser history or use a private / incognito window before you start.
                    </p>
                  </div>
                </div>

                {/* Call confidentially tip */}
                <div className="flex gap-3 items-start">
                  <div className="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center shrink-0">
                    <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-brand stroke-2 fill-none">
                      {/* phone icon, feathericons.com */}
                      <path d="M22 16.92v3a2 2 0 01-2.18 2A19.86 19.86 0 013.09 4.18 2 2 0 015.09 2h3a2 2 0 012 1.72c.13 1 .37 1.97.72 2.9a2 2 0 01-.45 2.11L9.09 10a16 16 0 006.91 6.91l1.27-1.27a2 2 0 012.11-.45c.93.35 1.9.59 2.9.72A2 2 0 0122 16.92z" />
                    </svg>
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-brand mb-0.5">Call us confidentially</p>
                    <p className="text-xs text-gray-500 leading-relaxed">
                      If you&apos;re worried about being monitored, call{" "}
                      <a href="tel:423-476-3886" className="text-brand font-semibold hover:underline">(423) 476-3886</a>{" "}
                      — available 24/7, always free.
                    </p>
                  </div>
                </div>

              </div>

              {/* 911 danger callout */}
              <div className="bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-5 flex gap-2 items-start">
                <svg viewBox="0 0 24 24" className="w-4 h-4 shrink-0 mt-0.5 stroke-red-700 stroke-2 fill-none">
                  {/* alert-circle icon, feathericons.com */}
                  <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                <p className="text-sm font-semibold text-red-700">If you are in immediate danger, call 911.</p>
              </div>

              {/* Action buttons */}
              <div className="space-y-2">
                <button
                  onClick={handleDismissSafetyModal}
                  className="w-full bg-brand text-white py-2.5 rounded-lg font-semibold text-sm border-2 border-brand hover:bg-white hover:text-brand cursor-pointer transition-all"
                >
                  I understand — continue to site
                </button>
                <button
                  onClick={() => window.location.replace("https://www.google.com")}
                  className="w-full bg-red-600 text-white py-2.5 rounded-lg font-semibold text-sm border-2 border-red-600 hover:bg-white hover:text-red-600 transition-all cursor-pointer"
                >
                  Exit quickly now
                </button>
              </div>
            </div>

          </div>
        </div>
      )}
      {/*
      Page content,
      main stops the content from being hidden behind the fixed navbar,
      screen readers use it to identify the main content of the page,
      search engines use it to understand what the page is about
      */}
      <main>
        {/*
        Hero section,
        id="home" so the Home nav link anchor scrolls here,
        relative for positioning context of the absolute children,
        h-[70vh] for 70% of the viewport height,
        flex items-center justify-center to center content both vertically and horizontally
        */}
        <section id="home" className="relative h-[70vh] flex items-center justify-center">
          {/*
          Background layer,
          absolute inset-0 stretches it to fill the entire section,
          bg-linear-to-br from-purple-900 to-purple-600 creates a diagonal purple gradient,
          the inner div adds a bg-black/30 dark overlay on top to improve text readability
          */}
          <div className="absolute inset-0 bg-linear-to-br from-purple-900 to-purple-600">
            <div className="absolute inset-0 bg-black/30"></div>
          </div>

          {/*
          Hero content,
          relative z-10 lifts it above the background layer,
          text-center and px-4 for centered layout with horizontal padding
          */}
          <div className="relative z-10 text-center px-4">
            {/*
            Main headline,
            font-serif italic for an elegant, emotional feel,
            text-6xl scaling up to text-8xl on larger screens for impact,
            max-w-4xl mx-auto to constrain width and keep it centered,
            mb-8 for spacing below before the button,
            leading-tight for compact line height on large text
            */}
            <p className="text-white text-6xl md:text-7xl lg:text-8xl font-serif italic mb-8 max-w-4xl mx-auto leading-tight">
              You are not alone.
            </p>
            {/*
            Call-to-action button,
            bg-white text-black for high contrast against the dark background,
            px-10 py-4 for generous padding,
            rounded-full for pill shape,
            hover:bg-gray-200 and hover:scale-105 for subtle hover feedback,
            shadow-lg for depth,
            font-semibold for emphasis
            */}
            <button className="bg-white text-black px-10 py-4 rounded-full hover:bg-gray-200 hover:scale-105 transition-all shadow-lg font-semibold">
              Get Help Now
            </button>
          </div>
        </section>

        {/*
        Upcoming Events section,
        py-16 px-4 for vertical and horizontal padding,
        bg-gray-50 for a light off-white background to visually separate it from the hero
        */}
        <div className="relative py-16 px-4 bg-gray-50">
          <div className="max-w-7xl mx-auto">
            {/*
            Section heading,
            text-center and mb-12 to center it with spacing below
            */}
            <h2 className="text-center mb-12 text-2xl font-semibold">Upcoming Events</h2>

            {/*
            Carousel wrapper,
            relative so the prev/next buttons can be positioned outside the card area,
            max-w-2xl mx-auto to constrain and center the card
            */}
            <div className="relative max-w-2xl mx-auto" aria-label="Displayed event">

              {/*
              Previous button,
              absolute positioned to the left of the card, vertically centered,
              -translate-x-16 pulls it outside the card boundary,
              bg-white rounded-full with shadow for the circular button look
              */}
              <button
                onClick={handlePrev}
                className="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-16 z-10 bg-white rounded-full p-3 shadow-lg hover:bg-gray-100 transition-all"
                aria-label="Previous event"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-6 h-6 text-gray-800">
                  <path d="m15 18-6-6 6-6" />
                </svg>
              </button>

              {/*
              Slide window,
              overflow-hidden clips the off-screen cards so only one is visible at a time
              */}
              <div className="overflow-hidden">
                {/*
                Slide track,
                flex lays all cards in a row,
                translateX shifts the track left by (currentIndex * 100%) to show the active card,
                transition-transform with duration-500 animates the slide
                */}
                <div
                  className="flex transition-transform duration-500 ease-in-out"
                  style={{ transform: `translateX(-${currentIndex * 100}%)` }}
                >
                  {events.map((event, index) => (
                    /*
                    Individual event card,
                    min-w-full ensures each card takes up the full width of the window,
                    group enables hover effects on child elements
                    */
                    <a key={index} href="#" className="min-w-full group">
                      <div className="bg-white rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-shadow">
                        {/*
                        Image placeholder,
                        bg-purple-200 as a stand-in until real event images are added,
                        h-80 for a tall image area
                        */}
                        <div className="relative h-80 bg-purple-200 flex items-center justify-center"></div>
                        <div className="p-8">
                          <h3 className="mb-2 text-center">{event.title}</h3>
                          <p className="text-gray-600 text-center">{event.date}</p>
                        </div>
                      </div>
                    </a>
                  ))}
                </div>
              </div>

              {/*
              Next button,
              absolute positioned to the right of the card, vertically centered,
              translate-x-16 pulls it outside the card boundary on the right side
              */}
              <button
                onClick={handleNext}
                className="absolute right-0 top-1/2 -translate-y-1/2 translate-x-16 z-10 bg-white rounded-full p-3 shadow-lg hover:bg-gray-100 transition-all"
                aria-label="Next event"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-6 h-6 text-gray-800">
                  <path d="m9 18 6-6-6-6" />
                </svg>
              </button>
            </div>

            {/*
            Dot indicators,
            one dot per event, centered below the carousel,
            active dot uses bg-brand and is wider (w-8) to distinguish it,
            inactive dots use bg-gray-300 with a hover darkening effect
            */}
            <div className="flex justify-center gap-2 mt-8">
              {events.map((_, index) => (
                <button
                  key={index}
                  onClick={() => setCurrentIndex(index)}
                  className={`h-3 rounded-full transition-all ${
                    index === currentIndex
                      ? "bg-brand w-8"
                      : "w-3 bg-gray-300 hover:bg-gray-400"
                  }`}
                  aria-label={`Go to event ${index + 1}`}
                />
              ))}
            </div>
          </div>
        </div>

        {/*
        About section,
        id="about" so the About nav link anchor scrolls here,
        py-20 px-4 for vertical padding and horizontal gutters,
        bg-white background
        */}
        <section className="py-20 px-4 bg-white">
          <div className="max-w-7xl mx-auto">

            {/*
            Two-column row: WeAreHere image left, text content right,
            grid md:grid-cols-2 for two columns on medium screens and up,
            gap-12 for spacing between columns,
            items-start so both columns align to the top
            */}
            <div className="grid md:grid-cols-2 gap-12 items-start">

              {/*
              Left column: WeAreHere image,
              w-full stretches the image to fill the column width,
              rounded-lg for rounded corners,
              shadow-lg for a drop shadow,
              NOTE: if white padding appears around the image it is baked into the png itself,
              to fix it the png will need to be cropped or exported without padding
              */}
              <div>
                <Image
                  src="/WeAreHere.svg"
                  alt="Supportive hands"
                  width={0}
                  height={0}
                  sizes="100vw"
                  className="w-full h-auto rounded-lg shadow-lg"
                />
              </div>

              {/*
              Right column: heading, body copy, and hotline info,
              space-y-6 for consistent vertical spacing between children
              */}
              <div className="space-y-6">

                {/*
                Section heading,
                text-brand purple,
                text-4xl for prominence,
                text-center to match the Figma design,
                mb-6 for spacing below before the body copy
                */}
                <h1 className="mb-6 text-brand text-center text-4xl font-semibold">We Are Here to Help You.</h1>

                {/* Body copy, leading-relaxed for comfortable line height */}
                <p className="leading-relaxed text-gray-900 text-center mb-4">
                  <strong className="text-brand">The Harbor Safe House &amp; Advocacy Center, a program of Family Resource Agency, Inc.</strong>,
                  provides a secure environment and a comprehensive range of holistic services for
                  individuals and their children who are survivors of domestic violence and/or sexual
                  assault. These services empower survivors by giving them the tools and support they
                  need to rebuild their lives.
                </p>

                {/*
                Hotline sub-section,
                mt-16 pushes it further down to visually separate it from the body copy above
                */}
                <div className="mt-16">

                  {/* Hotline heading, brand color and size as the section heading above */}
                  <h2 className="text-center mb-4 text-brand text-4xl font-semibold">24/7 Confidential Hotline</h2>

                  {/* Supporting italic description */}
                  <p className="text-gray-900 italic text-center mb-4">
                    Our advocates are available to talk with anyone who is experiencing domestic
                    violence or sexual assault at any time, day or night.
                  </p>

                  {/*
                  Phone and text links,
                  hover:text-brand highlights them in brand color on hover,
                  transition-colors for a smooth color change
                  */}
                  <p className="text-center text-gray-900">
                    <a href="tel:423-476-3886" className="hover:text-brand transition-colors">Call (423) 476-3886</a>
                    {" | "}
                    <a href="sms:423-715-9614" className="hover:text-brand transition-colors">Text (423) 715-9614</a>
                  </p>

                </div>
              </div>
            </div>

            {/*
            Bottom row: Our Mission on the left, Our Values on the right,
            mt-16 for spacing above to separate from the row above,
            grid md:grid-cols-2 for two columns on medium screens and up,
            gap-12 for spacing between columns
            */}
            <div className="mt-16 grid md:grid-cols-2 gap-12">

              {/* Our Mission column, text-center to align image and caption */}
              <div className="text-center">
                {/*
                mb-6 for spacing between image and caption,
                */}
                <div className="mb-6">
                  <Image
                    src="/OurMission.svg"
                    alt="Our Mission"
                    width={0}
                    height={0}
                    sizes="100vw"
                    className="w-full h-auto rounded-lg shadow-lg"
                  />
                </div>
                {/* Mission caption */}
                <p className="text-gray-700 leading-relaxed">
                  Strengthening communities by providing a safe, caring place and high-quality advocacy for abuse victims.
                </p>
              </div>

              {/* Our Values column, text-center to align image and caption */}
              <div className="text-center">
                {/*
                mb-6 for spacing between image and caption,
                */}
                <div className="mb-6">
                  <Image
                    src="/OurValues.svg"
                    alt="Our Values"
                    width={0}
                    height={0}
                    sizes="100vw"
                    className="w-full h-auto rounded-lg shadow-lg"
                  />
                </div>
                {/* Values caption */}
                <p className="text-gray-700 leading-relaxed mb-6">
                  Our internal compass steers us toward where we feel aligned with our true direction.
                  At Harbor Safe House &amp; Advocacy Center our core values guide our work.
                </p>
                {/*
                NESW core values list,
                flex-wrap and justify-center so items wrap on smaller screens,
                gap-4 for spacing between items,
                text-left so the letter labels align with their value text
                */}
                <div className="flex flex-wrap justify-center gap-4 text-left">
                  <div><span className="text-brand font-bold">N-</span> Nurture</div>
                  <div><span className="text-brand font-bold">E-</span> Empower</div>
                  <div><span className="text-brand font-bold">S-</span> Strengthen</div>
                  <div><span className="text-brand font-bold">W-</span> Wholeness</div>
                </div>
              </div>

            </div>

          </div>
        </section>

      </main>
    </div>
  );
}
