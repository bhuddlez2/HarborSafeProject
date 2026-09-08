export default function OfficerInfoStep({ onContinue }) {
  // officer info is auto-filled from the auth session (users + law_enforcement_agents)
  // placeholder values until auth is wired up
  const officerName = "";
  const officerBadge = "";
  const officerAgency = "";

  return (
    <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">
      <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 py-10 md:px-12 md:py-14">
        <h1 className="text-3xl font-semibold text-gray-900 mb-2">
          Submitting officer
        </h1>
        <p className="text-sm text-gray-500 mb-10">
          This information is pulled from your account and cannot be edited here.
        </p>

        <div className="flex gap-4 mb-6">
          <div className="flex-1">
            <p className="text-sm font-medium text-gray-700 mb-1">Name</p>
            <div className="w-full border-2 border-gray-200 rounded-lg px-4 py-3 bg-gray-50 text-gray-400 select-none">
              {officerName || <span className="italic">Auto-filled from account</span>}
            </div>
          </div>
          <div className="flex-1">
            <p className="text-sm font-medium text-gray-700 mb-1">Badge number</p>
            <div className="w-full border-2 border-gray-200 rounded-lg px-4 py-3 bg-gray-50 text-gray-400 select-none">
              {officerBadge || <span className="italic">Auto-filled from account</span>}
            </div>
          </div>
        </div>

        <div className="mb-10">
          <p className="text-sm font-medium text-gray-700 mb-1">Agency</p>
          <div className="w-full border-2 border-gray-200 rounded-lg px-4 py-3 bg-gray-50 text-gray-400 select-none">
            {officerAgency || <span className="italic">Auto-filled from account</span>}
          </div>
        </div>

        <button
          onClick={onContinue}
          className="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                     hover:bg-gray-700 focus:outline-none
                     focus:ring-4 focus:ring-gray-400 transition"
        >
          Continue
        </button>
      </div>
    </main>
  );
}
