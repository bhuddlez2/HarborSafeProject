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

export function isNotFutureDate(value) {
  if (!value) return true;
  return value <= new Date().toISOString().slice(0, 10);
}
