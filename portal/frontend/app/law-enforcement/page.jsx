"use client";

import { useState } from "react";
import { lawEnforcementQuestions } from "@/app/lib/questions/law-enforcement";
import { submitLawEnforcementAssessment } from "@/app/lib/api/law-enforcement";

import OfficerInfoStep from "@/app/components/law-enforcement/OfficerInfoStep";
import VictimStep from "@/app/components/law-enforcement/VictimStep";
import OffenderStep from "@/app/components/law-enforcement/OffenderStep";
import IntroStep from "@/app/components/law-enforcement/IntroStep";
import QuestionsStep from "@/app/components/law-enforcement/QuestionsStep";
import ReviewStep from "@/app/components/law-enforcement/ReviewStep";
import SubmittedStep from "@/app/components/law-enforcement/SubmittedStep";

export default function LawEnforcementAssessmentPage() {
  // phase: cycles through "officerInfo", "victim", "offender", "intro", "questions", "complete", "submitted"
  const [phase, setPhase] = useState("officerInfo");
  // index: tracks which question is displayed
  const [index, setIndex] = useState(0);
  // answers: stores responses as { questionId: boolean }
  const [answers, setAnswers] = useState({});
  const [victimErrors, setVictimErrors] = useState({});
  const [offenderErrors, setOffenderErrors] = useState({});

  // officer phase state
  const [officerId, setOfficerId] = useState("");
  const [officerIdError, setOfficerIdError] = useState(null);

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

  const total = lawEnforcementQuestions.length;
  const current = lawEnforcementQuestions[index];

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
    setOfficerId(""); setOfficerIdError(null);
    setVictimFirstName(""); setVictimLastName(""); setVictimDob(""); setVictimSex(""); setVictimPhone("");
    setOffenderFirstName(""); setOffenderLastName(""); setOffenderDob(""); setOffenderSex(""); setOffenderRelationship("");
    setSubmitError(null);
    setPhase("officerInfo");
  };

  const handleOfficerContinue = () => {
    if (!/^\d+$/.test(officerId.trim())) {
      setOfficerIdError("Enter a numeric user ID");
      return;
    }
    setOfficerIdError(null);
    setPhase("victim");
  };

  const handleSubmit = async () => {
    setSubmitting(true);
    setSubmitError(null);
    try {
      await submitLawEnforcementAssessment({
        submittedBy: parseInt(officerId, 10),
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

  if (phase === "officerInfo") {
    return (
      <OfficerInfoStep
        officerId={officerId} setOfficerId={setOfficerId}
        officerIdError={officerIdError}
        onContinue={handleOfficerContinue}
      />
    );
  }

  if (phase === "victim") {
    return (
      <VictimStep
        victimFirstName={victimFirstName} setVictimFirstName={setVictimFirstName}
        victimLastName={victimLastName} setVictimLastName={setVictimLastName}
        victimDob={victimDob} setVictimDob={setVictimDob}
        victimSex={victimSex} setVictimSex={setVictimSex}
        victimPhone={victimPhone} setVictimPhone={setVictimPhone}
        victimErrors={victimErrors}
        onBack={() => setPhase("officerInfo")}
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
        officerId={officerId}
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
