"use client";

/*
AssessmentContent: the civilian lethality assessment, moved here from portal/frontend.

One page, several phases: who the report is for (and, for someone else, whether to stay
anonymous), the submitter's details when they aren't anonymous, the victim, the offender,
the 11 yes/no questions, then review and submit.

The review shows everything entered and every answer, with an Edit button on each
section and a Change button on each answer. Once the review has been reached, each
step's button returns straight to it ("Return to review") instead of walking on through
the rest. Submit stays disabled until the safe-phone box is ticked ("I confirm {number}
is a safe phone number to contact") - the same check as the officer wizard
(NewAssessment::reviewStep()), in Harbor Safe's own wording. The box is unticked
whenever the number changes and is never sent: it only makes sure the person knows the
number must be safe to call.

Everything entered stays in this component's state until Submit, which sends it all in
one request (lib/assessment.js). Nothing is written to the URL, cookies or browser
storage, autofill is turned off on every field so a shared device doesn't remember
names typed here, and the state is cleared as soon as the submission is accepted.
*/

import { useState, useEffect, useRef, startTransition } from "react";
import Link from "next/link";
import Modal from "../components/Modal";
import { OPTIONAL_PLACEHOLDER, RequiredNote, focusPageHeading } from "../components/FormControls";
import { lethalityQuestions, questionText } from "../lib/lethality-questions";
import { submitAssessment, fetchRelationshipOptions } from "../lib/assessment";
import {
  isValidEmail, isValidPhone, dateOfBirthError, formatDateInput, typedDateToIso, DOB_INPUT_MAX_LENGTH,
  formatPhoneInput, PHONE_INPUT_MAX_LENGTH,
} from "../lib/validators";

// pt-22.5 on the layout already clears the fixed navbar (5.625rem), so the grey
// backdrop fills the rest of the viewport.
const PAGE = "min-h-[calc(100vh-5.625rem)] bg-[#F4F2F7] flex items-start md:items-center justify-center px-4 py-10 md:py-16";
const CARD = "bg-white rounded-2xl shadow-lg w-full max-w-2xl px-6 py-10 md:px-12 md:py-14";
const HEADING = "text-3xl font-semibold text-gray-900";
const INPUT =
  "w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900 placeholder:text-gray-400 " +
  "focus:outline-none focus:border-[#5C0F8B] transition";
const SELECT =
  "h-12 appearance-none border-2 border-gray-300 rounded-lg px-4 py-2 text-gray-900 " +
  "focus:outline-none focus:border-[#5C0F8B] transition disabled:opacity-60";
const PRIMARY =
  "bg-[#5C0F8B] text-white px-8 py-4 rounded-lg text-lg hover:bg-[#4C0B74] " +
  "focus:outline-none focus:ring-4 focus:ring-[#5C0F8B]/40 transition " +
  "disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-[#5C0F8B]";
const SECONDARY =
  "border-2 border-[#5C0F8B] text-[#5C0F8B] px-8 py-4 rounded-lg text-lg " +
  "hover:bg-[#5C0F8B] hover:text-white focus:outline-none focus:ring-4 focus:ring-[#5C0F8B]/40 transition";
const ANSWER =
  "flex-1 border-2 border-[#5C0F8B] text-[#5C0F8B] text-lg py-4 rounded-lg " +
  "hover:bg-[#5C0F8B] hover:text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-gray-400 transition";

const choiceClass = (selected) =>
  `flex-1 py-4 rounded-lg text-lg border-2 transition focus:outline-none focus:ring-4 focus:ring-[#5C0F8B]/40 ${
    selected
      ? "bg-[#5C0F8B] text-white border-[#5C0F8B]"
      : "border-[#5C0F8B] text-[#5C0F8B] hover:bg-[#5C0F8B] hover:text-white"
  }`;

const SEX_OPTIONS = [
  { value: "M", label: "Male" },
  { value: "F", label: "Female" },
  { value: "O", label: "Other" },
];

/*
Required and optional markers - the same rule as the site's other forms (FormControls)
and the staff panel: a red asterisk after a required field's label, and faded grey
"Optional" ghost text inside an optional text box. A date can't hold ghost text, so an
optional date gets the same grey word after its label instead (`optional`). A label with
neither is an optional box using ghost text, which screen readers can't rely on, so it
carries ", optional" for them only. Steps with an asterisk show RequiredNote above their
buttons.
*/
function Label({ htmlFor, required = false, optional = false, children }) {
  return (
    <label htmlFor={htmlFor} className="block text-sm font-medium text-gray-700 mb-1">
      {children}
      {required && <span className="text-red-700 ml-1" aria-hidden="true">*</span>}
      {optional && <span className="text-gray-400 font-normal ml-1">{OPTIONAL_PLACEHOLDER}</span>}
      {!required && !optional && <span className="sr-only">, optional</span>}
    </label>
  );
}

