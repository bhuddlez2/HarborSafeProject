import Image from "next/image";

/*
Footer component,
extracted into its own file so layout.js can import it and render it on every page,
no "use client" needed since this component has no interactivity or state
*/
export default function Footer() {
  return (
    /*
    Footer,
    bg-brand for brand purple background,
    text-white for all text inside,
    py-12 px-4 for vertical padding and horizontal gutters,
    aria-label describes the footer region to screen readers
    */
    <footer className="bg-brand text-white py-12 px-4" aria-label="Site footer with contact information and social media links">

      {/*
      Two-column layout: hotline info on the left, social links on the right,
      max-w-7xl mx-auto to match the width of the sections above,
      grid md:grid-cols-2 for two columns on medium screens and up,
      gap-8 for spacing between columns
      */}
      <div className="max-w-7xl mx-auto grid md:grid-cols-2 gap-8">

        {/* Left column: crisis hotline heading and phone link */}
        <div>
          {/* mb-4 for spacing between heading and link below */}
          <h3 className="font-semibold mb-4">24/7 Confidential Domestic Violence and Sexual Assault Crisis Hotline</h3>
          <div className="space-y-3">
            {/*
            Phone link,
            flex items-center gap-3 to align the icon and number side by side,
            hover:text-purple-200 for a lighter purple on hover,
            transition-colors for a smooth color change
            */}
            <a href="tel:423-476-3886" className="flex items-center gap-3 hover:text-purple-200 transition-colors">
              {/* Phone icon */}
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-5 h-5">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.13 12 19.79 19.79 0 0 1 1.06 3.38 2 2 0 0 1 3.04 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" />
              </svg>
              <span>(423) 476-3886</span>
            </a>
          </div>
        </div>

        {/* Right column: social media links */}
        <div>
          {/* mb-4 for spacing between heading and icons below */}
          <h3 className="font-semibold mb-4">Connect With Us</h3>
          {/*
          flex gap-4 to lay the icons out in a row with spacing,
          hover:text-purple-200 on each link for consistent hover color
          */}
          <div className="flex gap-4">
            {/* Facebook */}
            <a href="https://www.facebook.com/HarborSafeHouse/" target="_blank" rel="noopener noreferrer" className="hover:text-purple-200 transition-colors" aria-label="Visit Harbor Safe House on Facebook (opens in new tab)">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-6 h-6">
                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />
              </svg>
            </a>
            {/* TikTok */}
            <a href="https://www.tiktok.com/@harborsafehouse" target="_blank" rel="noopener noreferrer" className="hover:text-purple-200 transition-colors" aria-label="Visit Harbor Safe House on TikTok (opens in new tab)">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-6 h-6">
                <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5" />
              </svg>
            </a>
            {/* Instagram */}
            <a href="https://www.instagram.com/harborsafehouse/" target="_blank" rel="noopener noreferrer" className="hover:text-purple-200 transition-colors" aria-label="Visit Harbor Safe House on Instagram (opens in new tab)">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-6 h-6">
                <rect width="20" height="20" x="2" y="2" rx="5" ry="5" />
                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
                <line x1="17.5" x2="17.51" y1="6.5" y2="6.5" />
              </svg>
            </a>
          </div>
        </div>
      </div>

      {/* Divider line above the combined logo */}
      <div className="max-w-7xl mx-auto mt-8 pt-8 border-t border-purple-700"></div>

      {/*
      Combined logo below the divider,
      centered with mx-auto,
      h-16 sets the display height, w-auto scales proportionally
      */}
      <div className="max-w-7xl mx-auto mt-6">
        <Image
          src="/Combined Logo With Color.svg"
          alt="Harbor Safe House and Advocacy Center — a program of Family Resource Agency, Inc."
          width={400}
          height={100}
          className="h-32 w-auto"
        />
      </div>

    </footer>
  );
}
