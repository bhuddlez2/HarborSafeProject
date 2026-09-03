"use client";

import { useState, useEffect, startTransition } from "react";
import { lethalityQuestions } from "@/app/lib/lethality-questions";
import { submitAssessment } from "@/app/lib/api";
import { submitterSchema, victimSchema, offenderSchema } from "@/app/lib/validation";
const API_URL = process.env.NEXT_PUBLIC_API_URL

export default function AssessmentPage() {
  const [showSafetyModal, setShowSafetyModal] = useState(false);

  useEffect(() => {
    startTransition(() => setShowSafetyModal(true));
  }, []);

  useEffect(() => {
    document.body.style.overflow = showSafetyModal ? "hidden" : "";
    return () => { document.body.style.overflow = ""; };
  }, [showSafetyModal]);

  // phase: cycles through "prescreen", "intro", "questions", and "complete"
  const [phase, setPhase] = useState("prescreen");
  // index: tracks which question is displayed. called in handleanswer and handleback and handlereset
  const [index, setIndex] = useState(0);
  // answers: stores responses as { questionId: boolean }, called in handleanswer and handlereset
  const [answers, setAnswers] = useState({});
  // stores validation errors for the victim phase, called when validating victim info
  const [victimErrors, setVictimErrors] = useState({});

  // prescreen state
  // anonymous stays null until forWhom is answered, which controls whether it renders
  const [forWhom, setForWhom] = useState(null);
  const [anonymous, setAnonymous] = useState(null);

  // info phase state (SubmitterInfo)
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");

  // victim phase state (VictimInfo)
  const [victimFirstName, setVictimFirstName] = useState("");
  const [victimLastName, setVictimLastName] = useState("");
  const [victimDob, setVictimDob] = useState("");
  const [victimSex, setVictimSex] = useState("");
  const [victimPhone, setVictimPhone] = useState("");

  // offender phase state (OffenderInfo)
  const [offenderFirstName, setOffenderFirstName] = useState("");
  const [offenderLastName, setOffenderLastName] = useState("");
  const [offenderDob, setOffenderDob] = useState("");
  const [offenderSex, setOffenderSex] = useState("");
  const [offenderRelationship, setOffenderRelationship] = useState("");


  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);

  // total question count and current question
  const total = lethalityQuestions.length;
  const current = lethalityQuestions[index];

  // below functions are called throughout the program alongside button presses to handle user interaction and program flow

  // handles the answer, recording it and advancing
  const handleAnswer = async (value) => {
    const updated = { ...answers, [current.id]: value };
    setAnswers(updated);

    if (index < total - 1) {
        setIndex(index + 1);
    } else {
        setPhase("complete");
    }
};

  // sets the user back one question, until the first
  const handleBack = () => {
    if (index > 0) setIndex(index - 1);
  };



  // Prescreen phase: collects who the report is for and anonymity preference
  if (phase === "prescreen") {
    return (
      <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">

        {/* Safety / confidentiality modal, shown on every visit before the form begins */}
        {showSafetyModal && (
          <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="safety-modal-heading"
          >
            <div className="bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden">

              {/* Header */}
              <div className="bg-gray-900 px-6 py-5 text-center">
                <p className="text-xs font-semibold tracking-widest uppercase text-gray-400 mb-0.5">Before you begin</p>
                <h2 id="safety-modal-heading" className="text-xl font-bold text-white">Confidentiality notice</h2>
              </div>

              {/* Body */}
              <div className="px-6 py-5">
                <p className="text-sm text-gray-600 leading-relaxed mb-5">
                  Information you provide in this assessment is kept confidential and used solely to
                  connect you with support services.
                </p>

                <div className="space-y-4 mb-5">

                  {/* Confidentiality item */}
                  <div className="flex gap-3 items-start">
                    <div className="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
                      <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-gray-700 stroke-2 fill-none">
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

                  {/* Mandatory reporting item */}
                  <div className="flex gap-3 items-start">
                    <div className="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
                      <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-amber-600 stroke-2 fill-none">
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

                  {/* Browse safely item */}
                  <div className="flex gap-3 items-start">
                    <div className="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
                      <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-gray-700 stroke-2 fill-none">
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

                {/* Action button */}
                <button
                  onClick={() => setShowSafetyModal(false)}
                  className="w-full bg-gray-900 text-white py-2.5 rounded-lg font-semibold text-sm
                             hover:bg-gray-700 focus:outline-none focus:ring-4 focus:ring-gray-400 transition"
                >
                  I understand, continue
                </button>
              </div>

            </div>
          </div>
        )}

        <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 py-10 md:px-12 md:py-14">
          <h1 className="text-3xl font-semibold text-gray-900 mb-8">
            Before we begin
          </h1>

          {/* Question 1: who is this for */}
          <p className="text-gray-700 text-lg font-medium mb-4">
            Who is this report for?
          </p>
          <div className="flex gap-4 mb-10">
            <button
              onClick={() => setForWhom("self")}
              className={`flex-1 py-4 rounded-lg text-lg border-2 transition
                focus:outline-none focus:ring-4 focus:ring-gray-400
                ${forWhom === "self"
                  ? "bg-gray-900 text-white border-gray-900"
                  : "border-gray-900 text-gray-900 hover:bg-gray-900 hover:text-white"}`}
            >
              Myself
            </button>
            <button
              onClick={() => setForWhom("other")}
              className={`flex-1 py-4 rounded-lg text-lg border-2 transition
                focus:outline-none focus:ring-4 focus:ring-gray-400
                ${forWhom === "other"
                  ? "bg-gray-900 text-white border-gray-900"
                  : "border-gray-900 text-gray-900 hover:bg-gray-900 hover:text-white"}`}
            >
              Someone else
            </button>
          </div>

          {/* Question 2: anonymity, only shown when assessing someone else */}
          {forWhom === "other" && (
            <div className="mb-10">
              <p className="text-gray-700 text-lg font-medium mb-4">
                Would you like to remain anonymous?
              </p>
              <div className="flex gap-4">
                <button
                  onClick={() => setAnonymous(true)}
                  className={`flex-1 py-4 rounded-lg text-lg border-2 transition
                    focus:outline-none focus:ring-4 focus:ring-gray-400
                    ${anonymous === true
                      ? "bg-gray-900 text-white border-gray-900"
                      : "border-gray-900 text-gray-900 hover:bg-gray-900 hover:text-white"}`}
                >
                  Yes
                </button>
                <button
                  onClick={() => setAnonymous(false)}
                  className={`flex-1 py-4 rounded-lg text-lg border-2 transition
                    focus:outline-none focus:ring-4 focus:ring-gray-400
                    ${anonymous === false
                      ? "bg-gray-900 text-white border-gray-900"
                      : "border-gray-900 text-gray-900 hover:bg-gray-900 hover:text-white"}`}
                >
                  No
                </button>
              </div>
            </div>
          )}

          {/* Continue: for "self" appears immediately; for "other" requires anonymity answer */}
          {(forWhom === "self" || (forWhom === "other" && anonymous !== null)) && (
            <button
              onClick={() => setPhase(
                forWhom === "other" && !anonymous ? "info" : "victim"
              )}
              className="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                         hover:bg-gray-700 focus:outline-none
                         focus:ring-4 focus:ring-gray-400 transition"
            >
              Continue
            </button>
          )}

        </div>
      </main>
    );
  }

  // Info phase: collects identifying information when anonymous === false
  if (phase === "info") {
    return (
      <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">
        <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 py-10 md:px-12 md:py-14">
          <h1 className="text-3xl font-semibold text-gray-900 mb-10">
            Your information
          </h1>

          <div className="flex gap-4 mb-6">
            <div className="flex-1">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                First name
              </label>
              <input
                type="text"
                value={firstName}
                onChange={(e) => setFirstName(e.target.value)}
                className="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                           focus:outline-none focus:border-gray-900 transition"
              />
            </div>
            <div className="flex-1">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Last name
              </label>
              <input
                type="text"
                value={lastName}
                onChange={(e) => setLastName(e.target.value)}
                className="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                           focus:outline-none focus:border-gray-900 transition"
              />
            </div>
          </div>

          <div className="mb-6">
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Email{" "}
              <span className="text-gray-400 font-normal">(optional)</span>
            </label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                         focus:outline-none focus:border-gray-900 transition"
            />
          </div>

          <div className="mb-10">
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Phone number{" "}
              <span className="text-gray-400 font-normal">(optional)</span>
            </label>
            <input
              type="tel"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              className="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                         focus:outline-none focus:border-gray-900 transition"
            />
          </div>

          <div className="flex gap-4">
            <button
              onClick={() => setPhase("prescreen")}
              className="border-2 border-gray-900 text-gray-900 px-8 py-4 rounded-lg text-lg
                         hover:bg-gray-900 hover:text-white
                         focus:outline-none focus:ring-4 focus:ring-gray-400 transition"
            >
              Back
            </button>
            <button
              onClick={() => setPhase("victim")}
              disabled={!firstName.trim() || !lastName.trim()}
              className="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                         hover:bg-gray-700 focus:outline-none
                         focus:ring-4 focus:ring-gray-400 transition
                         disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-gray-900"
            >
              Continue
            </button>
          </div>

        </div>
      </main>
    );
  }

  // Victim phase: collects identifying information about the subject of abuse
  if (phase === "victim") {
    return (
      <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">
        <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 py-10 md:px-12 md:py-14">
          <h1 className="text-3xl font-semibold text-gray-900 mb-10">
            {forWhom === "self" ? "Your information" : "About the victim"}
          </h1>

          <div className="flex gap-4 mb-6">
            <div className="flex-1">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                First name
              </label>
              <input
                type="text"
                value={victimFirstName}
                onChange={(e) => setVictimFirstName(e.target.value)}
                className={`w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                           focus:outline-none focus:border-gray-900 transition
                           ${victimErrors.victimFirstName ? "border-red-500" : "border-gray-300 focus:border-gray-900"}`}
              />
              {victimErrors.victimFirstName && (
              <p className="text-sm text-red-600 mt-1">{victimErrors.victimFirstName[0]}</p>
              )}
            </div>
            <div className="flex-1">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Last name
              </label>
              <input
                type="text"
                value={victimLastName}
                onChange={(e) => setVictimLastName(e.target.value)}
                className={`w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                           focus:outline-none focus:border-gray-900 transition
                           ${victimErrors.victimLastName ? "border-red-500" : "border-gray-300 focus:border-gray-900"}`}
              />
              {victimErrors.victimLastName && (
              <p className="text-sm text-red-600 mt-1">{victimErrors.victimLastName[0]}</p>
              )}
            </div>
          </div>

          <div className="flex gap-4 mb-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Date of birth
              </label>
              <input
                type="date"
                value={victimDob}
                onChange={(e) => setVictimDob(e.target.value)}
                className={`border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                           focus:outline-none focus:border-gray-900 transition
                           ${victimErrors.victimDob ? "border-red-500" : "border-gray-300 focus:border-gray-900"}`}
              />
              {victimErrors.victimDob && (
              <p className="text-sm text-red-600 mt-1">{victimErrors.victimDob[0]}</p>
              )}
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Sex
              </label>
              <select
                value={victimSex}
                onChange={(e) => setVictimSex(e.target.value)}
                className={`w-32 h-12 appearance-none border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                           focus:outline-none focus:border-gray-900 transition
                           ${victimErrors.victimSex ? "border-red-500" : "border-gray-300 focus:border-gray-900"}`}
              >
                <option value="">Select</option>
                <option value="M">Male</option>
                <option value="F">Female</option>
                <option value="O">Other</option>
              </select>
              {victimErrors.victimSex && (
              <p className="text-sm text-red-600 mt-1">{victimErrors.victimSex[0]}</p>
              )}
            </div>
          </div>

          <div className="mb-10">
            <label className="block text-sm font-medium text-gray-700 mb-1">
              {forWhom === "self" ? "Phone number" : "Safe phone number"}{" "}
              <span className="text-gray-400 font-normal">(optional)</span>
            </label>
            <input
              type="tel"
              value={victimPhone}
              onChange={(e) => setVictimPhone(e.target.value)}
              className={`w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                         focus:outline-none focus:border-gray-900 transition
                         ${victimErrors.victimPhone ? "border-red-500" : "border-gray-300 focus:border-gray-900"}`}
            />
            {victimErrors.victimPhone && (
              <p className="text-sm text-red-600 mt-1">{victimErrors.victimPhone[0]}</p>
              )}
          </div>

          <div className="flex gap-4">
            <button
              onClick={() => setPhase(forWhom === "other" && anonymous === false ? "info" : "prescreen")}
              className="border-2 border-gray-900 text-gray-900 px-8 py-4 rounded-lg text-lg
                         hover:bg-gray-900 hover:text-white
                         focus:outline-none focus:ring-4 focus:ring-gray-400 transition"
            >
              Back
            </button>
            <button
              onClick={() => {
                const result = victimSchema.safeParse({
                  victimFirstName,
                  victimLastName,
                  victimDob,
                  victimSex,
                  victimPhone,
                });

                if (!result.success) {
                  setVictimErrors(result.error.flatten().fieldErrors);
                  return; // stop here, don't advance phase
                }

                setVictimErrors({});
                setPhase("offender");
              }}
              className="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                         hover:bg-gray-700 focus:outline-none
                         focus:ring-4 focus:ring-gray-400 transition
                         disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-gray-900"
            >
              Continue
            </button>
          </div>

        </div>
      </main>
    );
  }

  // Offender phase: collects identifying information about the abuser
  if (phase === "offender") {
    return (
      <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">
        <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 py-10 md:px-12 md:py-14">
          <h1 className="text-3xl font-semibold text-gray-900 mb-10">
            About the offender
          </h1>

          <div className="flex gap-4 mb-6">
            <div className="flex-1">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                First name
              </label>
              <input
                type="text"
                value={offenderFirstName}
                onChange={(e) => setOffenderFirstName(e.target.value)}
                className="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                           focus:outline-none focus:border-gray-900 transition"
              />
            </div>
            <div className="flex-1">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Last name
              </label>
              <input
                type="text"
                value={offenderLastName}
                onChange={(e) => setOffenderLastName(e.target.value)}
                className="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                           focus:outline-none focus:border-gray-900 transition"
              />
            </div>
          </div>

          <div className="flex gap-4 mb-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Date of birth{" "}
                <span className="text-gray-400 font-normal">(optional)</span>
              </label>
              <input
                type="date"
                value={offenderDob}
                onChange={(e) => setOffenderDob(e.target.value)}
                className="border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                           focus:outline-none focus:border-gray-900 transition"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Sex
              </label>
              <select
                value={offenderSex}
                onChange={(e) => setOffenderSex(e.target.value)}
                className="w-32 h-12 appearance-none border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                           focus:outline-none focus:border-gray-900 transition"
              >
                <option value="">Select</option>
                <option value="M">Male</option>
                <option value="F">Female</option>
                <option value="O">Other</option>
              </select>
            </div>
          </div>

          <div className="mb-10">
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Relationship to victim
            </label>
            <input
              type="text"
              value={offenderRelationship}
              onChange={(e) => setOffenderRelationship(e.target.value)}
              className="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                         focus:outline-none focus:border-gray-900 transition"
            />
          </div>

          <div className="flex gap-4">
            <button
              onClick={() => setPhase("victim")}
              className="border-2 border-gray-900 text-gray-900 px-8 py-4 rounded-lg text-lg
                         hover:bg-gray-900 hover:text-white
                         focus:outline-none focus:ring-4 focus:ring-gray-400 transition"
            >
              Back
            </button>
            <button
              onClick={() => setPhase("intro")}
              disabled={!offenderFirstName.trim() || !offenderLastName.trim() || !offenderSex || !offenderRelationship.trim()}
              className="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                         hover:bg-gray-700 focus:outline-none
                         focus:ring-4 focus:ring-gray-400 transition
                         disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-gray-900"
            >
              Continue
            </button>
          </div>

        </div>
      </main>
    );
  }

  // Intro phase
  if (phase === "intro") {
    // min-h-screen scales to screen height, and then color is set using bg
    return (
      <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">
        <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 py-10 md:px-12 md:py-14">
          <h1 className="text-3xl font-semibold text-gray-900 mb-6">
            Lethality Assessment
          </h1>
          <p className="text-gray-700 text-lg mb-4 leading-relaxed">
            This screen contains {total} yes/no questions. Ask each question exactly as written.
          </p>
          <p className="text-gray-700 text-lg mb-10 leading-relaxed">
            Nothing is saved or transmitted. Closing the tab clears the results.
          </p>
          <button
            onClick={() => setPhase("questions")}
            className="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                       hover:bg-gray-700 focus:outline-none
                       focus:ring-4 focus:ring-gray-400 transition"
          >
            Begin assessment
          </button>
        </div>
      </main>
    );
  }

  // Complete phase
  if (phase === "complete") {
    return (
      <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">
        <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 py-10 md:px-12 md:py-14">
          <h1 className="text-3xl font-semibold text-gray-900 mb-6">
            Review &amp; submit
          </h1>

          <div className="divide-y divide-gray-100 mb-10">
            <div className="py-4">
              <p className="text-sm font-medium text-gray-500 mb-1">Victim</p>
              <p className="text-gray-900">{victimFirstName} {victimLastName}</p>
            </div>
            <div className="py-4">
              <p className="text-sm font-medium text-gray-500 mb-1">Offender</p>
              <p className="text-gray-900">{offenderFirstName} {offenderLastName}</p>
            </div>
            {forWhom === "other" && !anonymous && (
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

          {submitError && (
              <p className="text-red-600 text-sm mb-4">{submitError}</p>
          )}

          <div className="flex gap-4">
            <button
              onClick={() => { setIndex(total - 1); setPhase("questions"); }}
              className="border-2 border-gray-900 text-gray-900 px-8 py-4 rounded-lg text-lg
                         hover:bg-gray-900 hover:text-white
                         focus:outline-none focus:ring-4 focus:ring-gray-400 transition"
            >
              Back
            </button>
            <button
              onClick={async () => {
                  setSubmitting(true);
                  setSubmitError(null);
                  try {
                      await submitAssessment({
                          anonymous,
                          forWhom,
                          firstName,
                          lastName,
                          email,
                          phone,
                          victimFirstName,
                          victimLastName,
                          victimDob,
                          victimSex,
                          victimPhone,
                          offenderFirstName,
                          offenderLastName,
                          offenderDob,
                          offenderSex,
                          offenderRelationship,
                          answers,
                      });
                      setPhase("submitted");
                  } catch (err) {
                      setSubmitError(err.message);
                  } finally {
                      setSubmitting(false);
                  }
              }}
              disabled={submitting}
              className={`bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                        hover:bg-gray-700 focus:outline-none
                        focus:ring-4 focus:ring-gray-400 transition
                        ${submitting ? 'opacity-50 cursor-not-allowed' : ''}`}
            >
              {submitting ? 'Submitting...' : 'Submit assessment'}
          </button>
          </div>
        </div>
      </main>
    );
  }

  // Submit Phase
  if (phase === "submitted") {
    return (
        <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">
            <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 py-10 md:px-12 md:py-14">
                <h1 className="text-3xl font-semibold text-gray-900 mb-6">
                    Assessment submitted
                </h1>
                <p className="text-gray-700 text-lg mb-10 leading-relaxed">
                    The assessment has been saved successfully.
                </p>
                <button
                    onClick={() => {
                        // reset all state
                        setAnswers({});
                        setIndex(0);
                        setForWhom(null);
                        setAnonymous(null);
                        setFirstName(""); setLastName(""); setEmail(""); setPhone("");
                        setVictimFirstName(""); setVictimLastName(""); setVictimDob(""); setVictimSex(""); setVictimPhone("");
                        setOffenderFirstName(""); setOffenderLastName(""); setOffenderDob(""); setOffenderSex(""); setOffenderRelationship("");
                        setSubmitError(null);
                        setPhase("prescreen");
                    }}
                    className="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                               hover:bg-gray-700 focus:outline-none
                               focus:ring-4 focus:ring-gray-400 transition"
                >
                    Start new assessment
                </button>
            </div>
        </main>
    );
}

  // Questions phase
  const percent = Math.round(((index + 1) / total) * 100);

  return (
    <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">
      <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 pt-10 pb-36 md:pb-14 md:px-12">

        {/* Progress bar */}
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
          >
            <div
              className="h-full bg-gray-900 transition-all duration-300"
              style={{ width: `${percent}%` }}
            />
          </div>
        </div>

        {/* Question text */}
        <h2
          className="text-2xl md:text-3xl text-gray-900 font-medium leading-snug mb-10"
          aria-live="polite"
        >
          {current.text}
        </h2>

        {/* Back + Yes/No, inside card on desktop */}
        <div className="hidden md:block">
          {index > 0 && (
            <button
              onClick={handleBack}
              className="text-sm text-gray-500 hover:text-gray-900 transition block mb-4"
            >
              ← Previous question
            </button>
          )}
          <div className="flex gap-4">
            <button
              onClick={() => handleAnswer(true)}
              className="flex-1 border-2 border-gray-900 text-gray-900 text-lg py-4 rounded-lg
                         hover:bg-gray-900 hover:text-white
                         focus:outline-none focus-visible:ring-4 focus-visible:ring-gray-400 transition"
            >
              Yes
            </button>
            <button
              onClick={() => handleAnswer(false)}
              className="flex-1 border-2 border-gray-900 text-gray-900 text-lg py-4 rounded-lg
                         hover:bg-gray-900 hover:text-white
                         focus:outline-none focus-visible:ring-4 focus-visible:ring-gray-400 transition"
            >
              No
            </button>
          </div>
        </div>

      </div>

      {/* Back + Yes/No, fixed to bottom on mobile only */}
      <div className="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-100 px-6 pb-10 pt-4">
        <div className="mx-auto max-w-2xl">
          {index > 0 && (
            <button
              onClick={handleBack}
              className="text-sm text-gray-500 hover:text-gray-900 transition block mb-4"
            >
              ← Previous question
            </button>
          )}
          <div className="flex gap-4">
            <button
              onClick={() => handleAnswer(true)}
              className="flex-1 border-2 border-gray-900 text-gray-900 text-lg py-4 rounded-lg
                         hover:bg-gray-900 hover:text-white
                         focus:outline-none focus-visible:ring-4 focus-visible:ring-gray-400 transition"
            >
              Yes
            </button>
            <button
              onClick={() => handleAnswer(false)}
              className="flex-1 border-2 border-gray-900 text-gray-900 text-lg py-4 rounded-lg
                         hover:bg-gray-900 hover:text-white
                         focus:outline-none focus-visible:ring-4 focus-visible:ring-gray-400 transition"
            >
              No
            </button>
          </div>
        </div>
      </div>

    </main>
  );
}
