/*
CrisisHotlineStrip, the "24/7 Confidential Crisis Hotline" bar that sits under each page's hero,
bg-purple-50 with a border-b border-purple-100 separator,
sticky top-22.5 pins it right under the fixed navbar (h-22.5) once the hero scrolls away, so
Call and Text stay one tap away on every page (B10), z-40 keeps it under the navbar's z-50
(and its mobile menu) and far under the Safe Exit button's z-100, which sits at the bottom anyway,
on phones it is one compact row, just Call | Text in text-sm, so it covers ~45px rather than the
four stacked lines (~150px) it used to; the label and note show from sm up,
flex-wrap lets the label, numbers, and note wrap onto extra rows on in-between widths,
the links are whitespace-nowrap so "Call (423) 476-3886" never breaks mid-number,
anchors on pages with this strip need scroll-mt-44 (navbar + strip) to land below it
*/
import PhoneLink from "./PhoneLink";
import { CRISIS_CALL, CRISIS_TEXT } from "../lib/phone";

export default function CrisisHotlineStrip() {
  return (
    <div className="sticky top-22.5 z-40 flex items-center justify-center gap-x-8 gap-y-2 px-4 sm:px-8 py-3 sm:py-4 bg-purple-50 border-b border-purple-100 flex-wrap text-center">
      <span className="hidden sm:inline text-sm tracking-widest text-purple-700">24/7 Confidential Crisis Hotline</span>
      <div className="flex flex-row items-center gap-3 sm:gap-5">
        <PhoneLink number={CRISIS_CALL} prefix="Call " className="tap-target whitespace-nowrap text-sm sm:text-lg font-semibold text-brand hover:underline transition-all" />
        <span className="text-purple-300" aria-hidden="true">|</span>
        <PhoneLink number={CRISIS_TEXT} sms prefix="Text " className="tap-target whitespace-nowrap text-sm sm:text-lg font-semibold text-brand hover:underline transition-all" />
      </div>
      <span className="hidden sm:inline text-sm text-purple-700">Free &nbsp;·&nbsp; Confidential &nbsp;·&nbsp; 24 hours a day</span>
    </div>
  );
}
