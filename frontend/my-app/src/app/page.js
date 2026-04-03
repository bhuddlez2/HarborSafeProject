"use client";

export default function Home() {
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
        className="fixed top-[19px] right-4 z-[100] bg-red-600 hover:bg-white text-white hover:text-red-600 border-2 
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
        className="bg-brand h-[90px] flex items-center justify-between px-8 fixed top-0 left-0 right-0 z-50"
      >
        {/* 
        HarborSafe logo on the left side of the navbar
        src points to the logo image, 
        alt text for accessibility,
        h-20 w-20 for sizing, 
        */}
        <div className="flex items-center gap-4">
          <img
            src="/HSHAC.svg"
            alt="Harbor Safe House and Advocacy Center Logo"
            className="h-20 w-20"
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
      <main className="pt-[90px]">
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
      </main>
    </div>
  );
}
