export function isValidEmail(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

/*
Phone numbers: a full 10-digit North American number, area code included, so
Harbor Safe can always call back. The same rule as the backend's
App\Support\PhoneNumber: punctuation and spaces are ignored, so is a leading
country code 1, and an area code can't start with 0 or 1.
*/
function phoneDigits(value) {
  let digits = String(value ?? "").replace(/\D/g, "");
  if (digits.length === 11 && digits[0] === "1") digits = digits.slice(1);
  return digits;
}

export function isValidPhone(value) {
  return /^[2-9]\d{9}$/.test(phoneDigits(value));
}

/*
Formats a phone box as the person types: (423)555-0100. Built from the digits
alone each time, so deleting a bracket or dash just reflows the number. A
leading 0 or 1 is dropped, since no area code starts with one - that is how a
"1" typed out of habit is ignored. Stops at 10 digits.
*/
export function formatPhoneInput(value) {
  const digits = String(value ?? "").replace(/\D/g, "").replace(/^[01]+/, "").slice(0, 10);

  if (digits.length === 0) return "";
  if (digits.length <= 3) return `(${digits}`;
  if (digits.length <= 6) return `(${digits.slice(0, 3)})${digits.slice(3)}`;
  return `(${digits.slice(0, 3)})${digits.slice(3, 6)}-${digits.slice(6)}`;
}

// Room for a pasted number in any common form ("+1 (423) 555-0100"): the
// browser cuts a paste at maxLength before formatPhoneInput runs, so a tight
// limit would drop digits. formatPhoneInput itself stops at 10 digits.
export const PHONE_INPUT_MAX_LENGTH = 20;

/*
Dates of birth: no later than today and no earlier than 125 years ago. The
same rule as the backend's App\Support\DateOfBirth, which checks it again on
submit; change both together. "Today" is the Eastern day, as on the server,
whatever the visitor's own time zone.
*/
export const DOB_MAX_AGE_YEARS = 125;

const pad = (n) => String(n).padStart(2, "0");

export function dateOfBirthBounds(now = new Date()) {
  // en-CA formats as YYYY-MM-DD.
  const latest = new Intl.DateTimeFormat("en-CA", {
    timeZone: "America/New_York", year: "numeric", month: "2-digit", day: "2-digit",
  }).format(now);

  const [year, month, day] = latest.split("-").map(Number);
  const earliestYear = year - DOB_MAX_AGE_YEARS;
  // 29 February becomes the 28th in a year without one, as on the server.
  const daysInMonth = new Date(Date.UTC(earliestYear, month, 0)).getUTCDate();
  const earliest = `${String(earliestYear).padStart(4, "0")}-${pad(month)}-${pad(Math.min(day, daysInMonth))}`;

  return { earliest, latest };
}

// The message to show, or null when the date is fine (or empty).
export function dateOfBirthError(value, now = new Date()) {
  if (!value) return null;
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return "Enter a valid date.";

  const { earliest, latest } = dateOfBirthBounds(now);
  if (value > latest) return "Date of birth can't be in the future.";
  if (value < earliest) return `Date of birth can't be more than ${DOB_MAX_AGE_YEARS} years ago.`;
  return null;
}
