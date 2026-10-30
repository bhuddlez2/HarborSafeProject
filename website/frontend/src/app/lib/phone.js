/*
Phone numbers shown on the website: one display format and one link format,
used through components/PhoneLink.js, so every number reads and dials the same way.

Display:
  local       (423) 476-3886
  toll-free   1-800-799-7233 (the style national hotlines advertise themselves in)
  lettered    1-800-950-NAMI (6264), kept as the organisation brands it
  short       988, 911, 88788, left as written
Links dial +1 and the ten digits (tel:+14234763886), which every phone understands.

This is display only. What a visitor types into a form is formatted by
formatPhoneInput() in validators.js, to match how the portal stores numbers.
*/

// Harbor Safe's own numbers, so they are written once
export const CRISIS_CALL = "423-476-3886";
export const CRISIS_TEXT = "423-715-9614";

const TOLL_FREE_AREA_CODES = new Set(["800", "833", "844", "855", "866", "877", "888"]);

// The ten digits of a North American number, or null for short codes like 988
function tenDigits(raw) {
  let digits = String(raw).replace(/\D/g, "");
  if (digits.length === 11 && digits.startsWith("1")) digits = digits.slice(1);
  return digits.length === 10 ? digits : null;
}

export function formatPhone(raw) {
  const text = String(raw).trim();

  // Lettered numbers (800-950-NAMI (6264)) keep their letters, with the 1- in front
  if (/[a-z]/i.test(text)) return text.startsWith("1-") ? text : `1-${text}`;

  const digits = tenDigits(text);
  if (!digits) return text;

  const [area, exchange, line] = [digits.slice(0, 3), digits.slice(3, 6), digits.slice(6)];
  return TOLL_FREE_AREA_CODES.has(area)
    ? `1-${area}-${exchange}-${line}`
    : `(${area}) ${exchange}-${line}`;
}

export function phoneHref(raw, sms = false) {
  const digits = tenDigits(raw);
  const target = digits ? `+1${digits}` : String(raw).replace(/\D/g, "");
  return `${sms ? "sms" : "tel"}:${target}`;
}
