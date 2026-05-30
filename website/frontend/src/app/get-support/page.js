export default function GetSupport() {
  return (
    <main>

      <section className="bg-purple-950 px-4 pt-20 pb-16 text-center">
        <p className="text-xs font-semibold tracking-widest uppercase text-purple-300 mb-3">
          Harbor Safe House &amp; Advocacy Center
        </p>
        <h1 className="text-5xl md:text-6xl font-semibold text-white mb-4">You Are Not Alone</h1>
        <p className="text-white/80 text-lg max-w-xl mx-auto leading-relaxed">
          Free, confidential support is available 24 hours a day for anyone experiencing domestic violence or sexual assault.
        </p>
      </section>

      <div className="flex items-center justify-center gap-8 px-8 py-5 bg-purple-50 border-b border-purple-100 flex-wrap">
        <span className="text-sm tracking-widest text-purple-700">24/7 Confidential Crisis Hotline</span>
        <div className="flex items-center gap-5">
          <a href="tel:423-476-3886" className="text-lg font-semibold text-brand hover:underline transition-all">Call (423) 476-3886</a>
          <span className="text-purple-300">|</span>
          <a href="sms:423-715-9614" className="text-lg font-semibold text-brand hover:underline transition-all">Text (423) 715-9614</a>
        </div>
        <span className="text-sm text-purple-700">Free &nbsp;·&nbsp; Confidential &nbsp;·&nbsp; 24 hours a day</span>
      </div>

    </main>
  );
}
