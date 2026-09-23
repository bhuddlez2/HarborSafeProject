"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  HomeIcon,
  ClipboardIcon,
  SearchIcon,
  UserIcon,
} from "@/components/portal/icons";

const NAV_ITEMS = [
  { href: "/police",              label: "Home",           Icon: HomeIcon      },
  { href: "/police/assessments",  label: "My Assessments", Icon: ClipboardIcon },
  { href: "/police/search",       label: "Search Records", Icon: SearchIcon    },
  { href: "/police/account",      label: "Account",        Icon: UserIcon      },
];

export default function PortalSidebar({ officer }) {
  const pathname = usePathname();

  const isActive = (href) =>
    href === "/police" ? pathname === href : pathname.startsWith(href);

  return (
    <>
      {/* Desktop: left rail */}
      <nav
        aria-label="Officer portal"
        className="hidden md:flex w-[248px] shrink-0 flex-col gap-1.5 bg-[#231A33] p-4 pt-7 text-white"
      >
        <p className="px-3 pb-6 font-montserrat text-[15px] font-bold tracking-[0.04em] uppercase">
          Officer Portal
        </p>

        {NAV_ITEMS.map(({ href, label, Icon }) => {
          const active = isActive(href);
          return (
            <Link
              key={href}
              href={href}
              aria-current={active ? "page" : undefined}
              className={`flex h-12 items-center gap-3 rounded-[10px] px-3.5 text-[15px] transition-colors
                focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white
                ${active
                  ? "bg-[#5C0F8B] font-bold text-white"
                  : "font-semibold text-[#D6CFE2] hover:bg-white/10 hover:text-white"
                }`}
            >
              <Icon />
              <span>{label}</span>
            </Link>
          );
        })}

        <div className="grow" />

        <div className="border-t border-[#4A3E5E] px-3 pt-4">
          <p className="text-[15px] font-bold">{officer.name}</p>
          <p className="text-[13px] text-[#D6CFE2]">
            Badge {officer.badge} &middot; {officer.agency}
          </p>
        </div>

        <form action="/api/auth/logout" method="post" className="mt-3">
          <button
            type="submit"
            className="h-11 w-full rounded-[10px] border border-[#4A3E5E] text-sm font-semibold text-white
                       hover:bg-white/10 focus-visible:outline focus-visible:outline-2
                       focus-visible:outline-offset-2 focus-visible:outline-white transition-colors"
          >
            Sign out
          </button>
        </form>
      </nav>

      {/* Mobile: bottom hot bar */}
      <nav
        aria-label="Officer portal"
        className="fixed inset-x-0 bottom-0 z-20 grid grid-cols-4 border-t border-[#DDD7E6] bg-white md:hidden"
        style={{ paddingBottom: "env(safe-area-inset-bottom, 0px)" }}
      >
        {NAV_ITEMS.map(({ href, label, Icon }) => {
          const active = isActive(href);
          return (
            <Link
              key={href}
              href={href}
              aria-current={active ? "page" : undefined}
              className={`flex min-h-[76px] flex-col items-center justify-center gap-1 text-xs transition-colors
                ${active ? "font-bold text-[#5C0F8B]" : "font-semibold text-[#5A5566]"}`}
            >
              <Icon size={24} />
              <span>{label === "My Assessments" ? "Assessments" : label}</span>
            </Link>
          );
        })}
      </nav>
    </>
  );
}
