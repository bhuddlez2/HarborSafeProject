// Lethality assessment question data.
// the critical trigger questions are the ones that indicate a high risk of lethality if answered "yes"
export const lethalityQuestions = [
  { id: 1,  text: "Have they ever used a weapon against you or threatened you with a weapon?",                              criticalTrigger: true  },
  { id: 2,  text: "Have they threatened to kill you or your children?",                                                     criticalTrigger: true  },
  { id: 3,  text: "Do you think they might try to kill you?",                                                              criticalTrigger: true  },
  { id: 4,  text: "Do they have a gun or can they get one easily?",                                                    criticalTrigger: false },
  { id: 5,  text: "Have they ever tried to choke (strangle) you?",                                                         criticalTrigger: false },
  { id: 6,  text: "Are they violently or constantly jealous, or do they control most of your daily activities?",       criticalTrigger: false },
  { id: 7,  text: "Have you left them or separated after living together or being married?",                              criticalTrigger: false },
  { id: 8,  text: "Are they unemployed?",                                                                                   criticalTrigger: false },
  { id: 9,  text: "Have they ever tried to kill himself/herself?",                                                         criticalTrigger: false },
  { id: 10, text: "Do you have a child that they know is not theirs?",                                                 criticalTrigger: false },
  { id: 11, text: "Do they follow or spy on you, or leave threatening messages?",                                       criticalTrigger: false },
];
