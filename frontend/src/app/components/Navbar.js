"use client";

import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";

/*
Navbar component,
extracted into its own file so layout.js can remain a server component,
server components are required to export metadata (page title, description etc),
"use client" is declared here instead of layout.js so both can coexist
*/
export default function Navbar() {
  /*
  usePathname returns the current URL path (e.g. "/about", "/resources"),
  used to determine which nav link should appear active (white pill style),
  re-runs automatically whenever the user navigates to a new page
  */
  const pathname = usePathname();

  /*
  navClass returns the correct className for a nav link span based on whether
  its href matches the current pathname,
  active link gets the solid white pill style,
  inactive links get the transparent style with a white pill on hover
  */
  const navClass = (href) =>
    pathname === href
      ? "block bg-white text-brand px-4 py-2 rounded-full transition-all duration-300"
      : "block px-4 py-2 rounded-full hover:bg-white hover:text-brand transition-all duration-300";

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
        {/*
        HSHAC abbreviation text next to the logo,
        aria-hidden hides it from screen readers entirely since the logo image
        directly to the left already announces the full organization name via its alt text
        */}
        <span className="text-white text-xl leading-tight">
          Harbor Safe House<br />and Advocacy Center
        </span>
      </div>

      {/*
      Navigation links on the right side of the navbar,
      flex and items-center for alignment,
      gap-10 for spacing between links,
      mr-8 for a small right margin from the edge
      added replace which calls history.replaceState(), which pops recent history entries.
      */}
      <div className="flex items-center gap-10 mr-8">
        <Link replace href="/" className="text-white font-bold group relative">
          <span className={navClass("/")}>Home</span>
        </Link>
        <Link replace href="/about" className="text-white font-bold group relative">
          <span className={navClass("/about")}>About</span>
        </Link>
        <Link replace href="/get-support" className="text-white font-bold group relative">
          <span className={navClass("/get-support")}>Get Support</span>
        </Link>
        <Link replace href="/give-support" className="text-white font-bold group relative">
          <span className={navClass("/give-support")}>Give Support</span>
        </Link>
        <Link replace href="/resources" className="text-white font-bold group relative">
          <span className={navClass("/resources")}>Resources</span>
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
