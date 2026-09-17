export default function OffenderStep({
  offenderFirstName, setOffenderFirstName,
  offenderLastName, setOffenderLastName,
  offenderDob, setOffenderDob,
  offenderSex, setOffenderSex,
  offenderRelationship, setOffenderRelationship,
  offenderErrors,
  onBack,
  onContinue,
}) {
  return (
    <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">
      <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 py-10 md:px-12 md:py-14">
        <h1 className="text-3xl font-semibold text-gray-900 mb-10">
          About the offender
        </h1>

        <div className="flex gap-4 mb-6">
          <div className="flex-1">
            <label className="block text-sm font-medium text-gray-700 mb-1">
              First name
            </label>
            <input
              type="text"
              value={offenderFirstName}
              onChange={(e) => setOffenderFirstName(e.target.value)}
              className="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                         focus:outline-none focus:border-gray-900 transition"
            />
            {offenderErrors.offenderFirstName && (
              <p className="text-sm text-red-600 mt-1">{offenderErrors.offenderFirstName[0]}</p>
            )}
          </div>
          <div className="flex-1">
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Last name
            </label>
            <input
              type="text"
              value={offenderLastName}
              onChange={(e) => setOffenderLastName(e.target.value)}
              className="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                         focus:outline-none focus:border-gray-900 transition"
            />
            {offenderErrors.offenderLastName && (
              <p className="text-sm text-red-600 mt-1">{offenderErrors.offenderLastName[0]}</p>
            )}
          </div>
        </div>

        <div className="flex gap-4 mb-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Date of birth{" "}
              <span className="text-gray-400 font-normal">(optional)</span>
            </label>
            <input
              type="date"
              value={offenderDob}
              onChange={(e) => setOffenderDob(e.target.value)}
              className="border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                         focus:outline-none focus:border-gray-900 transition"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Sex
            </label>
            <select
              value={offenderSex}
              onChange={(e) => setOffenderSex(e.target.value)}
              className={`w-32 h-12 appearance-none border-2 rounded-lg px-4 py-3 text-gray-900
                         focus:outline-none transition
                         ${offenderErrors.offenderSex ? "border-red-500" : "border-gray-300 focus:border-gray-900"}`}
            >
              <option value="">Select</option>
              <option value="M">Male</option>
              <option value="F">Female</option>
              <option value="O">Other</option>
            </select>
            {offenderErrors.offenderSex && (
              <p className="text-sm text-red-600 mt-1">{offenderErrors.offenderSex[0]}</p>
            )}
          </div>
        </div>

        <div className="mb-10">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Relationship to victim
          </label>
          <input
            type="text"
            value={offenderRelationship}
            onChange={(e) => setOffenderRelationship(e.target.value)}
            className="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                       focus:outline-none focus:border-gray-900 transition"
          />
          {offenderErrors.offenderRelationship && (
            <p className="text-sm text-red-600 mt-1">{offenderErrors.offenderRelationship[0]}</p>
          )}
        </div>

        <div className="flex gap-4">
          <button
            onClick={onBack}
            className="border-2 border-gray-900 text-gray-900 px-8 py-4 rounded-lg text-lg
                       hover:bg-gray-900 hover:text-white
                       focus:outline-none focus:ring-4 focus:ring-gray-400 transition"
          >
            Back
          </button>
          <button
            onClick={onContinue}
            disabled={!offenderFirstName.trim() || !offenderLastName.trim() || !offenderSex || !offenderRelationship.trim()}
            className="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                       hover:bg-gray-700 focus:outline-none
                       focus:ring-4 focus:ring-gray-400 transition"
          >
            Continue
          </button>
        </div>

      </div>
    </main>
  );
}
