export function isValidEmail(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

export function isValidPhone(value) {
  return /^[0-9()+\-.\s]{7,20}$/.test(value);
}

export function isNotFutureDate(value) {
  if (!value) return true;
  return value <= new Date().toISOString().slice(0, 10);
}
