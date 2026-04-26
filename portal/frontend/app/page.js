"use client";

import { useState } from "react";
import { lethalityQuestions } from "@/app/lib/lethality-questions";

export default function AssessmentPage() {
  // phase: cycles through "prescreen", "intro", "questions", and "complete"
  const [phase, setPhase] = useState("prescreen");
  // index: tracks which question is displayed. called in handleanswer and handleback and handlereset
  const [index, setIndex] = useState(0);
  // answers: stores responses as { questionId: boolean }, called in handleanswer and handlereset
  const [answers, setAnswers] = useState({});

  // prescreen state: forWhom is "self" or "other", anonymous is true or false
  // anonymous stays null until forWhom is answered, which controls whether it renders
  const [forWhom, setForWhom] = useState(null);
  const [anonymous, setAnonymous] = useState(null);

  // info phase state: collected only when anonymous === false
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [relationship, setRelationship] = useState("");
  const [phone, setPhone] = useState("");

  // total question count and current question
  const total = lethalityQuestions.length;
  const current = lethalityQuestions[index];

  // below functions are called throughout the program alongside button presses to handle user interaction and program flow

  // handles the answer, recording it and advancing
  const handleAnswer = (value) => {
    // think of updated as a copy of current answers that modifies the set
    // Update answers with the current response, and overwrites answer as well, allowing back navigation
    // in react we need to create a new object to trigger re-render, which is why we take the old answers
    const updated = { ...answers, [current.id]: value };
    // calls the state setter to update answers with the new object
    setAnswers(updated);
    // handles question navigation, advancing until completion
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

  // Reset handler: empties answers, resets index, clears prescreen and info, returns to prescreen
  const handleReset = () => {
    setAnswers({});
    setIndex(0);
    setForWhom(null);
    setAnonymous(null);
    setFirstName("");
    setLastName("");
    setRelationship("");
    setPhone("");
    setPhase("prescreen");
  };

  // Prescreen phase: collects who the assessment is for and anonymity preference
  if (phase === "prescreen") {
    return (
      <main className="min-h-screen bg-white">
        <div className="mx-auto max-w-2xl px-6 py-16">
          <h1 className="text-3xl font-semibold text-gray-900 mb-8">
            Before we begin
          </h1>

          {/* Question 1: who is this for */}
          <p className="text-gray-700 text-lg font-medium mb-4">
            Who is this assessment for?
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

          {/* Question 2: anonymity — only appears after question 1 is answered */}
          {forWhom !== null && (
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

          {/* Continue — only appears after both questions are answered */}
          {forWhom !== null && anonymous !== null && (
            <button
              onClick={() => setPhase(!anonymous ? "info" : "intro")}
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
      <main className="min-h-screen bg-white">
        <div className="mx-auto max-w-2xl px-6 py-16">
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
              Relationship to subject{" "}
              <span className="text-gray-400 font-normal">(optional)</span>
            </label>
            <input
              type="text"
              value={relationship}
              onChange={(e) => setRelationship(e.target.value)}
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
              onClick={() => setPhase("intro")}
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

  // Intro phase
  if (phase === "intro") {
    // min-h-screen scales to screen height, and then color is set using bg
    return (
      <main className="min-h-screen bg-white">
        <div className="mx-auto max-w-2xl px-6 py-16">
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
      <main className="min-h-screen bg-white">
        <div className="mx-auto max-w-2xl px-6 py-16">
          <h1 className="text-3xl font-semibold text-gray-900 mb-6">
            Assessment complete
          </h1>
          <p className="text-gray-700 text-lg mb-10 leading-relaxed">
            All {total} questions have been answered.
          </p>
          <button
            onClick={handleReset}
            className="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                       hover:bg-gray-700 focus:outline-none
                       focus:ring-4 focus:ring-gray-400 transition"
          >
            New assessment
          </button>
        </div>
      </main>
    );
  }

  // Questions phase
  const percent = Math.round(((index + 1) / total) * 100);

  return (
    <main className="min-h-screen bg-white">
      <div className="mx-auto max-w-2xl px-6 py-16">

        {/* Progress bar */}
        <div className="mb-12">
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
          className="text-2xl md:text-3xl text-gray-900 font-medium leading-snug mb-12"
          aria-live="polite"
        >
          {current.text}
        </h2>

        {/* Yes / No buttons */}
        <div className="flex gap-4 mb-8">
          <button
            onClick={() => handleAnswer(true)}
            className="flex-1 bg-gray-900 text-white text-lg py-5 rounded-lg
                       hover:bg-gray-700 focus:outline-none
                       focus:ring-4 focus:ring-gray-400 transition"
          >
            Yes
          </button>
          <button
            onClick={() => handleAnswer(false)}
            className="flex-1 border-2 border-gray-900 text-gray-900 text-lg py-5 rounded-lg
                       hover:bg-gray-900 hover:text-white
                       focus:outline-none focus:ring-4 focus:ring-gray-400 transition"
          >
            No
          </button>
        </div>

        {/* Back button */}
        {index > 0 && (
          <button
            onClick={handleBack}
            className="text-sm text-gray-500 hover:text-gray-900 transition"
          >
            ← Previous question
          </button>
        )}

      </div>
    </main>
  );
}
