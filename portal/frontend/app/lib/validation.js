import { z } from "zod";

// Shared name pattern: letters, spaces, hyphens, apostrophes only
const nameField = z
  .string()
  .trim()
  .min(1, "Required")
  .regex(/^[a-zA-Z\s'-]+$/, "Cannot contain numbers or symbols");

// Shared "must not be in the future" date field
const pastDateField = z
  .string()
  .min(1, "Required")
  .refine((val) => !isNaN(Date.parse(val)), "Enter a valid date")
  .refine((val) => new Date(val) <= new Date(), "Date cannot be in the future");

export const submitterSchema = z.object({
  firstName: nameField,
  lastName: nameField,
  badge: z.string().trim().optional(),
  email: z
    .string()
    .trim()
    .email("Enter a valid email address")
    .optional()
    .or(z.literal("")), // allows empty string since it's optional
  phone: z.string().trim().optional(),
});

export const victimSchema = z.object({
  victimFirstName: nameField,
  victimLastName: nameField,
  victimDob: pastDateField,
  victimSex: z.enum(["M", "F", "O"], { errorMap: () => ({ message: "Select an option" }) }),
  victimPhone: z.string().trim().optional(),
});

export const offenderSchema = z.object({
  offenderFirstName: nameField,
  offenderLastName: nameField,
  offenderDob: pastDateField,
  offenderSex: z.enum(["M", "F", "O"], { errorMap: () => ({ message: "Select an option" }) }),
  offenderRelationship: z.string().trim().min(1, "Required"),
});