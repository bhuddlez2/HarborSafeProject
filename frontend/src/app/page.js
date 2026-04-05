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
      Security Alert modal,
      only renders when showSafetyModal is true, which is on the first ever visit,
      once the user clicks OK it is stored in localStorage and will not show again,
      fixed inset-0 stretches the dark overlay across the entire viewport,
      z-200 places it above the navbar (z-50) and exit button (z-100),
      flex items-center justify-center centers the modal card both vertically and horizontally,
      bg-black/60 creates a semi-transparent dark backdrop to focus attention on the modal,
      px-4 adds horizontal padding so the card does not touch screen edges on small screens
      */}
      {showSafetyModal && (
        <div
          className="fixed inset-0 z-200 flex items-center justify-center bg-black/60 px-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby="safety-modal-heading"
        >
          {/*
          Modal card,
          bg-white for a clean white background,
          rounded-lg for soft corners,
          shadow-2xl for a strong drop shadow to lift it off the backdrop,
          max-w-lg to cap the width so it does not stretch too wide on large screens,
          w-full so it fills available space on small screens up to that max,
          p-8 for generous internal padding
          */}
          <div className="bg-white rounded-lg shadow-2xl max-w-lg w-full p-8">
            {/*
            Modal heading,
            text-2xl font-bold for prominence,
            text-brand for the brand purple color,
            mb-4 for spacing below before the first paragraph
            */}
            <h2 id="safety-modal-heading" className="text-2xl font-bold text-brand mb-4">Security Alert</h2>
            {/*
            Internet safety warning paragraph,
            mb-3 for spacing below before the next paragraph,
            the phone number is a clickable tel link styled in brand color
            so users can tap it directly on mobile
            */}
            <p className="text-gray-800 mb-3">
              Internet usage can be monitored and is impossible to erase completely. If you&apos;re
              concerned your internet usage might be monitored, call us at{" "}
              <a href="tel:423-476-3886" className="text-brand font-semibold hover:underline">
                (423) 476-3886
              </a>
              .
            </p>
            {/*
            Quick exit instructions paragraph,
            bold so it stands out as an important action the user can take,
            kbd element is styled to look like a keyboard key for clarity
            */}
            <p className="text-gray-800 mb-3">
              <strong>
                Click the red &ldquo;Exit&rdquo; button in the lower-right corner or press{" "}
                <kbd className="bg-gray-100 border border-gray-300 rounded px-1 py-0.5 text-sm">Esc</kbd>{" "}
                at any time to leave this site immediately.
              </strong>
            </p>
            {/*
            Emergency warning paragraph,
            text-red-700 and font-semibold to make it visually urgent,
            mb-6 for extra spacing above the OK button below
            */}
            <p className="text-red-700 font-semibold mb-6">
              Please contact 911 if you feel you are in immediate danger or a life-threatening situation.
            </p>
            {/*
            OK dismiss button,
            bg-brand text-white for brand styling,
            px-8 py-3 for generous padding,
            rounded-full for pill shape to match the site's button style,
            hover:bg-purple-800 darkens the button on hover for feedback,
            transition-all for a smooth color change,
            onClick calls handleDismissSafetyModal which saves to localStorage and hides the modal
            */}
            <button
              onClick={handleDismissSafetyModal}
              className="bg-brand text-white px-8 py-3 rounded-full font-semibold hover:bg-purple-800 transition-all"
            >
              OK
            </button>
          </div>
        </div>
      )}
      {/*
      Quick exit button
      safety feature for users who need to leave the site quickly,
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
