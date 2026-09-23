import PortalSidebar from "@/components/portal/PortalSidebar";

// TODO: replace with a real session lookup once auth is wired, e.g.:
//   import { getSession } from "@/lib/auth";
//   const session = await getSession();
//   if (!session) redirect("/login");
async function getOfficer() {
  return {
    name: "Ofc. [Last Name]",
    badge: "[####]",
    agency: "[Agency]",
  };
}

export const metadata = {
  title: "Officer Portal",
  robots: { index: false, follow: false },
};

export default async function PoliceLayout({ children }) {
  const officer = await getOfficer();

  return (
    <div className="flex min-h-screen bg-[#F4F2F7] font-nunito text-[#1B1726]">
      <PortalSidebar officer={officer} />
      {/* pb-[76px] offsets the fixed mobile bottom nav bar */}
      <div className="grow pb-[76px] md:pb-0">{children}</div>
    </div>
  );
}
