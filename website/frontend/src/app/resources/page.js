import ResourcesContent from "./ResourcesContent";

/*
This file is a server component so it can export metadata;
all of the interactive work is in ResourcesContent.js.
*/
export const metadata = {
  title: "Resources | Harbor Safe House & Advocacy Center",
  description:
    "Local and national resources for survivors of domestic violence and sexual assault: crisis lines, legal help, orders of protection, safety planning, and information on abuse and trauma.",
};

export default function ResourcesPage() {
  return <ResourcesContent />;
}
