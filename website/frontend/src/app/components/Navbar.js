"use client";

import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";

const NAV_LINKS = [
  { href: "/", label: "Home" },
  { href: "/about", label: "About" },
  { href: "/get-support", label: "Get Support" },
  { href: "/give-support", label: "Give Support" },
  { href: "/resources", label: "Resources" },
  { href: "/events", label: "Events" },
];

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
  /*
  next.config.mjs sets trailingSlash: true, so pathname comes back as "/about/" while
  NAV_LINKS use "/about"; strip the trailing slash (except on "/") so they compare equal
  */
  const pathname = usePathname().replace(/(.)\/$/, "$1");

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
    <div className="on-dark fixed top-0 left-0 right-0 z-50">
      <nav
        className="bg-brand h-22.5 flex items-center justify-between px-4 md:px-12 lg:px-6 xl:px-10 relative"
        aria-label="Main navigation"
      >
        {/* Logo image on the left; clicking routes to home, shrink-0 so the nav links can't squeeze it */}
        <Link replace href="/" className="flex items-center gap-4 shrink-0">
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
          {/*
          Text appears on desktop only, whitespace-nowrap so each line stays on one line,
          the full name and program line only show from xl (1280px) up; between lg and xl the
          nav links leave too little room, so only "HSHAC" shows there
          */}
          <div className="hidden lg:flex flex-col leading-tight whitespace-nowrap">
            <span className="text-white text-xl font-bold">HSHAC</span>
            <span className="hidden xl:block text-white/90 text-sm">Harbor Safe House &amp; Advocacy Center</span>
            <span className="hidden xl:block text-white/90 text-xs">A Program of the Family Resource Agency</span>
          </div>
        </Link>

        {/* HSHAC text centered on mobile only */}
        <span className="lg:hidden absolute left-1/2 -translate-x-1/2 text-white text-xl font-bold pointer-events-none">
          HSHAC
        </span>

        {/*
        Desktop nav links; hidden on small screens, visible from lg breakpoint up,
        the pills' own px-4 provides most of the spacing, so the gap between them is small
        (gap-1, gap-2 from xl), which leaves room for the full org name beside the logo
        */}
        <div className="hidden lg:flex items-center gap-1 xl:gap-2">
          {NAV_LINKS.map((link) => (
            <Link key={link.href} replace href={link.href} className="text-white font-bold whitespace-nowrap rounded-full">
              <span className={navClass(link.href)}>{link.label}</span>
            </Link>
          ))}
          {/* Divider between navigation links and language switcher */}
          <div className="h-8 w-px bg-white mx-2 xl:mx-3"></div>
          {/* Language switcher */}
          <a className="text-white text-sm hover:underline transition-all">
            En Español
          </a>
        </div>

        {/*
        Hamburger button — visible only on small screens (lg:hidden),
        toggles menuOpen state to show/hide the mobile dropdown,
        aria-expanded communicates the open/closed state to screen readers
        */}
        <button
          className="lg:hidden text-white p-2"
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
        <div className="lg:hidden bg-brand border-t border-purple-700 flex flex-col px-6 py-4 gap-2">
          {NAV_LINKS.map((link) => (
            <Link
              key={link.href}
              replace
              href={link.href}
              className="text-white font-bold rounded-full"
              onClick={() => setMenuOpen(false)}
            >
              <span className={navClass(link.href)}>{link.label}</span>
            </Link>
          ))}
          {/* Divider */}
          <div className="h-px bg-purple-700 my-1"></div>
          {/* Language switcher */}
          <a className="text-white text-sm hover:underline transition-all">
            En Español
          </a>
        </div>
      )}
    </div>
  );
}
