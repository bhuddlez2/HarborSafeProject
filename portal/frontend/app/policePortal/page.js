"use client";

import { useState } from "react";
import { lethalityQuestions } from "@/app/lib/lethality-questions";
import { submitAssessment } from "@/app/lib/api";
import { victimSchema, offenderSchema } from "@/app/lib/validation";

export default function AssessmentPage() {
  const [phase, setPhase] = useState("info");
  const [index, setIndex] = useState(0);
  const [answers, setAnswers] = useState({});
  const [victimErrors, setVictimErrors] = useState({});

  // officer info — will be populated from auth session (users + law_enforcement_agents)
  // placeholder values until auth is wired up
  const officerName = "";
  const officerBadge = "";
  const officerAgency = "";

  // victim phase state (VictimFirstName, VictimLastName, VictimSex, VictimDOB, VictimSafePhoneNumber)
  const [victimFirstName, setVictimFirstName] = useState("");
  const [victimLastName, setVictimLastName] = useState("");
  const [victimDob, setVictimDob] = useState("");
  const [victimSex, setVictimSex] = useState("");
  const [victimPhone, setVictimPhone] = useState("");

  // offender phase state (OffenderFirstName, OffenderLastName, OffenderSex, OffenderDOB, OffenderVictimRelationship)
  const [offenderFirstName, setOffenderFirstName] = useState("");
  const [offenderLastName, setOffenderLastName] = useState("");
  const [offenderDob, setOffenderDob] = useState("");
  const [offenderSex, setOffenderSex] = useState("");
  const [offenderRelationship, setOffenderRelationship] = useState("");

  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);

  const total = lethalityQuestions.length;
  const current = lethalityQuestions[index];

  const handleAnswer = async (value) => {
    const updated = { ...answers, [current.id]: value };
    setAnswers(updated);
    if (index < total - 1) {
      setIndex(index + 1);
    } else {
      setPhase("complete");
    }
  };

  const handleBack = () => {
    if (index > 0) setIndex(index - 1);
  };

  // Info phase: officer identity, auto-filled from auth session
  if (phase === "info") {
    return (
      <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">
        <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 py-10 md:px-12 md:py-14">
          <h1 className="text-3xl font-semibold text-gray-900 mb-2">
            Submitting officer
          </h1>
          <p className="text-sm text-gray-500 mb-10">
            This information is pulled from your account and cannot be edited here.
          </p>

          <div className="flex gap-4 mb-6">
            <div className="flex-1">
              <p className="text-sm font-medium text-gray-700 mb-1">Name</p>
              <div className="w-full border-2 border-gray-200 rounded-lg px-4 py-3 bg-gray-50 text-gray-400 select-none">
                {officerName || <span className="italic">Auto-filled from account</span>}
              </div>
            </div>
            <div className="flex-1">
              <p className="text-sm font-medium text-gray-700 mb-1">Badge number</p>
              <div className="w-full border-2 border-gray-200 rounded-lg px-4 py-3 bg-gray-50 text-gray-400 select-none">
                {officerBadge || <span className="italic">Auto-filled from account</span>}
              </div>
            </div>
          </div>

          <div className="mb-10">
            <p className="text-sm font-medium text-gray-700 mb-1">Agency</p>
            <div className="w-full border-2 border-gray-200 rounded-lg px-4 py-3 bg-gray-50 text-gray-400 select-none">
              {officerAgency || <span className="italic">Auto-filled from account</span>}
            </div>
          </div>

          <button
            onClick={() => setPhase("victim")}
            className="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                       hover:bg-gray-700 focus:outline-none
                       focus:ring-4 focus:ring-gray-400 transition"
          >
            Continue
          </button>

        </div>
      </main>
    );
  }

  // Victim phase
  if (phase === "victim") {
    return (
      <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">
        <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 py-10 md:px-12 md:py-14">
          <h1 className="text-3xl font-semibold text-gray-900 mb-10">
            About the victim
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
                className={`w-full border-2 rounded-lg px-4 py-3 text-gray-900
                            focus:outline-none transition
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
                className={`w-full border-2 rounded-lg px-4 py-3 text-gray-900
                            focus:outline-none transition
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
                className={`border-2 rounded-lg px-4 py-3 text-gray-900
                            focus:outline-none transition
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
                className={`w-32 h-12 appearance-none border-2 rounded-lg px-4 py-3 text-gray-900
                            focus:outline-none transition
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
              Safe phone number{" "}
              <span className="text-gray-400 font-normal">(optional)</span>
            </label>
            <input
              type="tel"
              value={victimPhone}
              onChange={(e) => setVictimPhone(e.target.value)}
              className="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                         focus:outline-none focus:border-gray-900 transition"
            />
          </div>

          <div className="flex gap-4">
            <button
              onClick={() => setPhase("info")}
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
                  return;
                }
                setVictimErrors({});
                setPhase("offender");
              }}
              className="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                         hover:bg-gray-700 focus:outline-none
                         focus:ring-4 focus:ring-gray-400 transition"
            >
              Continue
            </button>
          </div>

        </div>
      </main>
    );
  }

  // Offender phase
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
          <div className="flex gap-4">
            <button
              onClick={() => setPhase("offender")}
              className="border-2 border-gray-900 text-gray-900 px-8 py-4 rounded-lg text-lg
                         hover:bg-gray-900 hover:text-white
                         focus:outline-none focus:ring-4 focus:ring-gray-400 transition"
            >
              Back
            </button>
            <button
              onClick={() => setPhase("questions")}
              className="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                         hover:bg-gray-700 focus:outline-none
                         focus:ring-4 focus:ring-gray-400 transition"
            >
              Begin assessment
            </button>
          </div>
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
                    VictimFirstName: victimFirstName,
                    VictimLastName: victimLastName,
                    VictimDOB: victimDob,
                    VictimSex: victimSex,
                    VictimSafePhoneNumber: victimPhone,
                    OffenderFirstName: offenderFirstName,
                    OffenderLastName: offenderLastName,
                    OffenderDOB: offenderDob,
                    OffenderSex: offenderSex,
                    OffenderVictimRelationship: offenderRelationship,
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
                         ${submitting ? "opacity-50 cursor-not-allowed" : ""}`}
            >
              {submitting ? "Submitting..." : "Submit assessment"}
            </button>
          </div>
        </div>
      </main>
    );
  }

  // Submitted phase
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
              setAnswers({});
              setIndex(0);
              setVictimFirstName(""); setVictimLastName(""); setVictimDob(""); setVictimSex(""); setVictimPhone("");
              setOffenderFirstName(""); setOffenderLastName(""); setOffenderDob(""); setOffenderSex(""); setOffenderRelationship("");
              setSubmitError(null);
              setPhase("info");
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