function FieldError({ id, children }) {
  if (!children) return null;
  return <p id={id} className="text-sm text-red-700 mt-1">{children}</p>;
}

function SexSelect({ id, value, onChange }) {
  return (
    <select
      id={id}
      value={value}
      onChange={(e) => onChange(e.target.value)}
      aria-required="true"
      className={`w-32 ${SELECT}`}
    >
      <option value="">Select</option>
      {SEX_OPTIONS.map((option) => (
        <option key={option.value} value={option.value}>{option.label}</option>
      ))}
    </select>
  );
}

// "1990-04-12" as "Apr 12, 1990". Built from the parts, so the time zone can't move it
// to the day before.
// A typed MM/DD/YYYY date of birth as "Mar 14, 1985" for the review
function formatDate(typed) {
  const value = typedDateToIso(typed);
  if (!value) return null;
  const [year, month, day] = value.split("-").map(Number);
  return new Date(year, month - 1, day).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}

const EDIT_BUTTON =
  "tap-target text-sm font-semibold text-[#5C0F8B] underline underline-offset-2 rounded px-1 " +
  "hover:text-[#4C0B74] focus:outline-none focus:ring-2 focus:ring-[#5C0F8B]/40";

// One block of the review: a heading, an Edit button, and label/value pairs.
function ReviewSection({ title, editLabel, onEdit, fields }) {
  return (
    <section className="rounded-xl bg-[#F5F0FB] p-4 md:p-5">
      <div className="flex items-center justify-between gap-3 mb-3">
        <h2 className="text-xs font-bold uppercase tracking-widest text-[#5C0F8B]">{title}</h2>
        <button type="button" onClick={onEdit} aria-label={editLabel} className={EDIT_BUTTON}>Edit</button>
      </div>
      <dl className="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
        {fields.map(([label, value]) => (
          <div key={label}>
            <dt className="text-xs font-semibold uppercase tracking-wide text-[#9068B8]">{label}</dt>
            <dd className="font-semibold text-gray-900 break-words">{value || "—"}</dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

const RELATIONSHIP_LOAD_ERROR = "We couldn't load this list. Please try again.";

// The backend's message for the same rule (App\Support\PhoneNumber::MESSAGE).
const PHONE_ERROR = "Enter a 10-digit phone number, starting with the area code.";

export default function AssessmentContent() {
  const [showSafetyModal, setShowSafetyModal] = useState(false);

  useEffect(() => {
    startTransition(() => setShowSafetyModal(true));
  }, []);

  // phase: "prescreen", "info", "victim", "offender", "intro", "questions", "complete", "submitted"
  const [phase, setPhase] = useState("prescreen");

  // Each new step starts at the top, with focus on its heading, so the next Tab
  // goes to the step's first control and a screen reader reads the new heading.
  // Skipped on first load, where the Confidentiality Notice takes focus.
  const firstPhase = useRef(true);
  useEffect(() => {
    if (firstPhase.current) {
      firstPhase.current = false;
      return;
    }
    focusPageHeading();
  }, [phase]);
  // index: which question is displayed
  const [index, setIndex] = useState(0);
  // answers: { questionId: boolean }
  const [answers, setAnswers] = useState({});

  // prescreen: anonymous stays null until forWhom is answered
  const [forWhom, setForWhom] = useState(null);
  const [anonymous, setAnonymous] = useState(null);

  // info phase (the submitter, only when reporting for someone else and not anonymous)
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");

  // victim phase
  const [victimFirstName, setVictimFirstName] = useState("");
  const [victimLastName, setVictimLastName] = useState("");
  // Dates of birth are held as typed (MM/DD/YYYY) and turned into YYYY-MM-DD on submit
  const [victimDob, setVictimDob] = useState("");
  const [victimDobLeft, setVictimDobLeft] = useState(false);
  const [victimSex, setVictimSex] = useState("");
  const [victimPhone, setVictimPhone] = useState("");

  // offender phase; the relationship is a code from the backend's list ("spouse",
  // "other", ...), plus the typed detail when it's "other"
  const [offenderFirstName, setOffenderFirstName] = useState("");
  const [offenderLastName, setOffenderLastName] = useState("");
  const [offenderDob, setOffenderDob] = useState("");
  const [offenderDobLeft, setOffenderDobLeft] = useState(false);
  const [offenderSex, setOffenderSex] = useState("");
  const [offenderRelationship, setOffenderRelationship] = useState("");
  const [offenderRelationshipOther, setOffenderRelationshipOther] = useState("");

  // The options come from GET /api/public/relationships (no copy kept here).
  // null while loading; an error message if they couldn't be loaded.
  const [relationshipOptions, setRelationshipOptions] = useState(null);
  const [relationshipOptionsError, setRelationshipOptionsError] = useState(null);

  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);

  // Once the review has been reached, every step's button goes back to it.
  const [reviewReached, setReviewReached] = useState(false);
  // The review's "this number is safe" box. Page-only: never sent.
  const [safePhoneConfirmed, setSafePhoneConfirmed] = useState(false);

  const loadRelationshipOptions = () => {
    setRelationshipOptionsError(null);
    fetchRelationshipOptions()
      .then(setRelationshipOptions)
      .catch(() => setRelationshipOptionsError(RELATIONSHIP_LOAD_ERROR));
  };

  useEffect(() => {
    let cancelled = false;
    fetchRelationshipOptions()
      .then((options) => { if (!cancelled) setRelationshipOptions(options); })
      .catch(() => { if (!cancelled) setRelationshipOptionsError(RELATIONSHIP_LOAD_ERROR); });
    return () => { cancelled = true; };
  }, []);

  // Per-phase checks, computed live so each phase can block "Continue" and show
  // feedback before Submit. The API applies the same rules again.
  const emailError = email.trim() && !isValidEmail(email.trim()) ? "Enter a valid email address." : null;
  const phoneError = phone.trim() && !isValidPhone(phone.trim()) ? PHONE_ERROR : null;
  const victimDobError = dateOfBirthError(victimDob);
  const victimPhoneError = victimPhone.trim() && !isValidPhone(victimPhone.trim()) ? PHONE_ERROR : null;
  const offenderDobError = dateOfBirthError(offenderDob);
  /*
  A date of birth's message shows once all ten characters are in or the box has
  been left, never mid-typing; Continue is held back by the error either way
  */
  const victimDobShownError = victimDob.length === DOB_INPUT_MAX_LENGTH || victimDobLeft ? victimDobError : null;
  const offenderDobShownError = offenderDob.length === DOB_INPUT_MAX_LENGTH || offenderDobLeft ? offenderDobError : null;

  const total = lethalityQuestions.length;
  const current = lethalityQuestions[index];
  const identified = forWhom === "other" && anonymous === false;

  // The names the questions are asked with (#33): first names only - the offender's,
  // and the victim's except on a report for oneself, where the reader is the victim
  // and "you" stays. Anonymity only hides the reporter, so it changes nothing here.
  // Display only: answers are still sent by question number.
  const questionNames = {
    victim: forWhom === "other" ? victimFirstName : null,
    offender: offenderFirstName,
  };

  // Records the answer and moves on: to the next question, or to the review after the
  // last one - or straight back to it when the answer was changed from there.
  const handleAnswer = (value) => {
    setAnswers({ ...answers, [current.id]: value });

    if (index < total - 1 && !reviewReached) {
      setIndex(index + 1);
    } else {
      setReviewReached(true);
      setPhase("complete");
    }
  };

  // A step's forward button: the next step, or back to the review once it's been seen.
  const goOn = (nextPhase) => setPhase(reviewReached ? "complete" : nextPhase);
  const continueLabel = reviewReached ? "Return to review" : "Continue";

  // The safe number changed, so it has to be confirmed again.
  const changeVictimPhone = (value) => {
    setVictimPhone(formatPhoneInput(value));
    setSafePhoneConfirmed(false);
  };

  const handleBack = () => {
    if (index > 0) setIndex(index - 1);
  };

  // Clears everything that was entered. Run as soon as a submission is accepted, so
  // the details don't stay in the page, and again for "Submit Another Assessment".
  const clearEntries = () => {
    setAnswers({});
    setIndex(0);
    setForWhom(null);
    setAnonymous(null);
    setFirstName(""); setLastName(""); setEmail(""); setPhone("");
    setVictimFirstName(""); setVictimLastName(""); setVictimDob(""); setVictimDobLeft(false); setVictimSex(""); setVictimPhone("");
    setOffenderFirstName(""); setOffenderLastName(""); setOffenderDob(""); setOffenderDobLeft(false); setOffenderSex("");
    setOffenderRelationship(""); setOffenderRelationshipOther("");
    setSubmitError(null);
    setReviewReached(false);
    setSafePhoneConfirmed(false);
  };

  const handleSubmit = async () => {
    // The button is disabled until then; this stops anything that bypasses it.
    if (!safePhoneConfirmed) return;

    setSubmitting(true);
    setSubmitError(null);

    try {
      await submitAssessment({
        forWhom,
        anonymous,
        submitter: { firstName, lastName, email, phone },
        victim: { firstName: victimFirstName, lastName: victimLastName, dob: typedDateToIso(victimDob), sex: victimSex, phone: victimPhone },
        offender: {
          firstName: offenderFirstName,
          lastName: offenderLastName,
          dob: typedDateToIso(offenderDob),
          sex: offenderSex,
          relationship: offenderRelationship,
          relationshipOther: offenderRelationshipOther,
        },
        answers,
        questionCount: total,
      });
      clearEntries();
      setPhase("submitted");
    } catch (error) {
      if (error.status === 429) {
        setSubmitError("Too many submissions just now. Nothing was saved. Please wait a minute and try again. Your answers are still here.");
      } else if (error instanceof TypeError) {
        // fetch rejects with a TypeError when the server can't be reached at all
        setSubmitError("We couldn't reach the server. Please check your connection and try again.");
      } else {
        setSubmitError(error.message);
      }
    } finally {
      setSubmitting(false);
    }
  };

  // Prescreen: who the report is for, and anonymity when it's for someone else
  if (phase === "prescreen") {
    return (
      <main className={PAGE}>

        {/* Confidentiality Notice, shown each time the assessment is opened, before the form begins.
            Not dismissible: "I understand, continue" is the only way on. Safe Exit stays usable above it. */}
        {showSafetyModal && (
          <Modal title="Confidentiality Notice" dismissible={false} onClose={() => setShowSafetyModal(false)}>
            <div className="px-6 py-5">
              <p className="text-sm text-gray-600 leading-relaxed mb-5 text-center">
                Information you provide in this assessment is kept confidential.
              </p>

              <div className="space-y-4 mb-5">
                <div className="flex gap-3 items-start">
                  <div className="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
                    <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-gray-700 stroke-2 fill-none" aria-hidden="true">
                      {/* lock icon, feathericons.com */}
                      <rect x="3" y="11" width="18" height="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-gray-900 mb-0.5">Nothing is saved until you submit</p>
                    <p className="text-xs text-gray-600 leading-relaxed">
                      Nothing in this form is saved or sent to Harbor Safe House until you press
                      Submit at the end. After you submit, only Harbor Safe House sees your answers.
                    </p>
                  </div>
                </div>

                <div className="flex gap-3 items-start">
                  <div className="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
                    <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-gray-700 stroke-2 fill-none" aria-hidden="true">
                      {/* phone icon, feathericons.com */}
                      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                    </svg>
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-gray-900 mb-0.5">Harbor Safe House will follow up</p>
                    <p className="text-xs text-gray-600 leading-relaxed">
                      A Harbor Safe House advocate will contact the victim at the safe phone number you
                      provide, so only give a number that is safe to call.
                    </p>
                  </div>
                </div>

                <div className="flex gap-3 items-start">
                  <div className="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
                    <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-gray-700 stroke-2 fill-none" aria-hidden="true">
                      {/* trash icon, feathericons.com */}
                      <polyline points="3 6 5 6 21 6" /><path d="M19 6l-1 14H6L5 6" /><path d="M10 11v6M14 11v6" /><path d="M9 6V4h6v2" />
                    </svg>
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-gray-900 mb-0.5">Browse privately</p>
                    <p className="text-xs text-gray-600 leading-relaxed">
                      If you are concerned about someone monitoring your activity, consider using
                      a private window and clearing your browser history afterward.
                    </p>
                  </div>
                </div>

                <div className="flex gap-3 items-start">
                  <div className="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
                    <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-amber-600 stroke-2 fill-none" aria-hidden="true">
                      {/* alert-triangle icon, feathericons.com */}
                      <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />
                    </svg>
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-gray-900 mb-0.5">Mandatory reporting</p>
                    <p className="text-xs text-gray-600 leading-relaxed">
                      If information disclosed in this assessment indicates that a minor is being
                      abused or is at risk, we may be legally required to report it to the
                      appropriate authorities.
                    </p>
                  </div>
                </div>
              </div>

              <button
                onClick={() => setShowSafetyModal(false)}
                className="w-full bg-[#5C0F8B] text-white py-2.5 rounded-lg font-semibold text-sm
                           hover:bg-[#4C0B74] focus:outline-none focus:ring-4 focus:ring-[#5C0F8B]/40 transition"
              >
                I understand, continue
              </button>
            </div>
          </Modal>
        )}

        <div className={CARD}>
          <h1 className={`${HEADING} mb-8`}>Before We Begin</h1>

          <p className="text-gray-700 text-lg font-medium mb-4">Who is this report for?</p>
          <div className="flex gap-4 mb-10">
            <button onClick={() => setForWhom("self")} aria-pressed={forWhom === "self"} className={choiceClass(forWhom === "self")}>
              Myself
            </button>
            <button onClick={() => setForWhom("other")} aria-pressed={forWhom === "other"} className={choiceClass(forWhom === "other")}>
              Someone else
            </button>
          </div>

          {/* Anonymity is only asked when assessing someone else */}
          {forWhom === "other" && (
            <div className="mb-10">
              <p className="text-gray-700 text-lg font-medium mb-4">Would you like to remain anonymous?</p>
              <div className="flex gap-4">
                <button onClick={() => setAnonymous(true)} aria-pressed={anonymous === true} className={choiceClass(anonymous === true)}>
                  Yes
                </button>
                <button onClick={() => setAnonymous(false)} aria-pressed={anonymous === false} className={choiceClass(anonymous === false)}>
                  No
                </button>
              </div>
            </div>
          )}

          {/* Continue: straight away for "self"; for "other" once anonymity is answered.
              From the review, a switch to a named report still collects the name first. */}
          {(forWhom === "self" || (forWhom === "other" && anonymous !== null)) && (
            <button
              onClick={() => (identified && (!reviewReached || !firstName.trim() || !lastName.trim())
                ? setPhase("info")
                : goOn("victim"))}
              className={PRIMARY}
            >
              {identified && reviewReached && (!firstName.trim() || !lastName.trim()) ? "Continue" : continueLabel}
            </button>
          )}
        </div>
      </main>
    );
  }

  // Info: the submitter's own details, when reporting for someone else and not anonymous
  if (phase === "info") {
    return (
      <main className={PAGE}>
        <div className={CARD}>
          <h1 className={`${HEADING} mb-10`}>Your Information</h1>

          <div className="flex flex-col sm:flex-row gap-4 mb-6">
            <div className="flex-1">
              <Label htmlFor="submitter-first-name" required>First Name</Label>
              <input id="submitter-first-name" type="text" value={firstName} onChange={(e) => setFirstName(e.target.value)}
                maxLength={50} autoComplete="off" aria-required="true" className={INPUT} />
            </div>
            <div className="flex-1">
              <Label htmlFor="submitter-last-name" required>Last Name</Label>
              <input id="submitter-last-name" type="text" value={lastName} onChange={(e) => setLastName(e.target.value)}
                maxLength={50} autoComplete="off" aria-required="true" className={INPUT} />
            </div>
          </div>

          <div className="mb-6">
            <Label htmlFor="submitter-email">Email</Label>
            <input id="submitter-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)}
              placeholder={OPTIONAL_PLACEHOLDER} maxLength={100} autoComplete="off"
              aria-invalid={!!emailError} aria-describedby={emailError ? "submitter-email-error" : undefined} className={INPUT} />
            <FieldError id="submitter-email-error">{emailError}</FieldError>
          </div>

          <div className="mb-10">
            <Label htmlFor="submitter-phone">Phone Number</Label>
            <input id="submitter-phone" type="tel" value={phone} onChange={(e) => setPhone(formatPhoneInput(e.target.value))}
              placeholder={OPTIONAL_PLACEHOLDER} maxLength={PHONE_INPUT_MAX_LENGTH} autoComplete="off"
              aria-invalid={!!phoneError} aria-describedby={phoneError ? "submitter-phone-error" : undefined} className={INPUT} />
            <FieldError id="submitter-phone-error">{phoneError}</FieldError>
          </div>

          <RequiredNote className="-mt-4 mb-4" />
          <div className="flex gap-4">
            <button onClick={() => setPhase("prescreen")} className={SECONDARY}>Back</button>
            <button
              onClick={() => goOn("victim")}
              disabled={!firstName.trim() || !lastName.trim() || !!emailError || !!phoneError}
              className={PRIMARY}
            >
              {continueLabel}
            </button>
          </div>
        </div>
      </main>
    );
  }

  // Victim: the person the assessment is about
  if (phase === "victim") {
    return (
      <main className={PAGE}>
        <div className={CARD}>
          <h1 className={`${HEADING} mb-10`}>{forWhom === "self" ? "Your Information" : "About the Victim"}</h1>

          <div className="flex flex-col sm:flex-row gap-4 mb-6">
            <div className="flex-1">
              <Label htmlFor="victim-first-name" required>First Name</Label>
              <input id="victim-first-name" type="text" value={victimFirstName} onChange={(e) => setVictimFirstName(e.target.value)}
                maxLength={50} autoComplete="off" aria-required="true" className={INPUT} />
            </div>
            <div className="flex-1">
              <Label htmlFor="victim-last-name" required>Last Name</Label>
              <input id="victim-last-name" type="text" value={victimLastName} onChange={(e) => setVictimLastName(e.target.value)}
                maxLength={50} autoComplete="off" aria-required="true" className={INPUT} />
            </div>
          </div>

          <div className="flex flex-wrap gap-4 mb-6">
            <div>
              <Label htmlFor="victim-dob" required>Date of Birth</Label>
              <input id="victim-dob" type="text" inputMode="numeric" value={victimDob}
                onChange={(e) => setVictimDob(formatDateInput(e.target.value))} onBlur={() => setVictimDobLeft(true)}
                maxLength={DOB_INPUT_MAX_LENGTH} autoComplete="off" aria-required="true"
                aria-invalid={!!victimDobShownError}
                aria-describedby={victimDobShownError ? "victim-dob-hint victim-dob-error" : "victim-dob-hint"}
                className={`w-40 ${INPUT}`} />
              <p id="victim-dob-hint" className="text-xs text-gray-500 mt-1">MM/DD/YYYY</p>
              <FieldError id="victim-dob-error">{victimDobShownError}</FieldError>
            </div>
            <div>
              <Label htmlFor="victim-sex" required>Sex</Label>
              <SexSelect id="victim-sex" value={victimSex} onChange={setVictimSex} />
            </div>
          </div>

          <div className="mb-10">
            <Label htmlFor="victim-phone" required>Safe Phone Number</Label>
            {/* Required so Harbor Safe can follow up after the assessment. */}
            <input id="victim-phone" type="tel" value={victimPhone} onChange={(e) => changeVictimPhone(e.target.value)}
              maxLength={PHONE_INPUT_MAX_LENGTH} autoComplete="off" aria-required="true"
              aria-invalid={!!victimPhoneError} aria-describedby={victimPhoneError ? "victim-phone-error" : undefined} className={INPUT} />
            <FieldError id="victim-phone-error">{victimPhoneError}</FieldError>
          </div>

          <RequiredNote className="-mt-4 mb-4" />
          <div className="flex gap-4">
            <button onClick={() => setPhase(identified ? "info" : "prescreen")} className={SECONDARY}>Back</button>
            <button
              onClick={() => goOn("offender")}
              disabled={!victimFirstName.trim() || !victimLastName.trim() || !victimDob || !!victimDobError || !victimSex
                || !victimPhone.trim() || !!victimPhoneError}
              className={PRIMARY}
            >
              {continueLabel}
            </button>
          </div>
        </div>
      </main>
    );
  }

  // Offender: the person who is abusive
  if (phase === "offender") {
    return (
      <main className={PAGE}>
        <div className={CARD}>
          <h1 className={`${HEADING} mb-10`}>About the Offender</h1>

          <div className="flex flex-col sm:flex-row gap-4 mb-6">
            <div className="flex-1">
              <Label htmlFor="offender-first-name" required>First Name</Label>
              <input id="offender-first-name" type="text" value={offenderFirstName} onChange={(e) => setOffenderFirstName(e.target.value)}
                maxLength={50} autoComplete="off" aria-required="true" className={INPUT} />
            </div>
            <div className="flex-1">
              <Label htmlFor="offender-last-name">Last Name</Label>
              <input id="offender-last-name" type="text" value={offenderLastName} onChange={(e) => setOffenderLastName(e.target.value)}
                placeholder={OPTIONAL_PLACEHOLDER} maxLength={50} autoComplete="off" className={INPUT} />
            </div>
          </div>

          <div className="flex flex-wrap gap-4 mb-6">
            <div>
              {/* Now a box that can hold ghost text, so "Optional" goes inside it like every other optional box */}
              <Label htmlFor="offender-dob">Date of Birth</Label>
              <input id="offender-dob" type="text" inputMode="numeric" value={offenderDob}
                onChange={(e) => setOffenderDob(formatDateInput(e.target.value))} onBlur={() => setOffenderDobLeft(true)}
                placeholder={OPTIONAL_PLACEHOLDER} maxLength={DOB_INPUT_MAX_LENGTH} autoComplete="off"
                aria-invalid={!!offenderDobShownError}
                aria-describedby={offenderDobShownError ? "offender-dob-hint offender-dob-error" : "offender-dob-hint"}
                className={`w-40 ${INPUT}`} />
              <p id="offender-dob-hint" className="text-xs text-gray-500 mt-1">MM/DD/YYYY</p>
              <FieldError id="offender-dob-error">{offenderDobShownError}</FieldError>
            </div>
            <div>
              <Label htmlFor="offender-sex" required>Sex</Label>
              <SexSelect id="offender-sex" value={offenderSex} onChange={setOffenderSex} />
            </div>
          </div>

          <div className="mb-10">
            <Label htmlFor="offender-relationship" required>Relationship to Victim</Label>
            {relationshipOptionsError ? (
              <div className="flex items-center gap-3">
                <p role="alert" className="text-sm text-red-700">{relationshipOptionsError}</p>
                <button
                  type="button"
                  onClick={loadRelationshipOptions}
                  className="tap-target text-sm font-semibold text-[#5C0F8B] underline focus:outline-none focus:ring-2 focus:ring-[#5C0F8B]/40 rounded"
                >
                  Try again
                </button>
              </div>
            ) : (
              <select
                id="offender-relationship"
                value={offenderRelationship}
                onChange={(e) => setOffenderRelationship(e.target.value)}
                disabled={relationshipOptions === null}
                aria-required="true"
                className={`w-full ${SELECT}`}
              >
                <option value="">{relationshipOptions === null ? "Loading…" : "Select"}</option>
                {(relationshipOptions ?? []).map((option) => (
                  <option key={option.value} value={option.value}>{option.label}</option>
                ))}
              </select>
            )}

            {offenderRelationship === "other" && (
              <div className="mt-4">
                <Label htmlFor="offender-relationship-other" required>Please Specify</Label>
                <input id="offender-relationship-other" type="text" value={offenderRelationshipOther}
                  onChange={(e) => setOffenderRelationshipOther(e.target.value)}
                  maxLength={50} autoComplete="off" aria-required="true" className={INPUT} />
              </div>
            )}
          </div>

          <RequiredNote className="-mt-4 mb-4" />
          <div className="flex gap-4">
            <button onClick={() => setPhase("victim")} className={SECONDARY}>Back</button>
            <button
              onClick={() => goOn("intro")}
              disabled={!offenderFirstName.trim() || !offenderSex || !!offenderDobError
                || !offenderRelationship || (offenderRelationship === "other" && !offenderRelationshipOther.trim())}
              className={PRIMARY}
            >
              {continueLabel}
            </button>
          </div>
        </div>
      </main>
    );
  }

  // Intro to the questions
  if (phase === "intro") {
    return (
      <main className={PAGE}>
        <div className={CARD}>
          <h1 className={`${HEADING} mb-6`}>Lethality Assessment</h1>
          <p className="text-gray-700 text-lg mb-4 leading-relaxed">
            This screen contains {total} yes/no questions. Ask each question exactly as written.
          </p>
          <p className="text-gray-700 text-lg mb-10 leading-relaxed">
            Nothing is sent or saved until you press Submit at the end. If you close or leave
            this page before then, your answers are gone.
          </p>
          <button onClick={() => setPhase("questions")} className={PRIMARY}>Begin assessment</button>
        </div>
      </main>
    );
  }

  // Review and submit: everything entered, every answer, and the safe-phone check.
  // Answers are shown plainly, with no risk colouring or score: the person reading
  // this may be the victim.
  if (phase === "complete") {
    const sexLabel = (code) => SEX_OPTIONS.find((option) => option.value === code)?.label;
    const relationshipLabel = offenderRelationship === "other"
      ? `Other: ${offenderRelationshipOther.trim()}`
      : relationshipOptions?.find((option) => option.value === offenderRelationship)?.label ?? offenderRelationship;
    const forSelf = forWhom === "self";

    return (
      <main className={PAGE}>
        <div className={CARD}>
          <h1 className={`${HEADING} mb-2 text-center`}>Review &amp; Submit</h1>
          <p className="text-gray-600 mb-6 text-center">
            Check every detail and answer below. Use Edit or Change to fix anything. Nothing has
            been sent yet. It is sent only when you press Submit assessment.
          </p>

          <div className="flex flex-col gap-4">
            {/* A report for oneself has nothing to show here, but keeps the way back to "Who is this report for?". */}
            {forSelf ? (
              <p>
                <button type="button" onClick={() => setPhase("prescreen")} className={EDIT_BUTTON}>
                  Change who this report is for
                </button>
              </p>
            ) : (
              <ReviewSection
                title="This report"
                editLabel="Edit who this report is for"
                onEdit={() => setPhase("prescreen")}
                fields={[
                  ["Report for", "Someone else"],
                  ["Anonymous", anonymous ? "Yes" : "No"],
                ]}
              />
            )}

            {identified && (
              <ReviewSection
                title="Your information"
                editLabel="Edit your information"
                onEdit={() => setPhase("info")}
                fields={[
                  ["Name", `${firstName.trim()} ${lastName.trim()}`],
                  ["Email", email.trim()],
                  ["Phone number", phone.trim()],
                ]}
              />
            )}

            <ReviewSection
              title="Victim"
              editLabel="Edit the victim's details"
              onEdit={() => setPhase("victim")}
              fields={[
                ["Name", `${victimFirstName.trim()} ${victimLastName.trim()}`],
                ["Sex", sexLabel(victimSex)],
                ["Date of birth", formatDate(victimDob)],
                ["Safe phone number", victimPhone],
              ]}
            />

            <ReviewSection
              title="Offender"
              editLabel="Edit the offender's details"
              onEdit={() => setPhase("offender")}
              fields={[
                ["Name", `${offenderFirstName.trim()} ${offenderLastName.trim()}`.trim()],
                ["Sex", sexLabel(offenderSex)],
                ["Date of birth", formatDate(offenderDob) ?? "Not given"],
                ["Relationship to victim", relationshipLabel],
              ]}
            />

            <section className="overflow-hidden rounded-xl border-2 border-[#C9C1D6]">
              <h2 className="bg-[#EDE3F5] px-4 py-3 text-xs font-bold uppercase tracking-widest text-[#5C0F8B]">Answers</h2>
              <ol className="divide-y divide-[#EDE3F5]">
                {lethalityQuestions.map((question, i) => {
                  const value = answers[question.id];
                  return (
                    <li key={question.id} className="flex flex-wrap items-start gap-3 px-4 py-3">
                      <span className="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-500">
                        {question.id}
                      </span>
                      <span className="flex-1 min-w-48 text-sm leading-relaxed text-gray-700">{questionText(question, questionNames)}</span>
                      <span className="flex items-center gap-2 shrink-0 ml-auto">
                        <span className={`rounded-md px-2 py-0.5 text-xs font-bold tracking-wide ${
                          value === true ? "bg-[#F5F0FB] text-[#5C0F8B]" : "bg-gray-100 text-gray-600"
                        }`}>
                          {value === undefined ? "NOT ANSWERED" : value ? "YES" : "NO"}
                        </span>
                        <button
                          type="button"
                          onClick={() => { setIndex(i); setPhase("questions"); }}
                          aria-label={`Change answer to question ${question.id}`}
                          className={EDIT_BUTTON}
                        >
                          Change
                        </button>
                      </span>
                    </li>
                  );
                })}
              </ol>
            </section>
          </div>

          {/* The same check as the officer wizard, in this page's own wording. Never sent. */}
          <label htmlFor="safe-phone-confirmed" className="flex items-start gap-3 mt-8 cursor-pointer">
            <input
              id="safe-phone-confirmed"
              type="checkbox"
              checked={safePhoneConfirmed}
              onChange={(e) => setSafePhoneConfirmed(e.target.checked)}
              className="mt-1 h-5 w-5 shrink-0 accent-[#5C0F8B]"
            />
            <span className="text-gray-800 leading-relaxed">
              I confirm <strong>{victimPhone}</strong> is a safe phone number to contact
            </span>
          </label>
          {!safePhoneConfirmed && (
            <p className="text-sm text-gray-500 mt-2 ml-8">Tick the box to submit.</p>
          )}

          {submitError && <p role="alert" className="text-red-700 text-sm mt-6">{submitError}</p>}

          <div className="flex flex-wrap gap-4 mt-8">
            <button onClick={() => { setIndex(total - 1); setPhase("questions"); }} className={SECONDARY}>Back</button>
            <button onClick={handleSubmit} disabled={submitting || !safePhoneConfirmed} className={PRIMARY}>
              {submitting ? "Submitting..." : "Submit assessment"}
            </button>
          </div>
        </div>
      </main>
    );
  }

  // Submitted. Everything entered was cleared when the API accepted it.
  if (phase === "submitted") {
    return (
      <main className={PAGE}>
        <div className={`${CARD} text-center`}>
          <h1 className={`${HEADING} mb-6`}>Assessment Submitted</h1>
          <p className="text-gray-700 text-lg mb-10 leading-relaxed">
            Your assessment has been sent to Harbor Safe House. Harbor Safe House will follow up
            at the safe phone number you gave. Your answers have been cleared from this page.
          </p>
          <div className="flex flex-wrap justify-center gap-4">
            <button onClick={() => { clearEntries(); setPhase("prescreen"); }} className={SECONDARY}>
              Submit Another Assessment
            </button>
            <Link href="/resources" className={PRIMARY}>View Resources</Link>
          </div>
        </div>
      </main>
    );
  }

  // Questions
  const percent = Math.round(((index + 1) / total) * 100);

  const answerButtons = (
    <>
      {index > 0 && (
        <button onClick={handleBack} className="tap-target text-sm text-gray-500 hover:text-gray-900 transition block mb-4">
          ← Previous question
        </button>
      )}
      <div className="flex gap-4">
        <button onClick={() => handleAnswer(true)} className={ANSWER}>Yes</button>
        <button onClick={() => handleAnswer(false)} className={ANSWER}>No</button>
      </div>
    </>
  );

  return (
    <main className={PAGE}>
      {/* pb-60 below md leaves room for the answer bar fixed to the bottom of the screen */}
      <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-6 pt-10 pb-60 md:pb-14 md:px-12">

        <div className="mb-10">
          <div className="flex justify-between text-sm text-gray-500 mb-2">
            <span>Question {index + 1} of {total}</span>
            <span>{percent}%</span>
          </div>
          <div
            className="h-2 bg-gray-200 rounded-full overflow-hidden"
            role="progressbar"
            aria-valuenow={percent}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-label="Assessment progress"
          >
            <div className="h-full bg-[#5C0F8B] transition-all duration-300" style={{ width: `${percent}%` }} />
          </div>
        </div>

        <h2 className="text-2xl md:text-3xl text-gray-900 font-medium leading-snug mb-10" aria-live="polite">
          {questionText(current, questionNames)}
        </h2>

        {/* Inside the card on desktop */}
        <div className="hidden md:block">{answerButtons}</div>
      </div>

      {/*
      Fixed to the bottom of the screen on mobile, within thumb reach.
      pb-24 lifts the buttons clear of the Safe Exit button (fixed bottom-right, z-100),
      which stays on top so it can always be reached.
      */}
      <div className="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-100 px-6 pt-4 pb-24">
        <div className="mx-auto max-w-2xl">{answerButtons}</div>
      </div>
    </main>
  );
}
