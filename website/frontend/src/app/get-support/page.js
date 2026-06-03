export default function GetSupport() {
  return (
    <main>

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
      <div className="flex items-center justify-center gap-8 px-8 py-5 bg-purple-50 border-b border-purple-100 flex-wrap">
        <span className="text-sm tracking-widest text-purple-700">24/7 Confidential Crisis Hotline</span>
        <div className="flex items-center gap-5">
          <a href="tel:423-476-3886" className="text-lg font-semibold text-brand hover:underline transition-all">Call (423) 476-3886</a>
          <span className="text-purple-300">|</span>
          <a href="sms:423-715-9614" className="text-lg font-semibold text-brand hover:underline transition-all">Text (423) 715-9614</a>
        </div>
        <span className="text-sm text-purple-700">Free &nbsp;·&nbsp; Confidential &nbsp;·&nbsp; 24 hours a day</span>
      </div>

      {/* Services */}
      <section className="py-20 px-4 bg-white">
        <div className="max-w-7xl mx-auto">

          <span className="inline-block text-xs font-semibold tracking-widest uppercase bg-purple-100 text-brand px-3 py-1 rounded-full mb-4">How we help</span>
          <h2 className="text-3xl font-semibold text-brand mb-3">Ways we can support you</h2>
          <p className="text-gray-600 leading-relaxed mb-10 max-w-xl">
            No matter where you are in your journey, our advocates are here to walk alongside you.
          </p>

          <div className="grid md:grid-cols-4 gap-10">

            {/* Crisis Counseling */}
            <div className="flex flex-col items-center text-center">
              <div className="w-16 h-16 rounded-full bg-purple-100 flex items-center justify-center mb-6">
                <svg viewBox="0 0 24 24" className="w-7 h-7 stroke-brand stroke-2 fill-none">
                  {/* message-circle icon, feathericons.com */}
                  <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                </svg>
              </div>
              <p className="text-base font-semibold text-brand mb-3">Crisis Counseling</p>
              <p className="text-sm text-gray-600 leading-relaxed mb-4">
                Trauma-informed counseling through our hotline, in-person sessions, and support groups. We help with safety planning, goal-setting, court navigation, and building a future free from abuse.
              </p>
              <p className="text-xs text-gray-500 mt-auto">To schedule, call <a href="tel:423-476-3886" className="text-brand hover:underline">(423) 476-3886</a> or submit a contact form.</p>
            </div>

            {/* Support Groups */}
            <div className="flex flex-col items-center text-center">
              <div className="w-16 h-16 rounded-full bg-purple-100 flex items-center justify-center mb-6">
                <svg viewBox="0 0 24 24" className="w-7 h-7 stroke-brand stroke-2 fill-none">
                  {/* users icon, feathericons.com */}
                  <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 00-3-3.87" /><path d="M16 3.13a4 4 0 010 7.75" />
                </svg>
              </div>
              <p className="text-base font-semibold text-brand mb-3">Support Groups</p>
              <p className="text-sm text-gray-600 leading-relaxed mb-4">
                Weekly safe, confidential sessions for women and children to learn about domestic violence, build self-esteem, set goals, and create safety plans. Free childcare available with advance notice.
              </p>
              <p className="text-xs text-gray-500 mt-auto">For details, submit an online contact form.</p>
            </div>

            {/* Court Advocacy */}
            <div className="flex flex-col items-center text-center">
              <div className="w-16 h-16 rounded-full bg-purple-100 flex items-center justify-center mb-6">
                <svg viewBox="0 0 24 24" className="w-7 h-7 stroke-brand stroke-2 fill-none">
                  {/* briefcase icon, feathericons.com */}
                  <rect x="2" y="7" width="20" height="14" rx="2" ry="2" /><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16" />
                </svg>
              </div>
              <p className="text-base font-semibold text-brand mb-3">Court Advocacy</p>
              <p className="text-sm text-gray-600 leading-relaxed mb-4">
                Help with filing for protection orders, preparing for court appearances, and connecting with legal referrals. We walk with you through the process every step of the way.
              </p>
              <p className="text-xs text-gray-500 mt-auto">For more info, <a href="tel:423-889-1479" className="text-brand hover:underline">call</a> or <a href="sms:423-889-1479" className="text-brand hover:underline">text</a> <a href="tel:423-889-1479" className="text-brand hover:underline">(423) 889-1479</a>. Note: we do not provide legal advice or representation.</p>
            </div>

            {/* Community Education */}
            <div className="flex flex-col items-center text-center">
              <div className="w-16 h-16 rounded-full bg-purple-100 flex items-center justify-center mb-6">
                <svg viewBox="0 0 24 24" className="w-7 h-7 stroke-brand stroke-2 fill-none">
                  {/* book-open icon, feathericons.com */}
                  <path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z" /><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z" />
                </svg>
              </div>
              <p className="text-base font-semibold text-brand mb-3">Community Education</p>
              <p className="text-sm text-gray-600 leading-relaxed mb-4">
                Trauma-informed presentations for schools, clubs, churches, businesses, and organizations to help communities recognize, respond to, and prevent abuse.
              </p>
              <p className="text-xs text-gray-500 mt-auto">To schedule, <a href="tel:423-889-1479" className="text-brand hover:underline">call</a> or <a href="sms:423-889-1479" className="text-brand hover:underline">text</a> <a href="tel:423-889-1479" className="text-brand hover:underline">(423) 889-1479</a> or submit a contact form.</p>
            </div>

          </div>
        </div>
      </section>

      {/* Section divider */}
      <div className="w-full h-1 bg-brand"></div>

      {/* CTA band */}
      <section className="py-20 px-4 bg-purple-100 text-center">
        <div className="max-w-2xl mx-auto">
          <h2 className="text-3xl font-semibold text-brand mb-4">We&apos;re here when you&apos;re ready.</h2>
          <p className="text-gray-700 leading-relaxed mb-8">
            Reaching out can feel hard. Whether you call, text, or fill out a form, we will meet you where you are — no judgment, no pressure.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <a
              href="https://docs.google.com/forms/d/17tp9D1rWlMVuzSXBWFfrY16Q1xYZgXn6fI9hGEl2tg8/edit"
              target="_blank"
              rel="noopener noreferrer"
              className="bg-brand text-white px-8 py-3 rounded-lg font-semibold hover:bg-purple-800 transition-all"
            >
              Contact Us
            </a>
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
