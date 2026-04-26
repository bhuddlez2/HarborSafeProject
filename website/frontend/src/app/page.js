// necessary for useState and client-side interactivity in this component, server components cannot use state or event handlers
"use client";

import { useState, useEffect, startTransition } from "react";
import Image from "next/image";
import Link from "next/link";

export default function Home() {
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

  useEffect(() => {
    document.body.classList.toggle("safety-modal-open", showSafetyModal);
    return () => document.body.classList.remove("safety-modal-open");
  }, [showSafetyModal]);

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
                    <p className="text-xs text-gray-600 leading-relaxed">
                      Use the red &ldquo;Safe Exit&rdquo; button or press{" "}
                      <kbd className="bg-gray-100 border border-gray-500 rounded px-1 text-xs">Esc</kbd>{" "}
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
                    <p className="text-xs text-gray-600 leading-relaxed">
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
                <a href="tel:911" className="text-sm font-semibold text-red-700 hover:underline">If you are in immediate danger, call 911.</a>
              </div>

              {/* Action buttons */}
              <div className="space-y-2">
                <button
                  onClick={handleDismissSafetyModal}
                  className="hover:scale-105 w-full bg-brand text-white py-2.5 rounded-lg font-semibold text-sm border-2 border-brand hover:bg-white hover:text-brand cursor-pointer transition-all"
                >
                  I understand — continue to site
                </button>
                <button
                  onClick={() => window.location.replace("https://www.google.com")}
                  className="hover:scale-105 w-full bg-red-600 text-white py-2.5 rounded-lg font-semibold text-sm border-2 border-red-600 hover:bg-white hover:text-red-600 transition-all cursor-pointer"
                >
                  Safe Exit
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
          Background image,
          "White lighthouse on rocky seashore" — Unsplash license (free to use),
          https://unsplash.com/photos/white-lighthouse-on-rocky-seashore-KPaSCpklCZw,
          fill makes the image cover the full section (requires relative parent),
          object-cover crops to fill without distortion,
          priority preloads the image since it is above the fold,
          the inner div adds a bg-black/40 dark overlay to improve text readability
          */}
          <div className="absolute inset-0">
            <Image
              src="/sunset_by_the_lighthouse.jpg"
              alt="White lighthouse on rocky seashore at sunset"
              fill
              className="object-cover"
              priority
            />
            <div className="absolute inset-0 bg-black/40"></div>
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
        Hotline strip,
        sits directly below the hero,
        bg-purple-50 for a light tinted background,
        border-b border-purple-100 for a subtle separator below,
        flex items-center justify-center gap-8 to lay out the three elements centered with spacing,
        flex-wrap so it stacks gracefully on small screens
        */}
        <div className="flex items-center justify-center gap-8 px-8 py-5 bg-purple-50 border-b border-purple-100 flex-wrap">
          {/* Label */}
          <span className="text-sm tracking-widest text-purple-700">24/7 Confidential Crisis Hotline</span>
          {/* Call and text numbers with a divider between them */}
          <div className="flex items-center gap-5">
            <a href="tel:423-476-3886" className="text-lg font-semibold text-brand hover:underline transition-all">Call (423) 476-3886</a>
            <span className="text-purple-300">|</span>
            <a href="sms:423-715-9614" className="text-lg font-semibold text-brand hover:underline transition-all">Text (423) 715-9614</a>
          </div>
          {/* Supporting note */}
          <span className="text-sm text-purple-700">Free &nbsp;·&nbsp; Confidential &nbsp;·&nbsp; 24 hours a day</span>
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
                  src="/WeAreHere.png"
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

                {/* Section heading above the testimonials */}
                <h1 className="text-center text-brand text-4xl font-semibold">We Are Here to Help You.</h1>

                {/* First testimonial header and block */}
                <h2 className="text-brand text-xl font-semibold mt-8">A Survivor&apos;s Story</h2>
                <div className="mt-3 border-l-4 border-brand pl-6 py-4 bg-purple-50 rounded-r-lg">
                  <span className="text-5xl text-brand leading-none">&ldquo;</span>
                  <p className="text-gray-700 leading-relaxed text-lg mb-4 -mt-2">
                    They were there for me when I had nowhere else to turn. I came in with nothing and
                    left with the strength to rebuild my life. I will never forget what they did for
                    me and my children.
                  </p>
                  <p className="text-sm font-semibold text-brand">— Anonymous Survivor</p>
                </div>

                {/* Second testimonial header and block */}
                <h2 className="text-brand text-xl font-semibold mt-8">Finding Safety &amp; Support</h2>
                <div className="mt-3 border-l-4 border-brand pl-6 py-4 bg-purple-50 rounded-r-lg">
                  <span className="text-5xl text-brand leading-none">&ldquo;</span>
                  <p className="text-gray-700 leading-relaxed text-lg mb-4 -mt-2">
                    The advocates here never made me feel judged. They listened, they helped me find
                    housing, and they stood by me through the entire legal process. I finally felt safe
                    for the first time in years.
                  </p>
                  <p className="text-sm font-semibold text-brand">— Anonymous Survivor</p>
                </div>

              </div>
            </div>

            {/*
            How we help section,
            mt-16 for spacing above,
            three service cards in a responsive grid
            */}
            <div className="mt-16">

              {/* Section kicker and heading */}
              <span className="inline-block text-xs font-semibold tracking-widest uppercase bg-purple-100 text-brand px-3 py-1 rounded-full mb-4">How we help</span>
              <h2 className="text-3xl font-semibold text-brand mb-3">Comprehensive support for survivors</h2>
              <p className="text-gray-600 leading-relaxed mb-10 max-w-xl">
                We provide a secure environment and the tools survivors need to rebuild their lives — for individuals and their children.
              </p>

              {/*
              Service cards grid,
              grid-cols-1 on mobile, md:grid-cols-3 on medium screens and up,
              gap-5 for spacing between cards
              */}
              <div className="grid md:grid-cols-3 gap-5">

                {/* Emergency Shelter card */}
                <div className="border border-gray-200 rounded-xl p-6">
                  {/* Icon box */}
                  <div className="w-9 h-9 rounded-lg bg-purple-100 flex items-center justify-center mb-5">
                    <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-brand stroke-2 fill-none">
                      {/* home icon, feathericons.com */}
                      <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" /><polyline points="9,22 9,12 15,12 15,22" />
                    </svg>
                  </div>
                  <p className="text-sm font-semibold text-brand mb-2">Emergency Shelter</p>
                  <p className="text-sm text-gray-600 leading-relaxed mb-4">Safe, confidential housing for individuals and families escaping dangerous situations.</p>
                  <Link replace href="#" className="text-xs font-semibold text-brand hover:underline transition-all">Learn more →</Link>
                </div>

                {/* Counseling & Advocacy card */}
                <div className="border border-gray-200 rounded-xl p-6">
                  <div className="w-9 h-9 rounded-lg bg-purple-100 flex items-center justify-center mb-5">
                    <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-brand stroke-2 fill-none">
                      {/* message-square icon, feathericons.com */}
                      <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                    </svg>
                  </div>
                  <p className="text-sm font-semibold text-brand mb-2">Counseling &amp; Advocacy</p>
                  <p className="text-sm text-gray-600 leading-relaxed mb-4">Individual and group counseling, legal advocacy, and court support for survivors.</p>
                  <Link replace href="#" className="text-xs font-semibold text-brand hover:underline transition-all">Learn more →</Link>
                </div>

                {/* 24/7 Crisis Line card */}
                <div className="border border-gray-200 rounded-xl p-6">
                  <div className="w-9 h-9 rounded-lg bg-purple-100 flex items-center justify-center mb-5">
                    <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-brand stroke-2 fill-none">
                      {/* clock icon, feathericons.com */}
                      <circle cx="12" cy="12" r="10" /><polyline points="12,6 12,12 16,14" />
                    </svg>
                  </div>
                  <p className="text-sm font-semibold text-brand mb-2">24/7 Crisis Line</p>
                  <p className="text-sm text-gray-600 leading-relaxed mb-4">Trained advocates available any time — call or text, day or night, always free.</p>
                  <a href="tel:423-476-3886" className="text-xs font-semibold text-brand hover:underline transition-all">Call now →</a>
                </div>

              </div>
            </div>

            {/*
            Mission and values row,
            mt-16 for spacing above,
            bg-gray-50 and border-y for a subtle section break,
            grid md:grid-cols-2 for two columns on medium screens and up,
            gap-16 for generous spacing between columns,
            py-16 px-8 for breathing room inside the section
            */}
            <div className="mt-16 bg-gray-50 border-y border-gray-200 rounded-lg py-16 px-8 grid md:grid-cols-2 gap-16 items-center">

              {/* Left column: kicker, heading, body, FRA footnote */}
              <div>
                {/* Kicker label */}
                <p className="text-xs font-semibold tracking-widest uppercase text-brand mb-4">our cardinal values</p>
                {/* Mission heading */}
                <h2 className="text-3xl font-semibold text-brand mb-6">Strengthening communities through safe, caring advocacy</h2>
                {/* Mission body */}
                <p className="text-gray-700 leading-relaxed mb-8">
                  We provide a secure environment and comprehensive holistic services for individuals
                  and their children who are survivors of domestic violence and/or sexual assault —
                  giving them the tools and support they need to rebuild their lives.
                </p>
                {/*
                FRA footnote,
                border-t separates it visually from the body text,
                pt-5 for spacing between the border and the text
                */}
                <div className="border-t border-gray-200 pt-5 text-sm text-gray-600">
                  A program of <strong className="text-brand">Family Resource Agency, Inc.</strong> — Impacting Lives and Changing Communities Since 1972
                </div>
              </div>

              {/*
              Right column: 2x2 grid of NESW value cards,
              gap-4 for spacing between cards,
              each card has a large letter, value name, and short description
              */}
              <div className="grid grid-cols-2 gap-4">
                {[
                  { letter: "N", word: "Nurture",    desc: "Caring for the whole person" },
                  { letter: "E", word: "Empower",    desc: "Building strength & agency" },
                  { letter: "S", word: "Strengthen", desc: "Supporting survivors & community" },
                  { letter: "W", word: "Wholeness",  desc: "Healing through holistic care" },
                ].map(({ letter, word, desc }) => (
                  <div key={letter} className="bg-white border border-gray-200 rounded-lg p-5">
                    {/* Large decorative letter */}
                    <p className="text-4xl font-bold text-brand leading-none mb-2">{letter}</p>
                    {/* Value name */}
                    <p className="text-sm font-semibold text-brand mb-1">{word}</p>
                    {/* Short description, gray-600 for contrast compliance */}
                    <p className="text-xs text-gray-600">{desc}</p>
                  </div>
                ))}
              </div>

            </div>

          </div>
        </section>

      </main>
    </div>
  );
}
