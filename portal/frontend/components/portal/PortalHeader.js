"use client";

// Phone-only header. On desktop the officer's identity and sign-out live in the
// sidebar, so this whole block is hidden above the `md` breakpoint.

import { LockIcon } from "@/components/portal/icons";

export default function PortalHeader({ officer }) {
  return (
    <header
      className="bg-[#231A33] px-5 pb-6 pt-5 text-white md:hidden"
      style={{ paddingTop: "calc(1.25rem + env(safe-area-inset-top, 0px))" }}
    >
      <div className="flex items-center justify-between gap-3">
        <p className="font-montserrat text-[15px] font-bold tracking-[0.04em] uppercase">
          HSHAC Officer Portal
        </p>
        <form action="/api/auth/logout" method="post">
          <button
            type="submit"
            aria-label="Sign out"
            className="flex h-11 w-11 items-center justify-center rounded-[10px] border border-[#4A3E5E]
                       focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
          >
            {/* Log-out arrow icon */}
            <svg
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
            >
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <path d="M16 17l5-5-5-5" />
              <path d="M21 12H9" />
            </svg>
          </button>
        </form>
      </div>

      <div className="mt-3.5">
        <p className="text-xl font-bold">{officer.name}</p>
        <p className="text-sm text-[#D6CFE2]">
          Badge {officer.badge} &middot; {officer.agency}
        </p>
      </div>
    </header>
  );
}


export function SessionNotice({ minutes }) {
  return (
    <p className="flex items-center gap-2 text-[13px] text-[#5A5566]">
      <LockIcon size={16} />
      Session locks after {minutes} minutes of inactivity
    </p>
  );
}
