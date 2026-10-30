import { formatPhone, phoneHref } from "../lib/phone";

/*
PhoneLink, a tap-to-call (or, with sms, tap-to-text) link for any phone number on the site,
showing it in the site's one format (lib/phone.js),
prefix puts words before the number ("Call " → "Call (423) 476-3886"),
children replace the visible text entirely (e.g. "Call →") while the link still dials the number,
the number itself is whitespace-nowrap so it never breaks across two lines,
any other props (className, aria-label) go straight to the <a>
*/
export default function PhoneLink({ number, sms = false, prefix = "", children, ...props }) {
  return (
    <a href={phoneHref(number, sms)} {...props}>
      {children ?? <>{prefix}<span className="whitespace-nowrap">{formatPhone(number)}</span></>}
    </a>
  );
}

// Numbers written inside a sentence: 423-472-2179, (423) 472-2179 or 1-800-799-7233
const PHONE_IN_TEXT = /((?:1-)?(?:\(\d{3}\) ?|\b\d{3}-)\d{3}-\d{4}\b)/;

/*
withPhoneLinks turns every phone number inside a plain string into a PhoneLink,
for text kept as data (the resources files), where a number sits in a sentence,
anything that isn't a string is returned untouched
*/
export function withPhoneLinks(text, className = "font-semibold text-brand hover:underline") {
  if (typeof text !== "string") return text;

  // split with a capturing group puts each number at the odd indexes
  return text.split(PHONE_IN_TEXT).map((part, i) =>
    i % 2 === 1 ? <PhoneLink key={i} number={part} className={className} /> : part
  );
}
