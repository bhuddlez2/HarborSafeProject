import FeedbackContent from "./FeedbackContent";

/*
This file is a server component so it can export metadata;
all of the interactive work is in FeedbackContent.js.
*/
export const metadata = {
  title: "Share Your Feedback | Harbor Safe House & Advocacy Center",
  description:
    "Tell Harbor Safe House & Advocacy Center how one of our services went. Feedback is anonymous.",
};

export default function FeedbackPage() {
  return <FeedbackContent />;
}
