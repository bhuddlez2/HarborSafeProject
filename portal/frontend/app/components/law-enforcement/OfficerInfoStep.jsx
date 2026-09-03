export default function OfficerInfoStep({
  officerId, setOfficerId,
  officerIdError,
  onContinue,
}) {
  return (
    <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">
      <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 py-10 md:px-12 md:py-14">
        <h1 className="text-3xl font-semibold text-gray-900 mb-8">
          Submitting officer
        </h1>

        <div className="mb-10">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Officer user ID
          </label>
          <input
            type="text"
            inputMode="numeric"
            value={officerId}
            onChange={(e) => setOfficerId(e.target.value)}
            className="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                       focus:outline-none focus:border-gray-900 transition"
          />
          {officerIdError && (
            <p className="text-sm text-red-600 mt-1">{officerIdError}</p>
          )}
        </div>

        <button
          onClick={onContinue}
          disabled={!officerId.trim()}
          className="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                     hover:bg-gray-700 focus:outline-none
                     focus:ring-4 focus:ring-gray-400 transition
                     disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-gray-900"
        >
          Continue
        </button>
      </div>
    </main>
  );
}
