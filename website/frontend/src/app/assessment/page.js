import AssessmentContent from "./AssessmentContent";

/*
This file is a server component so it can export metadata;
the assessment itself is in AssessmentContent.js.
*/
export const metadata = {
  title: "Lethality Assessment | Harbor Safe House & Advocacy Center",
  description:
    "Eleven yes/no questions that can help show whether a relationship is becoming more dangerous, sent confidentially to Harbor Safe House & Advocacy Center.",
};

export default function AssessmentPage() {
  return <AssessmentContent />;
}
