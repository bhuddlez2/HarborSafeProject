import Image from "next/image";

export default function About() {
  return (
    <main>

      {/* ── HERO / MISSION ─────────────────────────────────────────── */}
      <section className="pt-20 pb-12 bg-white px-4">
        <div className="max-w-7xl mx-auto">

          {/* Page heading and brand underline */}
          <div className="text-center mb-12">
            <h1 className="text-5xl md:text-6xl font-semibold text-brand mb-6">Who We Are</h1>
            <div className="w-11/12 h-2 bg-brand mx-auto"></div>
          </div>

          {/* Two-column: photo left, vision + mission text right */}
          <div className="grid md:grid-cols-2 gap-12 items-center">
            <div>
              <Image
                src="/Envision.png"
                alt="Four diverse women embracing in solidarity"
                width={0}
                height={0}
                sizes="100vw"
                className="w-full h-auto rounded-lg shadow-lg"
              />
            </div>
            <div className="max-w-3xl mx-auto">
              {/* Vision statement */}
              <h2 className="text-brand text-3xl md:text-4xl font-semibold mb-6 leading-tight">
                We envision a community where no one faces domestic abuse or sexual assault alone.
              </h2>
              {/* Mission paragraph */}
              <p className="text-gray-700 leading-relaxed text-lg">
                Our mission is deeply rooted in our unwavering commitment to empowering and
                supporting survivors of domestic violence. We believe that every individual
                deserves to live a life free from fear, violence, and oppression. Serving
                Bradley and Polk counties since 1987, HSHAC provides holistic trauma-informed
                care to ensure that you and your family find peace.
              </p>
            </div>
          </div>

        </div>
      </section>

      {/* Section divider */}
      <div className="w-full h-1 bg-brand"></div>

      {/* ── FRA PARTNERSHIP ────────────────────────────────────────── */}
      <section className="py-20 px-4 bg-purple-50">

        {/* Combined HSHAC + FRA logo */}
        <div>
          <Image
            src="/Combined Logo With Color.svg"
            alt="Harbor Safe House & Advocacy Center logo combined with Family Resource Agency logo"
            width={0}
            height={0}
            sizes="100vw"
            className="h-32 md:h-64 w-auto mx-auto block rounded-lg shadow-lg mb-12"
          />
        </div>

        {/* FRA relationship description */}
        <div className="max-w-5xl mx-auto">
          <p className="text-gray-700 leading-relaxed text-center max-w-4xl mx-auto">
            HSHAC operates as a program under Family Resource Agency Inc. (FRA). FRA&apos;s
            mission is to impact communities through education, advocacy, and care. Harbor Safe
            House is one of three key programs, along with SE TN Head Start and NW GA Head Start,
            overseen by FRA. While the Safe House provides crucial shelter and advocacy support
            for individuals facing domestic violence or crises, the Head Start programs focus on
            early childhood education and development. This structure allows FRA to coordinate
            diverse services, addressing immediate and long-term community needs.
          </p>
        </div>

      </section>

      {/* Section divider */}
      <div className="w-full h-1 bg-brand"></div>

      {/* ── PARTNERS ───────────────────────────────────────────────── */}
      <section className="py-20 px-4 bg-white">
        <div className="max-w-6xl mx-auto">

          {/* Section heading and intro */}
          <h2 className="text-4xl font-semibold text-brand text-center mb-4">Our Partners</h2>
          <p className="text-gray-700 text-center text-lg leading-relaxed max-w-4xl mx-auto mb-16">
            Harbor Safe House &amp; Advocacy Center proudly partners with local non-profits to
            provide holistic, evidence-based care for everyone seeking our support.
          </p>

          <div className="divide-y divide-purple-200">

            {/* United Way of the Ocoee Region */}
            <div className="flex flex-col md:flex-row gap-8 items-center py-12">
              <div className="shrink-0 w-48 flex justify-center">
                <a href="https://www.unitedwayocoee.org" target="_blank" rel="noopener noreferrer" aria-label="Visit United Way of the Ocoee Region website (opens in new tab)">
                  <Image 
                  src="/UnitedWay.svg" 
                  alt="United Way of the Ocoee Region" 
                  width={0} 
                  height={0} 
                  sizes="192px" 
                  className="h-28 w-auto" />
                </a>
              </div>
              <div>
                <h3 className="text-2xl font-semibold text-brand mb-3">United Way of the Ocoee Region</h3>
                <p className="text-gray-700 leading-relaxed">
                  United Way supports HSHAC through funding, volunteer networks, and community
                  connections. United Way of the Ocoee Region mobilizes communities to address
                  local needs through partnership and deep investment in programs that create
                  lasting change. Through their commitment to collaborative problem-solving, they
                  help us provide critical resources and support to survivors of domestic violence.
                </p>
              </div>
            </div>

            {/* Tennessee Coalition to End Domestic & Sexual Violence */}
            <div className="flex flex-col md:flex-row gap-8 items-center py-12">
              <div className="shrink-0 w-48 flex justify-center">
                <a href="https://tncoalition.org" target="_blank" rel="noopener noreferrer" aria-label="Visit Tennessee Coalition to End Domestic & Sexual Violence website (opens in new tab)">
                  <Image 
                  src="/Tennessee Coalition Logo.svg" 
                  alt="Tennessee Coalition to End Domestic & Sexual Violence Logo" 
                  width={0} 
                  height={0} 
                  sizes="192px" 
                  className="h-28 w-auto" />
                </a>
              </div>
              <div>
                <h3 className="text-2xl font-semibold text-brand mb-3">Tennessee Coalition to End Domestic &amp; Sexual Violence</h3>
                <p className="text-gray-700 leading-relaxed">
                  The Tennessee Coalition provides statewide advocacy, training, and resources 
                  that strengthen the capacity of local programs like HSHAC to serve survivors with best-practice, trauma-informed approaches.
                </p>
              </div>
            </div>

            {/* Legal Aid of East Tennessee */}
            <div className="flex flex-col md:flex-row gap-8 items-center py-12">
              <div className="shrink-0 w-48 flex justify-center">
                <a href="https://www.laet.org" target="_blank" rel="noopener noreferrer" aria-label="Visit Legal Aid of East Tennessee website (opens in new tab)">
                  <Image 
                  src="/LegalAid.svg" 
                  alt="Legal Aid of East Tennessee Logo" 
                  width={0} 
                  height={0} 
                  sizes="192px" 
                  className="h-28 w-auto" />
                </a>
              </div>
              <div>
                <h3 className="text-2xl font-semibold text-brand mb-3">Legal Aid of East Tennessee</h3>
                <p className="text-gray-700 leading-relaxed">
                  Legal Aid of East Tennessee provides free civil legal services to low-income individuals 
                  and families, helping them navigate complex legal issues and access justice. 
                  Their support is crucial in ensuring that survivors of domestic violence have 
                  the legal resources they need to protect themselves and their children.
                </p>
              </div>
            </div>

            {/* The Caring Place */}
            <div className="flex flex-col md:flex-row gap-8 items-center py-12">
              <div className="shrink-0 w-48 flex justify-center">
                <a href="https://thecaringplaceonline.org" target="_blank" rel="noopener noreferrer" aria-label="Visit The Caring Place website (opens in new tab)">
                  <Image 
                  src="/TheCaringPlace.svg" 
                  alt="The Caring Place Logo" 
                  width={0} 
                  height={0} 
                  sizes="192px" 
                  className="h-28 w-auto" />
                </a>
              </div>
              <div>
                <h3 className="text-2xl font-semibold text-brand mb-3">The Caring Place</h3>
                <p className="text-gray-700 leading-relaxed">
                  The Caring Place provides comprehensive support services for survivors of domestic violence 
                  and sexual assault, including counseling, advocacy, and emergency shelter. 
                  Their dedicated team works tirelessly to ensure that survivors have 
                  the resources and support they need to rebuild their lives.
                </p>
              </div>
            </div>

          </div>

        </div>
      </section>

    </main>
  );
}
