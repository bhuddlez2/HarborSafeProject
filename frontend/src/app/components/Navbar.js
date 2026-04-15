"use client";

import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";

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
  menuOpen tracks whether the mobile dropdown is visible,
  toggled by the hamburger button, closed automatically when a link is clicked
  */
  const [menuOpen, setMenuOpen] = useState(false);

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
    Navigation bar wrapper,
    relative so the mobile dropdown can be positioned absolutely below it,
    fixed at the top so it stays visible while scrolling,
    z-50 ensures it sits above all page content
    */
    <div className="fixed top-0 left-0 right-0 z-50">
      <nav
        className="bg-brand h-22.5 flex items-center justify-between px-12"
        aria-label="Main navigation"
      >
        {/* Logo and HSHAC text on the left side of the navbar — clicking routes to home */}
        <Link replace href="/" className="flex items-center gap-4">
          {/*
          width and height match the SVG's actual intrinsic dimensions so Next.js
          can calculate the correct aspect ratio,
          h-20 sets the display height via CSS,
          w-auto lets the width scale proportionally from that height
          */}
          <Image
            src="/HSHAC Black and White.svg"
            alt="Harbor Safe House and Advocacy Center Logo"
            width={573}
            height={514}
            className="h-20 w-auto"
          />
          {/* HSHAC abbreviation and full name stacked */}
          <div className="flex flex-col leading-tight">
            <span className="text-white text-xl font-bold">HSHAC</span>
            <span className="text-white/90 text-sm">Harbor Safe House and Advocacy Center</span>
          </div>
        </Link>

        {/*
        Desktop nav links — hidden on small screens, visible from md breakpoint up,
        gap-10 for spacing between links, mr-8 for a small right margin
        */}
        <div className="hidden md:flex items-center gap-10 mr-8">
          <Link replace href="/" className="text-white font-bold">
            <span className={navClass("/")}>Home</span>
          </Link>
          <Link replace href="/about" className="text-white font-bold">
            <span className={navClass("/about")}>About</span>
          </Link>
          <Link replace href="/get-support" className="text-white font-bold">
            <span className={navClass("/get-support")}>Get Support</span>
          </Link>
          <Link replace href="/give-support" className="text-white font-bold">
            <span className={navClass("/give-support")}>Give Support</span>
          </Link>
          <Link replace href="/resources" className="text-white font-bold">
            <span className={navClass("/resources")}>Resources</span>
          </Link>
          {/* Divider between navigation links and language switcher */}
          <div className="h-8 w-px bg-white"></div>
          {/* Language switcher */}
          <a href="#espanol" className="text-white text-sm hover:underline transition-all">
            En Español
          </a>
        </div>

        {/*
        Hamburger button — visible only on small screens (md:hidden),
        toggles menuOpen state to show/hide the mobile dropdown,
        aria-expanded communicates the open/closed state to screen readers
        */}
        <button
          className="md:hidden text-white p-2"
          onClick={() => setMenuOpen((prev) => !prev)}
          aria-label="Toggle navigation menu"
          aria-expanded={menuOpen}
        >
          {menuOpen ? (
            /* X icon when menu is open, feathericons.com */
            <svg viewBox="0 0 24 24" className="w-7 h-7 stroke-white stroke-2 fill-none">
              <line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          ) : (
            /* Hamburger icon when menu is closed, feathericons.com */
            <svg viewBox="0 0 24 24" className="w-7 h-7 stroke-white stroke-2 fill-none">
              <line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="18" x2="21" y2="18" />
            </svg>
          )}
        </button>
      </nav>

      {/*
      Mobile dropdown menu,
      only renders when menuOpen is true,
      bg-brand matches the navbar background,
      flex flex-col for a vertical list of links,
      px-6 py-4 for internal padding,
      border-t border-purple-700 for a subtle separator from the navbar above
      */}
      {menuOpen && (
        <div className="md:hidden bg-brand border-t border-purple-700 flex flex-col px-6 py-4 gap-2">
          <Link replace href="/" className="text-white font-bold" onClick={() => setMenuOpen(false)}>
            <span className={navClass("/")}>Home</span>
          </Link>
          <Link replace href="/about" className="text-white font-bold" onClick={() => setMenuOpen(false)}>
            <span className={navClass("/about")}>About</span>
          </Link>
          <Link replace href="/get-support" className="text-white font-bold" onClick={() => setMenuOpen(false)}>
            <span className={navClass("/get-support")}>Get Support</span>
          </Link>
          <Link replace href="/give-support" className="text-white font-bold" onClick={() => setMenuOpen(false)}>
            <span className={navClass("/give-support")}>Give Support</span>
          </Link>
          <Link replace href="/resources" className="text-white font-bold" onClick={() => setMenuOpen(false)}>
            <span className={navClass("/resources")}>Resources</span>
          </Link>
          {/* Divider */}
          <div className="h-px bg-purple-700 my-1"></div>
          {/* Language switcher */}
          <a href="#espanol" className="text-white text-sm hover:underline transition-all">
            En Español
          </a>
        </div>
      )}
    </div>
  );
}
