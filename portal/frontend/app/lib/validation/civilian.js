import { z } from "zod";
import { nameField, pastDateField } from "./shared";

export const submitterSchema = z.object({
  firstName: nameField,
  lastName: nameField,
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
  offenderDob: z
    .string()
    .trim()
    .refine((val) => !val || !isNaN(Date.parse(val)), "Enter a valid date")
    .refine((val) => !val || new Date(val) <= new Date(), "Date cannot be in the future")
    .optional()
    .or(z.literal("")),
  offenderSex: z.enum(["M", "F", "O"], { errorMap: () => ({ message: "Select an option" }) }),
  offenderRelationship: z.string().trim().min(1, "Required"),
});
