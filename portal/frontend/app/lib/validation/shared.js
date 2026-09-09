import { z } from "zod";

// Shared name pattern: letters, spaces, hyphens, apostrophes only
export const nameField = z
  .string()
  .trim()
  .min(1, "Required")
  .regex(/^[a-zA-Z\s'-]+$/, "Cannot contain numbers or symbols");

// Shared "must not be in the future" date field
export const pastDateField = z
  .string()
  .min(1, "Required")
  .refine((val) => !isNaN(Date.parse(val)), "Enter a valid date")
  .refine((val) => new Date(val) <= new Date(), "Date cannot be in the future");

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
