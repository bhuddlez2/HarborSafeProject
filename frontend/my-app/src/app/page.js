"use client";

import { useState } from "react";
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
      Quick exit button
      safety feature for users who need to leave the site quickly,
      fixed to the top right corner of the page so it is always visible,
      z-[100] to ensure it sits above all other content including the navbar (higher stack order),
      bg-red-600 for red background color to signal urgency,
      hover:bg-white and hover:text-red-600 to invert colors on hover,
      border-2 border-red-600 keeps the red border visible in both states,
      rounded-full for pill shape,
      px-6 py-2.5 for padding,
      shadow-lg for depth,
      transition-all and hover:scale-110 for smooth scale animation on hover,
      onClick redirects to Google to quickly hide the page from view,
      aria-label and title for accessibility and tooltip text
      */}
      <button
        className="fixed top-4.75 right-4 z-100 bg-red-600 hover:bg-white text-white hover:text-red-600 border-2 
        border-red-600 rounded-full px-6 py-2.5 shadow-lg transition-all hover:scale-110"

        aria-label="Quick exit button"
        title="Quick Exit (exit to google)"
        onClick={() => window.location.href = 'https://www.google.com'}
      >
        Exit
      </button>
      {/* 
      navigation bar,
      bg-[#5C0F8B] for background color,
      h-[90px] for height,
      flex and items-center for alignment, 
      justify-between to space out logo and nav links,
      px-8 for padding,  
      fixed at the top, 
      top left w for positioning, 
      z-50 ensures it sits above other content aka higher stack order 
      */}
      <nav
        className="bg-brand h-22.5 flex items-center justify-between px-8 fixed top-0 left-0 right-0 z-50"
      >
        {/* 
        HarborSafe logo on the left side of the navbar
        src points to the logo image, 
        alt text for accessibility,
        h-20 w-20 for sizing, 
        */}
        <div className="flex items-center gap-4">
          <Image
            src="/HSHAC.svg"
            alt="Harbor Safe House and Advocacy Center Logo"
            width={80}
            height={80}
          />
          {/* HSHAC text next to the logo, styled with white color and larger font size */}
          <span className="text-white text-xl">HSHAC</span>
        </div>

          {/* Navigation links on the right side of the navbar,
          flex and items-center for alignment, 
          gap-10 for spacing between links, 
          mr-32 for right margin to create space from the edge */}
        <div className="flex items-center gap-10 mr-32">
          {/* 
          Home link  
          text-white for link color,
          font-bold for emphasis, 
          group and relative for styling the hover effect on the span inside,
          */}
          <a href="#home" className="text-white font-bold group relative">
            {/*
            The span inside the Home link is styled to create a pill-shaped background on hover,
            block to make it a block element, 
            bg-white for background color, 
            text-brand for text color, 
            px-4 py-2 for padding, 
            rounded-full for fully rounded corners, 
            transition-all and duration-300 for smooth hover effect
             */}
            <span className="block bg-white text-brand px-4 py-2 rounded-full transition-all duration-300">
              Home
              </span>
          </a>
          <a href="#about" className="text-white font-bold group relative">
            <span className="block px-4 py-2 rounded-full hover:bg-white hover:text-brand transition-all duration-300">
              About
              </span>
          </a>
          <a href="#services" className="text-white font-bold group relative">
            <span className="block px-4 py-2 rounded-full hover:bg-white hover:text-brand transition-all duration-300">
              Get Support
              </span>
          </a>
          <a href="#donate" className="text-white font-bold group relative">
            <span className="block px-4 py-2 rounded-full hover:bg-white hover:text-brand transition-all duration-300">
              Give Support
              </span>
          </a>
          <a href="#resources" className="text-white font-bold group relative">
            <span className="block px-4 py-2 rounded-full hover:bg-white hover:text-brand transition-all duration-300">
              Resources
              </span>
          </a>
          {/* Divider between navigation links and language switcher */}
          <div className="h-8 w-px bg-white"></div>
          {/* Language switcher */}
          <a href="#espanol" className="text-white text-sm hover:underline transition-all">
            En Español
            </a>
        </div>
      </nav>
      {/* 
      Page content below 
      main stops the content from being hidden behind the fixed navbar,
      screen readers use it to identify the main content of the page,
      search engines use it to understand what the page is about,
      */}
      <main className="pt-22.5">
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
            <div className="relative max-w-2xl mx-auto">

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
                text-[#5C0F8B] for brand purple,
                text-4xl for prominence,
                text-center to match the Figma design,
                mb-6 for spacing below before the body copy
                */}
                <h1 className="mb-6 text-[#5C0F8B] text-center text-4xl font-semibold">We Are Here to Help You.</h1>

                {/* Body copy, leading-relaxed for comfortable line height */}
                <p className="leading-relaxed text-gray-900 text-center mb-4">
                  <strong className="text-[#5C0F8B]">The Harbor Safe House &amp; Advocacy Center, a program of Family Resource Agency, Inc.</strong>,
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

                  {/* Hotline heading, same brand color and size as the section heading above */}
                  <h2 className="text-center mb-4 text-[#5C0F8B] text-4xl font-semibold">24/7 Confidential Hotline</h2>

                  {/* Supporting italic description */}
                  <p className="text-gray-900 italic text-center mb-4">
                    Our advocates are available to talk with anyone who is experiencing domestic
                    violence or sexual assault at any time, day or night.
                  </p>

                  {/*
                  Phone and text links,
                  hover:text-[#5C0F8B] highlights them in brand color on hover,
                  transition-colors for a smooth color change
                  */}
                  <p className="text-center text-gray-900">
                    <a href="tel:423-476-3886" className="hover:text-[#5C0F8B] transition-colors">Call (423) 476-3886</a>
                    {" | "}
                    <a href="sms:423-715-9614" className="hover:text-[#5C0F8B] transition-colors">Text (423) 715-9614</a>
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
                  <div><span className="text-[#5C0F8B] font-bold">N-</span> Nurture</div>
                  <div><span className="text-[#5C0F8B] font-bold">E-</span> Empower</div>
                  <div><span className="text-[#5C0F8B] font-bold">S-</span> Strengthen</div>
                  <div><span className="text-[#5C0F8B] font-bold">W-</span> Wholeness</div>
                </div>
              </div>

            </div>

          </div>
        </section>

        {/*
        Footer,
        bg-[#5C0F8B] for brand purple background,
        text-white for all text inside,
        py-12 px-4 for vertical padding and horizontal gutters
        */}
        <footer className="bg-[#5C0F8B] text-white py-12 px-4">

          {/*
          Two-column layout: hotline info on the left, social links on the right,
          max-w-7xl mx-auto to match the width of the sections above,
          grid md:grid-cols-2 for two columns on medium screens and up,
          gap-8 for spacing between columns
          */}
          <div className="max-w-7xl mx-auto grid md:grid-cols-2 gap-8">

            {/* Left column: crisis hotline heading and phone link */}
            <div>
              {/* mb-4 for spacing between heading and link below */}
              <h3 className="font-semibold mb-4">24/7 Confidential Domestic Violence and Sexual Assault Crisis Hotline</h3>
              <div className="space-y-3">
                {/*
                Phone link,
                flex items-center gap-3 to align the icon and number side by side,
                hover:text-purple-200 for a lighter purple on hover,
                transition-colors for a smooth color change
                */}
                <a href="tel:423-476-3886" className="flex items-center gap-3 hover:text-purple-200 transition-colors">
                  {/* Phone icon */}
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-5 h-5">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.13 12 19.79 19.79 0 0 1 1.06 3.38 2 2 0 0 1 3.04 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" />
                  </svg>
                  <span>(423) 476-3886</span>
                </a>
              </div>
            </div>

            {/* Right column: social media links */}
            <div>
              {/* mb-4 for spacing between heading and icons below */}
              <h3 className="font-semibold mb-4">Connect With Us</h3>
              {/*
              flex gap-4 to lay the icons out in a row with spacing,
              hover:text-purple-200 on each link for consistent hover color
              */}
              <div className="flex gap-4">
                {/* Facebook */}
                <a href="#" className="hover:text-purple-200 transition-colors" aria-label="Facebook">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-6 h-6">
                    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />
                  </svg>
                </a>
                {/* Twitter */}
                <a href="#" className="hover:text-purple-200 transition-colors" aria-label="Twitter">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-6 h-6">
                    <path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z" />
                  </svg>
                </a>
                {/* Instagram */}
                <a href="#" className="hover:text-purple-200 transition-colors" aria-label="Instagram">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-6 h-6">
                    <rect width="20" height="20" x="2" y="2" rx="5" ry="5" />
                    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
                    <line x1="17.5" x2="17.51" y1="6.5" y2="6.5" />
                  </svg>
                </a>
              </div>
            </div>
          </div>

          {/* Divider line at the bottom of the footer, border-purple-700 for a subtle separator */}
          <div className="max-w-7xl mx-auto mt-8 pt-8 border-t border-purple-700"></div>

        </footer>
      </main>
    </div>
  );
}
