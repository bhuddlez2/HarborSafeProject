export default function SubmitterInfoStep({
  firstName, setFirstName,
  lastName, setLastName,
  email, setEmail,
  phone, setPhone,
  phoneError,
  submitterErrors,
  onBack,
  onContinue,
}) {
  return (
    <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">
      <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 py-10 md:px-12 md:py-14">
        <h1 className="text-3xl font-semibold text-gray-900 mb-10">
          Your information
        </h1>

        <div className="flex gap-4 mb-6">
          <div className="flex-1">
            <label className="block text-sm font-medium text-gray-700 mb-1">
              First name
            </label>
            <input
              type="text"
              value={firstName}
              onChange={(e) => setFirstName(e.target.value)}
              className="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                         focus:outline-none focus:border-gray-900 transition"
            />
            {submitterErrors.firstName && (
              <p className="text-sm text-red-600 mt-1">{submitterErrors.firstName[0]}</p>
            )}
          </div>
          <div className="flex-1">
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Last name
            </label>
            <input
              type="text"
              value={lastName}
              onChange={(e) => setLastName(e.target.value)}
              className="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                         focus:outline-none focus:border-gray-900 transition"
            />
            {submitterErrors.lastName && (
              <p className="text-sm text-red-600 mt-1">{submitterErrors.lastName[0]}</p>
            )}
          </div>
        </div>

        <div className="mb-6">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Email{" "}
            <span className="text-gray-400 font-normal">(optional)</span>
          </label>
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                       focus:outline-none focus:border-gray-900 transition"
          />
        </div>

        <div className="mb-10">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Phone number{" "}
            <span className="text-gray-400 font-normal">(optional)</span>
          </label>
          <input
            type="tel"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            maxLength={20}
            aria-invalid={!!phoneError}
            className="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                       focus:outline-none focus:border-gray-900 transition"
          />
          {phoneError && (
            <p className="text-red-600 text-sm mt-1">{phoneError}</p>
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
            disabled={!firstName.trim() || !lastName.trim()}
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
