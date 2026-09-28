import Link from "next/link";
import { PlusIcon } from "@/components/portal/icons";
import { SessionNotice } from "@/components/portal/PortalHeader";

// TODO: replace with a real draft query once drafts are persisted server-side, e.g.:
//   const draft = await db.assessment.findFirst({
//     where: { officerId: session.officerId, status: "DRAFT" },
//     orderBy: { updatedAt: "desc" },
//   });
async function getOpenDraft() {
  return null;
}

const SESSION_TIMEOUT_MINUTES = 15;

export default async function PoliceLandingPage() {
  const draft = await getOpenDraft();

  return (
    <main className="mx-auto flex max-w-250 flex-col gap-7 px-5 py-8 md:px-14 md:py-12">

      <div className="flex items-end justify-between">
        <h1 className="font-montserrat text-2xl font-bold md:text-[32px]">
          What do you need to do?
        </h1>
        {/* Desktop only — on mobile this appears below the card stack */}
        <span className="hidden md:flex">
          <SessionNotice minutes={SESSION_TIMEOUT_MINUTES} />
        </span>
      </div>

      <div className="grid gap-5 md:grid-cols-3">

        {/* Primary action — start a new assessment */}
        <Link
          href="/police/portal"
          className="flex min-h-55 flex-col justify-end gap-4 rounded-[18px] bg-[#5C0F8B] p-6 text-white
                     transition-colors hover:bg-[#4C0B74]
                     focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#5C0F8B]
                     md:col-span-2 md:min-h-70 md:p-9"
        >
          <span className="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/15 p-4">
            <PlusIcon size={32} />
          </span>
          <span className="font-montserrat text-[26px] font-bold leading-tight md:text-[34px]">
            Start New Lethality Assessment
          </span>
          <span className="text-[17px] text-[#EDE3F5]">
            Opens a blank LAP screening form
          </span>
        </Link>

        <div className="flex flex-col gap-5">

          {/* Draft / no-draft card */}
          {draft ? (
            <section
              aria-labelledby="draft-heading"
              className="flex grow flex-col gap-3 rounded-2xl border border-[#DDD7E6] bg-white p-5.5"
            >
              <div className="flex items-center justify-between gap-2">
                <h2 id="draft-heading" className="text-[17px] font-bold">
                  Unfinished draft
                </h2>
                <span className="rounded-full bg-[#FFF1D6] px-2.5 py-1 text-[13px] font-bold text-[#7A4A00]">
                  Draft
                </span>
              </div>
              <p className="text-[15px] text-[#5A5566]">
                Case {draft.caseNumber} &middot; started {draft.startedAt}
              </p>
              <div className="grow" />
              <div className="flex gap-2.5">
                <Link
                  href={`/police/assessments/${draft.id}`}
                  className="flex h-12 grow items-center justify-center rounded-[10px] bg-[#231A33] text-base font-bold text-white
                             transition-colors hover:bg-[#180F26]
                             focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#231A33]"
                >
                  Resume
                </Link>
                {/* TODO: wire to a server action that soft-deletes the draft */}
                <button
                  type="button"
                  className="h-12 rounded-[10px] border border-[#C9C1D6] px-4 text-base font-semibold
                             transition-colors hover:bg-[#F4F2F7]
                             focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#231A33]"
                >
                  Discard
                </button>
              </div>
            </section>
          ) : (
            <section className="flex grow flex-col justify-center gap-2 rounded-2xl border border-dashed border-[#C9C1D6] bg-white p-5.5">
              <h2 className="text-[17px] font-bold">No unfinished drafts</h2>
              <p className="text-[15px] text-[#5A5566]">
                A screening you leave part-way through will be saved here.
              </p>
            </section>
          )}

        </div>
      </div>

      {/* Mobile only — session notice sits below the card stack where it's easy to read */}
      <span className="md:hidden">
        <SessionNotice minutes={SESSION_TIMEOUT_MINUTES} />
      </span>

      {/* Pointer to past records */}
      <section className="flex flex-col gap-4 rounded-2xl border border-[#DDD7E6] bg-white px-5.5 py-5.5 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-[17px] font-bold">Looking for a past assessment?</h2>
          <p className="text-[15px] text-[#5A5566]">
            Records are in My Assessments. Each view is logged with your name and a timestamp.
          </p>
        </div>
        <Link
          href="/police/assessments"
          className="flex h-12 shrink-0 items-center justify-center rounded-[10px] border border-[#C9C1D6] px-5 text-[15px] font-bold
                     transition-colors hover:bg-[#F4F2F7]
                     focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#231A33]"
        >
          Go to My Assessments
        </Link>
      </section>

    </main>
  );
}
