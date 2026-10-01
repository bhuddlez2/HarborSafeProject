// necessary for useState and client-side interactivity in this component, server components cannot use state or event handlers
"use client";

import { useState } from "react";
import Image from "next/image";
import Link from "next/link";
import Modal from "./components/Modal";
import CrisisHotlineStrip from "./components/CrisisHotlineStrip";

export default function Home() {
  /*
  showHelpModal controls the "Get Help Now" contact list opened from the hero button
  */
  const [showHelpModal, setShowHelpModal] = useState(false);

  /*
  replaceNavigate does a full page load that replaces the current history entry (same
  history behavior as <Link replace>), used by the service cards' "Learn more" links to
  /get-support/#... anchors. Next's client router mishandles repeat hash navigations in dev, 
  so these links bypass it and let the browser scroll to the anchor natively.
  */
  const replaceNavigate = (e) => {
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    e.preventDefault();
    window.location.replace(e.currentTarget.href);
  };

  return (
    <div>
      {showHelpModal && (
        <Modal
          title="Get help now"
          subtitle={<>Free, confidential support.<br />You don&apos;t have to go through this alone.</>}
          onClose={() => setShowHelpModal(false)}
        >
          <div className="p-6 space-y-4">

            {/* 911 danger callout, alert-circle icon from feathericons.com */}
            <div className="bg-red-50 border border-red-200 rounded-lg px-4 py-3 flex gap-2 items-start">
              <svg viewBox="0 0 24 24" className="w-4 h-4 shrink-0 mt-0.5 stroke-red-700 stroke-2 fill-none" aria-hidden="true">
                <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>
              <a href="tel:911" className="text-sm font-semibold text-red-700 hover:underline">If you are in immediate danger, call 911.</a>
            </div>

            {/*
            Harbor Safe crisis line, soft fill instead of a border so the buttons carry the emphasis,
            buttons stack on narrow phones so the numbers can't wrap
            */}
            <div className="bg-purple-50 rounded-xl p-4">
              <p className="text-xs font-semibold tracking-widest uppercase text-brand mb-1">24/7 Crisis Line</p>
              <p className="text-sm font-semibold text-brand mb-3">Harbor Safe House &amp; Advocacy Center</p>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <a href="tel:423-476-3886" className="text-center bg-brand text-white py-2.5 rounded-lg font-semibold text-sm border-2 border-brand hover:bg-purple-800 hover:border-purple-800 transition-all">
                  Call (423) 476-3886
                </a>
                <a href="sms:423-715-9614" className="text-center bg-white text-brand py-2.5 rounded-lg font-semibold text-sm border-2 border-brand hover:bg-brand hover:text-white transition-all">
                  Text (423) 715-9614
                </a>
              </div>
            </div>

            <div className="divide-y divide-gray-100 border border-gray-200 rounded-xl">
              {[
                { name: "National Domestic Violence Hotline", note: "Call 24/7 · Text START to 88788", phone: "1-800-799-7233", tel: "18007997233" },
                { name: "988 Suicide & Crisis Lifeline", note: "Call or text, 24/7", phone: "988", tel: "988" },
              ].map(({ name, note, phone, tel }) => (
                /* stacked on phones so the pill doesn't squeeze the name, side by side from sm up */
                <div key={name} className="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4 px-4 py-3">
                  <div>
                    {/* text-balance splits long names into even lines instead of orphaning the last word */}
                    <p className="text-sm font-semibold text-brand text-balance">{name}</p>
                    <p className="text-xs text-gray-600">{note}</p>
                  </div>
                  {/* pill with phone icon (feathericons.com) so the number is intuitively tappable */}
                  <a
                    href={`tel:${tel}`}
                    aria-label={`Call ${name} at ${phone}`}
                    className="shrink-0 inline-flex items-center gap-1.5 bg-purple-100 text-brand text-sm font-semibold px-3 py-1.5 rounded-full hover:bg-brand hover:text-white transition-all"
                  >
                    <svg viewBox="0 0 24 24" className="w-3.5 h-3.5 stroke-current stroke-2 fill-none" aria-hidden="true">
                      <path d="M22 16.92v3a2 2 0 01-2.18 2A19.86 19.86 0 013.09 4.18 2 2 0 015.09 2h3a2 2 0 012 1.72c.13 1 .37 1.97.72 2.9a2 2 0 01-.45 2.11L9.09 10a16 16 0 006.91 6.91l1.27-1.27a2 2 0 012.11-.45c.93.35 1.9.59 2.9.72A2 2 0 0122 16.92z" />
                    </svg>
                    {phone}
                  </a>
                </div>
              ))}
            </div>

            <div className="flex flex-wrap gap-x-6 gap-y-2 pt-1 text-sm">
              <Link href="/get-support" className="font-semibold text-brand hover:underline">Our services →</Link>
              <Link href="/resources" className="font-semibold text-brand hover:underline">More resources →</Link>
            </div>
          </div>
        </Modal>
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
        <section id="home" className="on-dark relative h-[70vh] flex items-center justify-center">
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
            <button
              onClick={() => setShowHelpModal(true)}
              className="bg-white text-black px-10 py-4 rounded-full hover:bg-gray-200 hover:scale-105 transition-all shadow-lg font-semibold cursor-pointer"
            >
              Get Help Now
            </button>
          </div>
        </section>

        {/* Hotline strip */}
        <CrisisHotlineStrip />
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
                  <a href="/get-support/#shelter" onClick={replaceNavigate} className="text-xs font-semibold text-brand hover:underline transition-all">Learn more →</a>
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
                  <a href="/get-support/#counseling" onClick={replaceNavigate} className="text-xs font-semibold text-brand hover:underline transition-all">Learn more →</a>
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
                  {/* Call and text, matching the card copy and the hotline strip */}
                  <a href="tel:423-476-3886" className="text-xs font-semibold text-brand hover:underline transition-all">Call →</a>
                  <a href="sms:423-715-9614" className="ml-4 text-xs font-semibold text-brand hover:underline transition-all">Text →</a>
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
                  { letter: "N", word: "Nurture",    desc: "Caring for the whole person",  },
                  { letter: "E", word: "Empower",    desc: "Building strength & agency",  },
                  { letter: "W", word: "Wholeness",  desc: "Healing through holistic care",  },
                  { letter: "S", word: "Strengthen", desc: "Supporting survivors & community",  },
                ].map(({ letter, word, desc, icon }) => (
                  <div key={letter} className="relative bg-white border border-gray-200 rounded-lg p-5">
                    {icon && (
                      <a
                        title="Compass icon by benanibens Flaticon"
                        className="absolute top-3 right-3"
                      >
                        <Image
                          src={icon}
                          alt="compass direction"
                          width={48}
                          height={48}
                          className="filter-[invert(73%)_sepia(24%)_saturate(830%)_hue-rotate(213deg)_brightness(103%)_contrast(100%)]"
                        />
                      </a>
                    )}
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
