import Image from "next/image";

export default function About() {
  return (
    <main>

      {/*
      Hero / mission section,
      mt-22.5 offsets the fixed navbar height,
      two-column grid: vision + mission text on the right
      */}
      <section className="mt-22.5 pt-20 pb-12 bg-white px-4">
        <div className="max-w-7xl mx-auto">

          {/* Page heading and purple underline */}
          <div className="text-center mb-12">
            <h1 className="text-5xl md:text-6xl mb-6">About HSHAC</h1>
            <div className="w-11/12 h-2 bg-brand mx-auto"></div>
          </div>

          {/* Image next to vision and mission text */}
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
            {/* Vision and mission text */}
            <div className="max-w-3xl mx-auto">
              <h2 className="text-brand text-3xl md:text-4xl mb-6 leading-tight">
                We envision a community where no one faces domestic abuse or sexual assault alone.
              </h2>
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

      {/*
      FRA partnership section,
      bg-gray-50 for a subtle background shift
      */}
      <section className="py-20 px-4 bg-gray-50">
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

      {/*
      Partners section,
      bg-white, each partner entry has a name and description
      */}
      <section className="py-20 px-4 bg-white">
        <div className="max-w-6xl mx-auto">

          <h2 className="text-4xl text-center mb-4">Our Partners</h2>
          <p className="text-gray-700 text-center text-lg leading-relaxed max-w-4xl mx-auto mb-16">
            Harbor Safe House &amp; Advocacy Center proudly partners with local non-profits to
            provide holistic, evidence-based care for everyone seeking our support.
          </p>

          <div className="space-y-12">

            <div className="flex flex-col md:flex-row gap-8 items-center">
              <div className="shrink-0 w-48 flex justify-center">
                <Image src="/UnitedWay.jpg" alt="United Way of the Ocoee Region" width={200} height={80} className="h-20 w-auto" />
              </div>
              <div>
                <h3 className="text-2xl text-brand mb-3">United Way of the Ocoee Region</h3>
                <p className="text-gray-700 leading-relaxed">
                  United Way supports HSHAC through funding, volunteer networks, and community
                  connections. United Way of the Ocoee Region mobilizes communities to address
                  local needs through partnership and deep investment in programs that create
                  lasting change. Through their commitment to collaborative problem-solving, they
                  help us provide critical resources and support to survivors of domestic violence.
                </p>
              </div>
            </div>

            <div className="flex flex-col md:flex-row gap-8 items-center">
              <div className="shrink-0 w-48 flex justify-center">
                <Image src="/Tennessee Coalition Logo.png" alt="Tennessee Coalition to End Domestic & Sexual Violence Logo" width={200} height={80} className="h-20 w-auto" />
              </div>
              <div>
                <h3 className="text-2xl text-brand mb-3">Tennessee Coalition to End Domestic &amp; Sexual Violence</h3>
                <p className="text-gray-700 leading-relaxed">
                  Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor
                  incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud
                  exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
                </p>
              </div>
            </div>

            <div className="flex flex-col md:flex-row gap-8 items-center">
              <div className="shrink-0 w-48 flex justify-center">
                <Image src="/LegalAid.png" alt="Legal Aid of East Tennessee Logo" width={200} height={80} className="h-20 w-auto" />
              </div>
              <div>
                <h3 className="text-2xl text-brand mb-3">Legal Aid of East Tennessee</h3>
                <p className="text-gray-700 leading-relaxed">
                  Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor
                  incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud
                  exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
                </p>
              </div>
            </div>

            <div className="flex flex-col md:flex-row gap-8 items-center">
              <div className="shrink-0 w-48 flex justify-center">
                <Image src="/TheCaringPlace.png" alt="The Caring Place Logo" width={200} height={80} className="h-20 w-auto" />
              </div>
              <div>
                <h3 className="text-2xl text-brand mb-3">The Caring Place</h3>
                <p className="text-gray-700 leading-relaxed">
                  Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor
                  incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud
                  exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
                </p>
              </div>
            </div>

          </div>

        </div>
      </section>

    </main>
  );
}
