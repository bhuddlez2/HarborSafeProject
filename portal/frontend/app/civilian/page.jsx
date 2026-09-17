"use client";

import { useState, useEffect, startTransition } from "react";
import { civilianQuestions } from "@/app/lib/questions/civilian";
import { submitAssessment } from "@/app/lib/api/civilian";
import { isValidPhone } from "@/app/lib/validation/shared";

import PrescreenStep from "@/app/components/civilian/PrescreenStep";
import SubmitterInfoStep from "@/app/components/civilian/SubmitterInfoStep";
import VictimStep from "@/app/components/civilian/VictimStep";
import OffenderStep from "@/app/components/civilian/OffenderStep";
import IntroStep from "@/app/components/civilian/IntroStep";
import QuestionsStep from "@/app/components/civilian/QuestionsStep";
import ReviewStep from "@/app/components/civilian/ReviewStep";
import SubmittedStep from "@/app/components/civilian/SubmittedStep";

export default function CivilianAssessmentPage() {
  const [showSafetyModal, setShowSafetyModal] = useState(false);

  useEffect(() => {
    startTransition(() => setShowSafetyModal(true));
  }, []);

  useEffect(() => {
    document.body.style.overflow = showSafetyModal ? "hidden" : "";
    return () => { document.body.style.overflow = ""; };
  }, [showSafetyModal]);

  // phase: cycles through "prescreen", "info", "victim", "offender", "intro", "questions", "complete", "submitted"
  const [phase, setPhase] = useState("prescreen");
  // index: tracks which question is displayed
  const [index, setIndex] = useState(0);
  // answers: stores responses as { questionId: boolean }
  const [answers, setAnswers] = useState({});
  // stores validation errors for the submitter/victim/offender phases
  const [submitterErrors, setSubmitterErrors] = useState({});
  const [victimErrors, setVictimErrors] = useState({});
  const [offenderErrors, setOffenderErrors] = useState({});

  // prescreen state
  const [forWhom, setForWhom] = useState(null);
  const [anonymous, setAnonymous] = useState(null);

  // info phase state (SubmitterInfo)
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");

  // victim phase state
  const [victimFirstName, setVictimFirstName] = useState("");
  const [victimLastName, setVictimLastName] = useState("");
  const [victimDob, setVictimDob] = useState("");
  const [victimSex, setVictimSex] = useState("");
  const [victimPhone, setVictimPhone] = useState("");

  // offender phase state
  const [offenderFirstName, setOffenderFirstName] = useState("");
  const [offenderLastName, setOffenderLastName] = useState("");
  const [offenderDob, setOffenderDob] = useState("");
  const [offenderSex, setOffenderSex] = useState("");
  const [offenderRelationship, setOffenderRelationship] = useState("");

  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);

  const phoneError = phone.trim() && !isValidPhone(phone.trim())
    ? "Enter a valid phone number."
    : null;

  const total = civilianQuestions.length;
  const current = civilianQuestions[index];

  const handleAnswer = (value) => {
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

  const resetAll = () => {
    setAnswers({});
    setIndex(0);
    setForWhom(null);
    setAnonymous(null);
    setFirstName(""); setLastName(""); setEmail(""); setPhone("");
    setVictimFirstName(""); setVictimLastName(""); setVictimDob(""); setVictimSex(""); setVictimPhone("");
    setOffenderFirstName(""); setOffenderLastName(""); setOffenderDob(""); setOffenderSex(""); setOffenderRelationship("");
    setSubmitError(null);
    setPhase("prescreen");
  };

  const handleSubmit = async () => {
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
  };

  if (phase === "prescreen") {
    return (
      <PrescreenStep
        showSafetyModal={showSafetyModal}
        onSafetyContinue={() => setShowSafetyModal(false)}
        forWhom={forWhom}
        setForWhom={setForWhom}
        anonymous={anonymous}
        setAnonymous={setAnonymous}
        onContinue={() => setPhase(forWhom === "other" && !anonymous ? "info" : "victim")}
      />
    );
  }

  if (phase === "info") {
    return (
      <SubmitterInfoStep
        firstName={firstName} setFirstName={setFirstName}
        lastName={lastName} setLastName={setLastName}
        email={email} setEmail={setEmail}
        phone={phone} setPhone={setPhone}
        phoneError={phoneError}
        submitterErrors={submitterErrors}
        onBack={() => setPhase("prescreen")}
        onContinue={() => setPhase("victim")}
      />
    );
  }

  if (phase === "victim") {
    return (
      <VictimStep
        forWhom={forWhom}
        victimFirstName={victimFirstName} setVictimFirstName={setVictimFirstName}
        victimLastName={victimLastName} setVictimLastName={setVictimLastName}
        victimDob={victimDob} setVictimDob={setVictimDob}
        victimSex={victimSex} setVictimSex={setVictimSex}
        victimPhone={victimPhone} setVictimPhone={setVictimPhone}
        victimErrors={victimErrors}
        onBack={() => setPhase(forWhom === "other" && anonymous === false ? "info" : "prescreen")}
        onContinue={() => setPhase("offender")}
      />
    );
  }

  if (phase === "offender") {
    return (
      <OffenderStep
        offenderFirstName={offenderFirstName} setOffenderFirstName={setOffenderFirstName}
        offenderLastName={offenderLastName} setOffenderLastName={setOffenderLastName}
        offenderDob={offenderDob} setOffenderDob={setOffenderDob}
        offenderSex={offenderSex} setOffenderSex={setOffenderSex}
        offenderRelationship={offenderRelationship} setOffenderRelationship={setOffenderRelationship}
        offenderErrors={offenderErrors}
        onBack={() => setPhase("victim")}
        onContinue={() => setPhase("intro")}
      />
    );
  }

  if (phase === "intro") {
    return <IntroStep total={total} onBegin={() => setPhase("questions")} />;
  }

  if (phase === "complete") {
    return (
      <ReviewStep
        victimFirstName={victimFirstName} victimLastName={victimLastName}
        offenderFirstName={offenderFirstName} offenderLastName={offenderLastName}
        forWhom={forWhom} anonymous={anonymous}
        firstName={firstName} lastName={lastName}
        total={total}
        submitError={submitError}
        submitting={submitting}
        onBack={() => { setIndex(total - 1); setPhase("questions"); }}
        onSubmit={handleSubmit}
      />
    );
  }

  if (phase === "submitted") {
    return <SubmittedStep onReset={resetAll} />;
  }

  // Questions phase (default fall-through)
  return (
    <QuestionsStep
      index={index}
      total={total}
      current={current}
      onAnswer={handleAnswer}
      onBack={handleBack}
    />
  );
}
