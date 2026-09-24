import EventsContent from "./EventsContent";

/*
This file is a server component so it can export metadata;
all of the interactive work is in EventsContent.js.
*/
export const metadata = {
  title: "Events & News | Harbor Safe House & Advocacy Center",
  description:
    "Upcoming events and newsletters from Harbor Safe House & Advocacy Center in Cleveland, TN.",
};

export default function EventsPage() {
  return <EventsContent />;
}
