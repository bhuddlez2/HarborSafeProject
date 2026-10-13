"use client";

/*
AssessmentContent: the civilian lethality assessment, moved here from portal/frontend.

One page, several phases: who the report is for (and, for someone else, whether to stay
anonymous), the submitter's details when they aren't anonymous, the victim, the offender,
the 11 yes/no questions, then review and submit.

Everything entered stays in this component's state until Submit, which sends it all in
one request (lib/assessment.js). Nothing is written to the URL, cookies or browser
storage, autofill is turned off on every field so a shared device doesn't remember
names typed here, and the state is cleared as soon as the submission is accepted.
*/

import { useState, useEffect } from "react";
import Modal from "../components/Modal";
import { OPTIONAL_PLACEHOLDER } from "../components/FormControls";
import { lethalityQuestions } from "../lib/lethality-questions";
import { submitAssessment, fetchRelationshipOptions } from "../lib/assessment";
import { isValidEmail, isValidPhone, isNotFutureDate } from "../lib/validators";

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
optional date gets the same grey word after its label instead.
*/
function Label({ htmlFor, required = false, optional = false, children }) {
  return (
    <label htmlFor={htmlFor} className="block text-sm font-medium text-gray-700 mb-1">
      {children}
      {required && <span className="text-red-700 ml-1" aria-hidden="true">*</span>}
      {optional && <span className="text-gray-400 font-normal ml-1">{OPTIONAL_PLACEHOLDER}</span>}
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

const RELATIONSHIP_LOAD_ERROR = "We couldn't load this list. Please try again.";

export default function AssessmentContent() {
  const [showSafetyModal, setShowSafetyModal] = useState(true);

  // phase: "prescreen", "info", "victim", "offender", "intro", "questions", "complete", "submitted"
  const [phase, setPhase] = useState("prescreen");
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
  const [victimDob, setVictimDob] = useState("");
  const [victimSex, setVictimSex] = useState("");
  const [victimPhone, setVictimPhone] = useState("");

  // offender phase; the relationship is a code from the backend's list ("spouse",
  // "other", ...), plus the typed detail when it's "other"
  const [offenderFirstName, setOffenderFirstName] = useState("");
  const [offenderLastName, setOffenderLastName] = useState("");
  const [offenderDob, setOffenderDob] = useState("");
  const [offenderSex, setOffenderSex] = useState("");
  const [offenderRelationship, setOffenderRelationship] = useState("");
  const [offenderRelationshipOther, setOffenderRelationshipOther] = useState("");

  // The options come from GET /api/public/relationships (no copy kept here).
  // null while loading; an error message if they couldn't be loaded.
  const [relationshipOptions, setRelationshipOptions] = useState(null);
  const [relationshipOptionsError, setRelationshipOptionsError] = useState(null);

  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);

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
  const phoneError = phone.trim() && !isValidPhone(phone.trim()) ? "Enter a valid phone number." : null;
  const victimDobError = !isNotFutureDate(victimDob) ? "Date of birth can't be in the future." : null;
  const victimPhoneError = victimPhone.trim() && !isValidPhone(victimPhone.trim()) ? "Enter a valid phone number." : null;
  const offenderDobError = !isNotFutureDate(offenderDob) ? "Date of birth can't be in the future." : null;

  const total = lethalityQuestions.length;
  const current = lethalityQuestions[index];
  const identified = forWhom === "other" && anonymous === false;

  // Records the answer and moves on, to review after the last question.
  const handleAnswer = (value) => {
    setAnswers({ ...answers, [current.id]: value });

    if (index < total - 1) {
      setIndex(index + 1);
    } else {
      setPhase("complete");
    }
  };

  const handleBack = () => {
    if (index > 0) setIndex(index - 1);
  };

  // Clears everything that was entered. Run as soon as a submission is accepted, so
  // the details don't stay in the page, and again for "Start new assessment".
  const clearEntries = () => {
    setAnswers({});
    setIndex(0);
    setForWhom(null);
    setAnonymous(null);
    setFirstName(""); setLastName(""); setEmail(""); setPhone("");
    setVictimFirstName(""); setVictimLastName(""); setVictimDob(""); setVictimSex(""); setVictimPhone("");
    setOffenderFirstName(""); setOffenderLastName(""); setOffenderDob(""); setOffenderSex("");
    setOffenderRelationship(""); setOffenderRelationshipOther("");
    setSubmitError(null);
  };

  const handleSubmit = async () => {
    setSubmitting(true);
    setSubmitError(null);

    try {
      await submitAssessment({
        forWhom,
        anonymous,
        submitter: { firstName, lastName, email, phone },
        victim: { firstName: victimFirstName, lastName: victimLastName, dob: victimDob, sex: victimSex, phone: victimPhone },
        offender: {
          firstName: offenderFirstName,
          lastName: offenderLastName,
          dob: offenderDob,
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
        setSubmitError("Too many submissions just now. Please wait a minute and try again.");
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

        {/* Confidentiality notice, shown each time the assessment is opened, before the form begins */}
        {showSafetyModal && (
          <Modal title="Confidentiality notice" subtitle="Before you begin" onClose={() => setShowSafetyModal(false)}>
            <div className="px-6 py-5">
              <p className="text-sm text-gray-600 leading-relaxed mb-5">
                Information you provide in this assessment is kept confidential and used solely to
                connect you with support services.
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
                    <p className="text-sm font-semibold text-gray-900 mb-0.5">Your information is protected</p>
                    <p className="text-xs text-gray-600 leading-relaxed">
                      Responses are treated as confidential. We will not share your information
                      without your consent except as required by law.
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
          <h1 className={`${HEADING} mb-8`}>Before we begin</h1>

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

          {/* Continue: straight away for "self"; for "other" once anonymity is answered */}
          {(forWhom === "self" || (forWhom === "other" && anonymous !== null)) && (
            <button onClick={() => setPhase(identified ? "info" : "victim")} className={PRIMARY}>
              Continue
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
          <h1 className={`${HEADING} mb-10`}>Your information</h1>

          <div className="flex flex-col sm:flex-row gap-4 mb-6">
            <div className="flex-1">
              <Label htmlFor="submitter-first-name" required>First name</Label>
              <input id="submitter-first-name" type="text" value={firstName} onChange={(e) => setFirstName(e.target.value)}
                maxLength={50} autoComplete="off" aria-required="true" className={INPUT} />
            </div>
            <div className="flex-1">
              <Label htmlFor="submitter-last-name" required>Last name</Label>
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
            <Label htmlFor="submitter-phone">Phone number</Label>
            <input id="submitter-phone" type="tel" value={phone} onChange={(e) => setPhone(e.target.value)}
              placeholder={OPTIONAL_PLACEHOLDER} maxLength={20} autoComplete="off"
              aria-invalid={!!phoneError} aria-describedby={phoneError ? "submitter-phone-error" : undefined} className={INPUT} />
            <FieldError id="submitter-phone-error">{phoneError}</FieldError>
          </div>

          <div className="flex gap-4">
            <button onClick={() => setPhase("prescreen")} className={SECONDARY}>Back</button>
            <button
              onClick={() => setPhase("victim")}
              disabled={!firstName.trim() || !lastName.trim() || !!emailError || !!phoneError}
              className={PRIMARY}
            >
              Continue
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
          <h1 className={`${HEADING} mb-10`}>{forWhom === "self" ? "Your information" : "About the victim"}</h1>

          <div className="flex flex-col sm:flex-row gap-4 mb-6">
            <div className="flex-1">
              <Label htmlFor="victim-first-name" required>First name</Label>
              <input id="victim-first-name" type="text" value={victimFirstName} onChange={(e) => setVictimFirstName(e.target.value)}
                maxLength={50} autoComplete="off" aria-required="true" className={INPUT} />
            </div>
            <div className="flex-1">
              <Label htmlFor="victim-last-name" required>Last name</Label>
              <input id="victim-last-name" type="text" value={victimLastName} onChange={(e) => setVictimLastName(e.target.value)}
                maxLength={50} autoComplete="off" aria-required="true" className={INPUT} />
            </div>
          </div>

          <div className="flex flex-wrap gap-4 mb-6">
            <div>
              <Label htmlFor="victim-dob" required>Date of birth</Label>
              <input id="victim-dob" type="date" value={victimDob} onChange={(e) => setVictimDob(e.target.value)}
                autoComplete="off" aria-required="true"
                aria-invalid={!!victimDobError} aria-describedby={victimDobError ? "victim-dob-error" : undefined}
                className="border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900 focus:outline-none focus:border-[#5C0F8B] transition" />
              <FieldError id="victim-dob-error">{victimDobError}</FieldError>
            </div>
            <div>
              <Label htmlFor="victim-sex" required>Sex</Label>
              <SexSelect id="victim-sex" value={victimSex} onChange={setVictimSex} />
            </div>
          </div>

          <div className="mb-10">
            <Label htmlFor="victim-phone" required>{forWhom === "self" ? "Phone number" : "Safe phone number"}</Label>
            {/* Required so Harbor Safe can follow up after the assessment. */}
            <input id="victim-phone" type="tel" value={victimPhone} onChange={(e) => setVictimPhone(e.target.value)}
              maxLength={20} autoComplete="off" aria-required="true"
              aria-invalid={!!victimPhoneError} aria-describedby={victimPhoneError ? "victim-phone-error" : undefined} className={INPUT} />
            <FieldError id="victim-phone-error">{victimPhoneError}</FieldError>
          </div>

          <div className="flex gap-4">
            <button onClick={() => setPhase(identified ? "info" : "prescreen")} className={SECONDARY}>Back</button>
            <button
              onClick={() => setPhase("offender")}
              disabled={!victimFirstName.trim() || !victimLastName.trim() || !victimDob || !!victimDobError || !victimSex
                || !victimPhone.trim() || !!victimPhoneError}
              className={PRIMARY}
            >
              Continue
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
          <h1 className={`${HEADING} mb-10`}>About the offender</h1>

          <div className="flex flex-col sm:flex-row gap-4 mb-6">
            <div className="flex-1">
              <Label htmlFor="offender-first-name" required>First name</Label>
              <input id="offender-first-name" type="text" value={offenderFirstName} onChange={(e) => setOffenderFirstName(e.target.value)}
                maxLength={50} autoComplete="off" aria-required="true" className={INPUT} />
            </div>
            <div className="flex-1">
              <Label htmlFor="offender-last-name" required>Last name</Label>
              <input id="offender-last-name" type="text" value={offenderLastName} onChange={(e) => setOffenderLastName(e.target.value)}
                maxLength={50} autoComplete="off" aria-required="true" className={INPUT} />
            </div>
          </div>

          <div className="flex flex-wrap gap-4 mb-6">
            <div>
              <Label htmlFor="offender-dob" optional>Date of birth</Label>
              <input id="offender-dob" type="date" value={offenderDob} onChange={(e) => setOffenderDob(e.target.value)}
                autoComplete="off"
                aria-invalid={!!offenderDobError} aria-describedby={offenderDobError ? "offender-dob-error" : undefined}
                className="border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900 focus:outline-none focus:border-[#5C0F8B] transition" />
              <FieldError id="offender-dob-error">{offenderDobError}</FieldError>
            </div>
            <div>
              <Label htmlFor="offender-sex" required>Sex</Label>
              <SexSelect id="offender-sex" value={offenderSex} onChange={setOffenderSex} />
            </div>
          </div>

          <div className="mb-10">
            <Label htmlFor="offender-relationship" required>Relationship to victim</Label>
            {relationshipOptionsError ? (
              <div className="flex items-center gap-3">
                <p role="alert" className="text-sm text-red-700">{relationshipOptionsError}</p>
                <button
                  type="button"
                  onClick={loadRelationshipOptions}
                  className="text-sm font-semibold text-[#5C0F8B] underline focus:outline-none focus:ring-2 focus:ring-[#5C0F8B]/40 rounded"
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
                <Label htmlFor="offender-relationship-other" required>Please specify</Label>
                <input id="offender-relationship-other" type="text" value={offenderRelationshipOther}
                  onChange={(e) => setOffenderRelationshipOther(e.target.value)}
                  maxLength={50} autoComplete="off" aria-required="true" className={INPUT} />
              </div>
            )}
          </div>

          <div className="flex gap-4">
            <button onClick={() => setPhase("victim")} className={SECONDARY}>Back</button>
            <button
              onClick={() => setPhase("intro")}
              disabled={!offenderFirstName.trim() || !offenderLastName.trim() || !offenderSex || !!offenderDobError
                || !offenderRelationship || (offenderRelationship === "other" && !offenderRelationshipOther.trim())}
              className={PRIMARY}
            >
              Continue
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
            Nothing is saved or transmitted. Closing the tab clears the results.
          </p>
          <button onClick={() => setPhase("questions")} className={PRIMARY}>Begin assessment</button>
        </div>
      </main>
    );
  }

  // Review and submit
  if (phase === "complete") {
    return (
      <main className={PAGE}>
        <div className={CARD}>
          <h1 className={`${HEADING} mb-6`}>Review &amp; submit</h1>

          <div className="divide-y divide-gray-100 mb-10">
            <div className="py-4">
              <p className="text-sm font-medium text-gray-500 mb-1">Victim</p>
              <p className="text-gray-900">{victimFirstName} {victimLastName}</p>
            </div>
            <div className="py-4">
              <p className="text-sm font-medium text-gray-500 mb-1">Offender</p>
              <p className="text-gray-900">{offenderFirstName} {offenderLastName}</p>
            </div>
            {identified && (
              <div className="py-4">
                <p className="text-sm font-medium text-gray-500 mb-1">Reported by</p>
                <p className="text-gray-900">{firstName} {lastName}</p>
              </div>
            )}
            <div className="py-4">
              <p className="text-sm font-medium text-gray-500 mb-1">Questions answered</p>
              <p className="text-gray-900">{total} of {total}</p>
            </div>
          </div>

          {submitError && <p role="alert" className="text-red-700 text-sm mb-4">{submitError}</p>}

          <div className="flex gap-4">
            <button onClick={() => { setIndex(total - 1); setPhase("questions"); }} className={SECONDARY}>Back</button>
            <button onClick={handleSubmit} disabled={submitting} className={PRIMARY}>
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
        <div className={CARD}>
          <h1 className={`${HEADING} mb-6`}>Assessment submitted</h1>
          <p className="text-gray-700 text-lg mb-10 leading-relaxed">
            The assessment has been saved successfully.
          </p>
          <button onClick={() => { clearEntries(); setPhase("prescreen"); }} className={PRIMARY}>
            Start new assessment
          </button>
        </div>
      </main>
    );
  }

  // Questions
  const percent = Math.round(((index + 1) / total) * 100);

  const answerButtons = (
    <>
      {index > 0 && (
        <button onClick={handleBack} className="text-sm text-gray-500 hover:text-gray-900 transition block mb-4">
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
          {current.text}
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
