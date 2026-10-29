// Same wording and order as NewAssessment::QUESTIONS (the officer wizard).
// portal/backend/tests/Unit/LethalityQuestionsTest.php fails if the two differ.
export const lethalityQuestions = [
  { id: 1,  text: "Have they ever used a weapon against you or threatened you with a weapon?" },
  { id: 2,  text: "Have they threatened to kill you or your children?" },
  { id: 3,  text: "Have they ever tried to choke (strangle) you?" },
  { id: 4,  text: "Do they have a gun or can they get one easily?" },
  { id: 5,  text: "Do they have an active order of protection against them?" },
  { id: 6,  text: "Are they a sex offender?" },
  { id: 7,  text: "Do you have injuries from previous incidents?" },
  { id: 8,  text: "Are they a serial abuser?" },
  { id: 9,  text: "Are they affiliated with gangs or criminal organizations?" },
  { id: 10, text: "Do they have any involvement in human trafficking?" },
  { id: 11, text: "Are there children involved who are at risk?" },
];

/*
The same questions with the victim's first name in place of "you" and the
offender's name in place of "they" (#33), for the civilian assessment's screens
only. What is submitted and stored is the answer to each question by number,
so staff screens, PDFs and the officer wizard keep the wording above.

Each template is written out in full, with its own words for a missing name,
so every mix reads correctly: `victim` and `offender` are each a name or null.
Nothing here edits the generic text above. Question 11 names no one, so it has
no template.
*/
const personalTemplates = {
  1: ({ victim, offender }) =>
    `${offender ? `Has ${offender}` : "Have they"} ever used a weapon against ${victim ?? "you"} or threatened ${victim ?? "you"} with a weapon?`,
  2: ({ victim, offender }) =>
    `${offender ? `Has ${offender}` : "Have they"} threatened to kill ${victim ? `${victim} or ${victim}'s` : "you or your"} children?`,
  3: ({ victim, offender }) =>
    `${offender ? `Has ${offender}` : "Have they"} ever tried to choke (strangle) ${victim ?? "you"}?`,
  4: ({ offender }) =>
    offender
      ? `Does ${offender} have a gun or can ${offender} get one easily?`
      : "Do they have a gun or can they get one easily?",
  5: ({ offender }) =>
    offender
      ? `Is there an active order of protection against ${offender}?`
      : "Do they have an active order of protection against them?",
  6: ({ offender }) => (offender ? `Is ${offender} a sex offender?` : "Are they a sex offender?"),
  7: ({ victim }) =>
    victim ? `Does ${victim} have injuries from previous incidents?` : "Do you have injuries from previous incidents?",
  8: ({ offender }) => (offender ? `Is ${offender} a serial abuser?` : "Are they a serial abuser?"),
  9: ({ offender }) =>
    offender
      ? `Is ${offender} affiliated with gangs or criminal organizations?`
      : "Are they affiliated with gangs or criminal organizations?",
  10: ({ offender }) =>
    offender
      ? `Does ${offender} have any involvement in human trafficking?`
      : "Do they have any involvement in human trafficking?",
};

// Name parts as typed, trimmed and joined; null when blank or only spaces.
export const nameOrNull = (...parts) =>
  parts.map((part) => (part ?? "").trim()).filter(Boolean).join(" ") || null;

/*
The text to show for one question. With neither name, the generic text above,
exactly; otherwise its template (or the generic text for a question with
none). Plain text, rendered by React as text - never as HTML.
*/
export function questionText(question, { victim = null, offender = null } = {}) {
  const names = { victim: nameOrNull(victim), offender: nameOrNull(offender) };
  const template = personalTemplates[question.id];

  if ((!names.victim && !names.offender) || !template) {
    return question.text;
  }

  return template(names);
}
