import Image from "next/image";
import Link from "next/link";
import CrisisHotlineStrip from "../components/CrisisHotlineStrip";

// Browser tab title and search description for this page (overrides the default in layout.js)
export const metadata = {
  title: "Give Support | Harbor Safe House & Advocacy Center",
  description:
    "Ways to support survivors at Harbor Safe House & Advocacy Center in Cleveland, TN: financial gifts, our Amazon wishlist, and donated items.",
};

const WISHLIST_ITEMS = [
  "Toiletries (soap, shampoo, toothbrushes, toothpaste, feminine hygiene products)",
  "Therapy and relaxation tools (journals, art supplies, stress-relief items)",
  "Cleaning supplies (detergent, disinfectants, mops, brooms, trash bags)",
  "First aid kits and medical supplies",
  "Non-perishable food and snacks",
  "Arts and crafts supplies",
];

export default function GiveSupport() {
  return (
    <main>

      {/* Hero */}
      <section className="bg-purple-950 px-4 pt-20 pb-16 text-center">
        <p className="text-xs font-semibold tracking-widest uppercase text-purple-300 mb-3">
          Harbor Safe House &amp; Advocacy Center
        </p>
        <h1 className="text-5xl md:text-6xl font-semibold text-white mb-4">Your Kindness Matters</h1>
        <p className="text-white/80 text-lg max-w-xl mx-auto leading-relaxed">
          There are many ways to show up for survivors. Thank you for thinking of us.
        </p>
      </section>

      {/* Hotline strip */}
      <CrisisHotlineStrip />

      {/* Intro, left-aligned on phones where a long centered paragraph is hard to read, centered from sm up */}
      <section className="py-20 px-4 bg-white">
        <div className="max-w-3xl mx-auto text-left sm:text-center">
          <p className="text-gray-700 leading-relaxed text-lg">
            We are so thankful you want to learn more about HSHAC and the ways you and your family can get involved.
            Running a safe house for survivors of domestic violence and sexual assault requires a wide range of items
            to ensure safety, comfort, and support for residents. Every aspect of our work is designed to send a
            strong message to every individual that no one should face domestic abuse or sexual assault alone and
            that they are loved. From the colors we use to the furniture we choose, every aspect conveys that they
            are safe and matter. Due to space constraints at our location, we can only accept items on the list
            below, monetary gifts or gift cards. Please <Link replace href="/contact" className="text-brand font-semibold hover:underline">contact us</Link> to ask about other donations. Thank you for
            thinking of us.
          </p>
        </div>
      </section>

      {/* Section divider */}
      <div className="w-full h-1 bg-brand"></div>

      {/* Ways to give */}
      <section className="py-20 px-4 bg-white">
        <div className="max-w-7xl mx-auto">
          <div className="grid md:grid-cols-2 gap-12 items-start">

            {/* Left: stock image */}
            <Image
              src="/give-support-donations.png"
              alt="Person folding clothes and placing them in a box to donate"
              width={0}
              height={0}
              sizes="100vw"
              className="w-full h-auto rounded-xl shadow-lg"
            />

            {/* Right: CTAs + wishlist */}
            <div>

              <div className="flex flex-col gap-3 mb-10">
                <a
                  href="https://givebutter.com/hshac"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="w-full bg-brand text-white text-center py-4 rounded-lg font-semibold text-lg hover:bg-purple-800 transition-all"
                >
                  Support Financially
                </a>
                <a
                  href="https://www.amazon.com/registries/gl/guest-view/2PIA67RLDWUA5"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="w-full bg-brand text-white text-center py-4 rounded-lg font-semibold text-lg hover:bg-purple-800 transition-all"
                >
                  Amazon Wishlist
                </a>
              </div>

              {/* Donation wishlist */}
              <ul className="space-y-4">
                {WISHLIST_ITEMS.map((item, i) => (
                  <li key={i} className="flex items-start gap-3 text-gray-700">
                    <span className="text-brand mt-0.5 shrink-0 text-lg leading-snug" aria-hidden="true">•</span>
                    <span className="leading-relaxed">{item}</span>
                  </li>
                ))}
              </ul>

            </div>
          </div>
        </div>
      </section>


    </main>
  );
}
