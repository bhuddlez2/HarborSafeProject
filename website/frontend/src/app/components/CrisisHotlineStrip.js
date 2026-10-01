/*
CrisisHotlineStrip, the "24/7 Confidential Crisis Hotline" bar that sits under each page's hero,
bg-purple-50 with a border-b border-purple-100 separator,
flex-wrap so the label, numbers, and note stack on small screens,
gap-y-3 keeps the wrapped rows close together while gap-x-8 spaces them on one line,
the numbers are whitespace-nowrap so "(423) 476-3886" never breaks mid-number, and the
Call/Text pair stacks (divider hidden) below sm where both won't fit on one line
*/
export default function CrisisHotlineStrip() {
  return (
    <div className="flex items-center justify-center gap-x-8 gap-y-3 px-8 py-5 bg-purple-50 border-b border-purple-100 flex-wrap text-center">
      <span className="text-sm tracking-widest text-purple-700">24/7 Confidential Crisis Hotline</span>
      <div className="flex flex-col sm:flex-row items-center gap-1 sm:gap-5">
        <a href="tel:423-476-3886" className="whitespace-nowrap text-lg font-semibold text-brand hover:underline transition-all">Call (423) 476-3886</a>
        <span className="hidden sm:inline text-purple-300" aria-hidden="true">|</span>
        <a href="sms:423-715-9614" className="whitespace-nowrap text-lg font-semibold text-brand hover:underline transition-all">Text (423) 715-9614</a>
      </div>
      <span className="text-sm text-purple-700">Free &nbsp;·&nbsp; Confidential &nbsp;·&nbsp; 24 hours a day</span>
    </div>
  );
}
