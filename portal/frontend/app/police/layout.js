import PortalSidebar from "@/components/portal/PortalSidebar";
import PortalHeader from "@/components/portal/PortalHeader";

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

// Metadata for the officer portal layout
// robots ensures search engine crawlers ignore police portal pages
export const metadata = {
  title: "Officer Portal",
  robots: { index: false, follow: false },
};

// viewport-fit=cover makes env(safe-area-inset-*) resolve correctly on notched phones
export const viewport = {
  width: "device-width",
  initialScale: 1,
  viewportFit: "cover",
};

export default async function PoliceLayout({ children }) {
  const officer = await getOfficer();

  return (
    <div className="flex min-h-dvh bg-[#F4F2F7] font-nunito text-[#1B1726]">
      <PortalSidebar officer={officer} />
      {/* pb-[76px] offsets the fixed mobile bottom nav bar */}
      <div className="grow pb-19 md:pb-0">
        <PortalHeader officer={officer} />
        {children}
      </div>
    </div>
  );
}
