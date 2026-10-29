import Link from "next/link";
import HashHighlight from "./HashHighlight";
import CrisisHotlineStrip from "../components/CrisisHotlineStrip";
import PhoneLink from "../components/PhoneLink";
import { CRISIS_CALL } from "../lib/phone";
import { CIVILIAN_ASSESSMENT_URL } from "../lib/portal";

// Browser tab title and search description for this page (overrides the default in layout.js)
export const metadata = {
  title: "Get Support | Harbor Safe House & Advocacy Center",
  description:
    "Free, confidential services for survivors of domestic violence and sexual assault in Cleveland, TN: emergency shelter, crisis counseling, support groups, court advocacy, and community education.",
};

// Opens Request Resources with these resource types ticked, matched by name
// (ContactContent). A name staff haven't added yet just ticks nothing.
function RequestLink({ needs }) {
  const query = new URLSearchParams(needs.map((need) => ["need", need]));
  return (
    <Link replace href={`/contact/?${query}`} className="mt-auto pt-6 text-sm font-semibold text-brand hover:underline">
      Request this service →
    </Link>
  );
}

export default function GetSupport() {
  return (
    <main>
      <HashHighlight />

      {/* Hero */}
      <section className="bg-purple-950 px-4 pt-20 pb-16 text-center">
        <p className="text-xs font-semibold tracking-widest uppercase text-purple-300 mb-3">
          Harbor Safe House &amp; Advocacy Center
        </p>
        <h1 className="text-5xl md:text-6xl font-semibold text-white mb-4">You Are Not Alone</h1>
        <p className="text-white/80 text-lg max-w-xl mx-auto leading-relaxed">
          Free, confidential support is available 24 hours a day for anyone experiencing domestic violence or sexual assault.
        </p>
      </section>

      {/* Hotline strip */}
      <CrisisHotlineStrip />

      {/*
      Services
      */}
      <section className="py-32 px-4 bg-white">
        <div className="max-w-7xl mx-auto">

          {/*
          Lethality assessment, first so someone worried about their safety sees it
          before the services (B14), tinted so it stands out from the page (B15)
          */}
          <div className="mb-24 bg-purple-100 border border-purple-200 rounded-2xl p-8 md:p-10">
            {/* max-w-5xl keeps the lines from running too long on wide screens */}
            <div className="max-w-5xl mx-auto flex flex-col items-center text-center">
              <h2 className="text-xl font-semibold text-brand mb-2">Do you have safety concerns?</h2>
              <p className="text-sm text-gray-600 leading-relaxed">
                Our lethality assessment asks 11 yes/no questions that can help show whether a relationship is
                becoming more dangerous. You can answer for yourself or for someone you&apos;re worried about.
                Your answers are sent confidentially to Harbor Safe House.{" "}
                {/* its own line, so it never splits with a lone "call 911." */}
                <strong className="block font-semibold text-gray-900">If you are in immediate danger,{" "}
                  <a href="tel:911" className="whitespace-nowrap text-red-700 hover:underline">call 911</a>.
                </strong>
              </p>
              <a
                href={CIVILIAN_ASSESSMENT_URL}
                className="mt-6 whitespace-nowrap bg-brand text-white px-6 py-3 rounded-lg font-semibold hover:bg-purple-800 transition-all"
              >
                Take the assessment
              </a>
            </div>
          </div>

          {/* Scroll targets for the home page's "Learn more" links (#shelter, #counseling) */}
          <span id="shelter" className="block scroll-mt-28" aria-hidden="true"></span>
          <span id="counseling" className="block scroll-mt-28" aria-hidden="true"></span>

          <div className="text-center mb-16">
            <h2 className="text-3xl font-semibold text-brand mb-3">Ways We Can Support You</h2>
            <p className="text-gray-600 leading-relaxed max-w-xl mx-auto">
              No matter where you are in your journey, our advocates are here to walk alongside you.
            </p>
          </div>

          {/*
          Service cards,
          each card's width subtracts its share of the gap-x-4 (1rem) gutters,
          cards carry their own p-6 padding so the service-card :target highlight
          (globals.css) has room around the content when a home page link lands on one
          */}
          <div className="flex flex-wrap justify-center gap-x-4 gap-y-8">
            <div data-highlight="shelter" className="service-card rounded-2xl p-6 w-full md:w-[calc((100%_-_1rem)/2)] lg:w-[calc((100%_-_2rem)/3)] flex flex-col items-center text-center">
              <div className="w-20 h-20 rounded-full bg-purple-100 flex items-center justify-center mb-8">
                <svg viewBox="0 0 24 24" className="w-8 h-8 stroke-brand stroke-2 fill-none">
                  {/* home icon, feathericons.com */}
                  <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" /><polyline points="9,22 9,12 15,12 15,22" />
                </svg>
              </div>
              <p className="text-lg font-semibold text-brand mb-4">Emergency Shelter</p>
              <p className="text-sm text-gray-600 leading-relaxed">
                Safe, confidential housing for individuals and families escaping dangerous situations. Our advocates can help you plan a safe move and connect you with the support you need while you stay with us.
              </p>
              <RequestLink needs={["Emergency Shelter"]} />
            </div>

            <div data-highlight="counseling" className="service-card rounded-2xl p-6 w-full md:w-[calc((100%_-_1rem)/2)] lg:w-[calc((100%_-_2rem)/3)] flex flex-col items-center text-center">
              <div className="w-20 h-20 rounded-full bg-purple-100 flex items-center justify-center mb-8">
                <svg viewBox="0 0 24 24" className="w-8 h-8 stroke-brand stroke-2 fill-none">
                  {/* message-circle icon, feathericons.com */}
                  <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                </svg>
              </div>
              <p className="text-lg font-semibold text-brand mb-4">Crisis Counseling</p>
              <p className="text-sm text-gray-600 leading-relaxed">
                Trauma-informed counseling through our hotline, in-person sessions, and support groups. We help with safety planning, goal-setting, court navigation, and building a future free from abuse.
              </p>
              <RequestLink needs={["Counseling"]} />
            </div>

            {/* Support Groups */}
            <div className="service-card rounded-2xl p-6 w-full md:w-[calc((100%_-_1rem)/2)] lg:w-[calc((100%_-_2rem)/3)] flex flex-col items-center text-center">
              <div className="w-20 h-20 rounded-full bg-purple-100 flex items-center justify-center mb-8">
                <svg viewBox="0 0 24 24" className="w-8 h-8 stroke-brand stroke-2 fill-none">
                  {/* users icon, feathericons.com */}
                  <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 00-3-3.87" /><path d="M16 3.13a4 4 0 010 7.75" />
                </svg>
              </div>
              <p className="text-lg font-semibold text-brand mb-4">Support Groups</p>
              <p className="text-sm text-gray-600 leading-relaxed">
                Weekly safe, confidential sessions for women and children to learn about domestic violence, build self-esteem, set goals, and create safety plans. Free childcare available with advance notice.
              </p>
              <RequestLink needs={["Support Groups"]} />
            </div>

            {/* Court Advocacy, highlighted along with Crisis Counseling by the home page's Counseling & Advocacy link */}
            <div data-highlight="counseling" className="service-card rounded-2xl p-6 w-full md:w-[calc((100%_-_1rem)/2)] lg:w-[calc((100%_-_2rem)/3)] flex flex-col items-center text-center">
              <div className="w-20 h-20 rounded-full bg-purple-100 flex items-center justify-center mb-8">
                <svg viewBox="0 0 24 24" className="w-8 h-8 stroke-brand stroke-2 fill-none">
                  {/* briefcase icon, feathericons.com */}
                  <rect x="2" y="7" width="20" height="14" rx="2" ry="2" /><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16" />
                </svg>
              </div>
              <p className="text-lg font-semibold text-brand mb-4">Court Advocacy</p>
              <p className="text-sm text-gray-600 leading-relaxed">
                Help with filing for protection orders, preparing for court appearances, and connecting with legal referrals. We walk with you through the process every step of the way.
                Please note: we do not provide legal advice or representation.
              </p>
              <RequestLink needs={["Orders of Protection", "Legal Help"]} />
            </div>

            {/* Community Education */}
            <div className="service-card rounded-2xl p-6 w-full md:w-[calc((100%_-_1rem)/2)] lg:w-[calc((100%_-_2rem)/3)] flex flex-col items-center text-center">
              <div className="w-20 h-20 rounded-full bg-purple-100 flex items-center justify-center mb-8">
                <svg viewBox="0 0 24 24" className="w-8 h-8 stroke-brand stroke-2 fill-none">
                  {/* book-open icon, feathericons.com */}
                  <path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z" /><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z" />
                </svg>
              </div>
              <p className="text-lg font-semibold text-brand mb-4">Community Education</p>
              <p className="text-sm text-gray-600 leading-relaxed">
                Trauma-informed presentations for schools, clubs, churches, businesses, and organizations to help communities recognize, respond to, and prevent abuse.
              </p>
              <RequestLink needs={["Community Education"]} />
            </div>

          </div>

          {/* The office line, kept from the removed "Ask about any of these services" box: it's nowhere else on the site */}
          <p className="mt-12 text-center text-sm text-gray-600 leading-relaxed">
            For court advocacy or community education, you can also call or text our office at{" "}
            <PhoneLink number="423-889-1479" className="font-semibold text-brand hover:underline" />.
          </p>
        </div>
      </section>

      {/* Section divider */}
      <div className="w-full h-1 bg-brand"></div>

      {/* CTA band */}
      <section className="py-32 px-4 bg-purple-100 text-center">
        <div className="max-w-2xl mx-auto">
          <h2 className="text-3xl font-semibold text-brand mb-4">We&apos;re here when you&apos;re ready.</h2>
          <p className="text-gray-700 leading-relaxed mb-8">
            Reaching out can feel hard. Whether you call, text, or fill out a form, we will meet you where you are — no judgment, no pressure.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <Link
              replace
              href="/contact"
              className="bg-brand text-white px-8 py-3 rounded-lg font-semibold hover:bg-purple-800 transition-all"
            >
              Contact Us
            </Link>
            <PhoneLink
              number={CRISIS_CALL}
              prefix="Call "
              className="border-2 border-brand text-brand px-8 py-3 rounded-lg font-semibold hover:bg-brand hover:text-white transition-all"
            />
          </div>
        </div>
      </section>

    </main>
  );
}
