"use client";

import Image from "next/image";
import Link from "next/link";

/*
Navbar component,
extracted into its own file so layout.js can remain a server component,
server components are required to export metadata (page title, description etc),
"use client" is declared here instead of layout.js so both can coexist
*/
export default function Navbar() {
  return (
    /*
    Navigation bar,
    bg-brand for background color,
    h-22.5 for height,
    flex and items-center for alignment,
    justify-between to space out logo and nav links,
    px-8 for padding,
    fixed at the top so it stays visible while scrolling,
    left-0 right-0 stretches it across the full width,
    z-50 ensures it sits above all page content
    */
    <nav
      className="bg-brand h-22.5 flex items-center justify-between px-8 fixed top-0 left-0 right-0 z-50"
      aria-label="Main navigation"
    >
      {/*
      Logo and HSHAC text on the left side of the navbar,
      flex items-center gap-4 to align them side by side with spacing
      */}
      <div className="flex items-center gap-4">
        {/*
        width and height match the SVG's actual intrinsic dimensions so Next.js
        can calculate the correct aspect ratio,
        h-20 sets the display height via CSS,
        w-auto lets the width scale proportionally from that height,
        next.js gave a warning so width and height were set to real values and then
        overridden with CSS to maintain the aspect ratio without distortion
        */}
        <Image
          src="/HSHAC Black and White.svg"
          alt="Harbor Safe House and Advocacy Center Logo"
          width={573}
          height={514}
          className="h-20 w-auto"
        />
        {/* HSHAC text next to the logo, styled with white color and larger font size */}
        <span className="text-white text-xl">HSHAC</span>
      </div>

      {/*
      Navigation links on the right side of the navbar,
      flex and items-center for alignment,
      gap-10 for spacing between links,
      mr-8 for a small right margin from the edge
      */}
      <div className="flex items-center gap-10 mr-8">
        {/*
        Home link,
        text-white for link color,
        font-bold for emphasis,
        group and relative for styling the hover effect on the span inside
        */}
        <Link href="/" className="text-white font-bold group relative">
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
        </Link>
        <Link href="/about" className="text-white font-bold group relative">
          <span className="block px-4 py-2 rounded-full hover:bg-white hover:text-brand transition-all duration-300">
            About
          </span>
        </Link>
        <Link href="/get-support" className="text-white font-bold group relative">
          <span className="block px-4 py-2 rounded-full hover:bg-white hover:text-brand transition-all duration-300">
            Get Support
          </span>
        </Link>
        <Link href="/give-support" className="text-white font-bold group relative">
          <span className="block px-4 py-2 rounded-full hover:bg-white hover:text-brand transition-all duration-300">
            Give Support
          </span>
        </Link>
        <Link href="/resources" className="text-white font-bold group relative">
          <span className="block px-4 py-2 rounded-full hover:bg-white hover:text-brand transition-all duration-300">
            Resources
          </span>
        </Link>
        {/* Divider between navigation links and language switcher */}
        <div className="h-8 w-px bg-white"></div>
        {/* Language switcher */}
        <a href="#espanol" className="text-white text-sm hover:underline transition-all">
          En Español
        </a>
      </div>
    </nav>
  );
}
