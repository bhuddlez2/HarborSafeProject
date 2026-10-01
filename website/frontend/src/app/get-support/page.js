import Link from "next/link";
import HashHighlight from "./HashHighlight";
import CrisisHotlineStrip from "../components/CrisisHotlineStrip";

// Browser tab title and search description for this page (overrides the default in layout.js)
export const metadata = {
  title: "Get Support | Harbor Safe House & Advocacy Center",
  description:
    "Free, confidential services for survivors of domestic violence and sexual assault in Cleveland, TN: emergency shelter, crisis counseling, support groups, court advocacy, and community education.",
};

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

          <span className="inline-block text-xs font-semibold tracking-widest uppercase bg-purple-100 text-brand px-3 py-1 rounded-full mb-4">How we help</span>
          <h2 className="text-3xl font-semibold text-brand mb-3">Ways we can support you</h2>
          <p className="text-gray-600 leading-relaxed mb-16 max-w-xl">
            No matter where you are in your journey, our advocates are here to walk alongside you.
          </p>

          {/*
          Service cards,
          each card's width subtracts its share of the gap-x-4 (1rem) gutters,
          cards carry their own p-6 padding so the service-card :target highlight
          (globals.css) has room around the content when a home page link lands on one
          */}
          <div className="flex flex-wrap justify-center gap-x-4 gap-y-8">

            {/*
            Emergency Shelter,
            id="shelter" is the target of the home page's Emergency Shelter "Learn more" link,
            scroll-mt-28 clears the fixed navbar with a little breathing room
            */}
            <div id="shelter" data-highlight="shelter" className="service-card scroll-mt-28 rounded-2xl p-6 w-full md:w-[calc((100%_-_1rem)/2)] lg:w-[calc((100%_-_2rem)/3)] flex flex-col items-center text-center">
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
            </div>

            {/*
            Crisis Counseling, id="counseling" is the scroll target of the home page's Counseling & Advocacy "Learn more" link,
            data-highlight="counseling" (shared with Court Advocacy) marks every card that link highlights
            */}
            <div id="counseling" data-highlight="counseling" className="service-card scroll-mt-28 rounded-2xl p-6 w-full md:w-[calc((100%_-_1rem)/2)] lg:w-[calc((100%_-_2rem)/3)] flex flex-col items-center text-center">
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
            </div>

          </div>

          {/*
          One contact line for every service above, replacing per-card footers that mostly
          repeated the same thing; court advocacy and community education keep their office number
          */}
          <p className="mt-16 text-center text-gray-600 leading-relaxed max-w-2xl mx-auto">
            To ask about any of these services, call{" "}
            <a href="tel:423-476-3886" className="whitespace-nowrap font-semibold text-brand hover:underline">(423) 476-3886</a>{" "}
            or text{" "}
            <a href="sms:423-715-9614" className="whitespace-nowrap font-semibold text-brand hover:underline">(423) 715-9614</a>{" "}
            any time, or{" "}
            <Link replace href="/contact" className="font-semibold text-brand hover:underline">submit a contact form</Link>.
            For court advocacy or community education, you can also{" "}
            <a href="tel:423-889-1479" className="font-semibold text-brand hover:underline">call</a> or{" "}
            <a href="sms:423-889-1479" className="font-semibold text-brand hover:underline">text</a> our office at{" "}
            <a href="tel:423-889-1479" className="whitespace-nowrap font-semibold text-brand hover:underline">(423) 889-1479</a>.
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
            <a
              href="tel:423-476-3886"
              className="border-2 border-brand text-brand px-8 py-3 rounded-lg font-semibold hover:bg-brand hover:text-white transition-all"
            >
              Call (423) 476-3886
            </a>
          </div>
        </div>
      </section>

    </main>
  );
}
