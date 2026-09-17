export default function ReviewStep({
  victimFirstName, victimLastName,
  offenderFirstName, offenderLastName,
  officerId,
  total,
  submitError,
  submitting,
  onBack,
  onSubmit,
}) {
  return (
    <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">
      <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 py-10 md:px-12 md:py-14">
        <h1 className="text-3xl font-semibold text-gray-900 mb-6">
          Review &amp; submit
        </h1>

        <div className="divide-y divide-gray-100 mb-10">
          <div className="py-4">
            <p className="text-sm font-medium text-gray-500 mb-1">Victim</p>
            <p className="text-gray-900">{victimFirstName} {victimLastName}</p>
          </div>
          <div className="py-4">
            <p className="text-sm font-medium text-gray-500 mb-1">Offender</p>
            <p className="text-gray-900">{offenderFirstName} {offenderLastName}</p>
          </div>
          <div className="py-4">
            <p className="text-sm font-medium text-gray-500 mb-1">Submitting officer</p>
            <p className="text-gray-900">User ID {officerId}</p>
          </div>
          <div className="py-4">
            <p className="text-sm font-medium text-gray-500 mb-1">Questions answered</p>
            <p className="text-gray-900">{total} of {total}</p>
          </div>
        </div>

        {submitError && (
            <p className="text-red-600 text-sm mb-4">{submitError}</p>
        )}

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
            onClick={onSubmit}
            disabled={submitting}
            className={`bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                      hover:bg-gray-700 focus:outline-none
                      focus:ring-4 focus:ring-gray-400 transition
                      ${submitting ? 'opacity-50 cursor-not-allowed' : ''}`}
          >
            {submitting ? 'Submitting...' : 'Submit assessment'}
        </button>
        </div>
      </div>
    </main>
  );
}
