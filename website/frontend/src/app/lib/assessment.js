/*
assessment.js: the data layer for the civilian lethality assessment (app/assessment).

The whole assessment goes to the API in ONE request, POST /api/public/civilian-assessments,
which writes it in one transaction through a database user that can only insert
(see the backend's PublicCivilianAssessmentController). Nothing is sent before Submit,
and nothing comes back: the response is a bare confirmation, so no record id or
submitted detail is ever held by the browser after the request.

Like forms.js, this runs in the visitor's browser (the site is a static export), and only
ever calls the narrow /api/public/* surface.
*/
const API_URL = (process.env.NEXT_PUBLIC_API_URL || "http://127.0.0.1:8000").replace(/\/+$/, "");

/*
The "Relationship to victim" options, from the backend's single list
(App\Enums\OffenderRelationship via GET /api/public/relationships), so this page
never keeps its own copy. [{ value: "spouse", label: "Spouse" }, ...]; the form
sends `value`. Throws when the list can't be loaded, so the page can offer a retry.
*/
export async function fetchRelationshipOptions() {
  const response = await fetch(`${API_URL}/api/public/relationships`, {
    headers: { Accept: "application/json" },
  });

  if (!response.ok) {
    throw new Error(`Could not load the relationship options (HTTP ${response.status}).`);
  }

  return response.json();
}

/*
Sends the completed assessment. Submitter contact details are included only when
someone reports for another person and declines anonymity; the API refuses them
otherwise, so an anonymous submission can never carry them by accident.

Throws an Error with `status` and `fieldErrors` (the API's validation messages),
matching forms.js, so the page can show a throttling message on 429.
*/
export async function submitAssessment({
  forWhom,
  anonymous,
  submitter,
  victim,
  offender,
  answers,
  questionCount,
}) {
  const identified = forWhom === "other" && anonymous === false;

  const payload = {
    ForWhom: forWhom,
    ...(forWhom === "other" ? { Anonymous: anonymous } : {}),

    VictimFirstName: victim.firstName,
    VictimLastName: victim.lastName,
    VictimSex: victim.sex,
    VictimDOB: victim.dob || null,
    VictimSafePhoneNumber: victim.phone,

    OffenderFirstName: offender.firstName,
    OffenderLastName: offender.lastName,
    OffenderSex: offender.sex,
    OffenderDOB: offender.dob || null,
    // A code from the list; the typed detail only goes with "other" (the API refuses it otherwise).
    OffenderVictimRelationship: offender.relationship,
    OffenderVictimRelationshipOther:
      offender.relationship === "other" ? offender.relationshipOther.trim() || null : null,

    ...(identified
      ? {
          SubmitterFirstName: submitter.firstName,
          SubmitterLastName: submitter.lastName,
          SubmitterEmail: submitter.email || null,
          SubmitterPhoneNumber: submitter.phone || null,
        }
      : {}),
  };

  for (let n = 1; n <= questionCount; n++) {
    payload[`RiskIndicator${n}`] = answers[n] ?? false;
  }

  const response = await fetch(`${API_URL}/api/public/civilian-assessments`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(payload),
  });

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    const error = new Error(data.message || "Something went wrong. Please try again.");
    error.status = response.status;
    error.fieldErrors = data.errors ?? {};
    throw error;
  }

  return data;
}
