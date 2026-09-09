import SafetyModal from "@/app/components/shared/SafetyModal";

export default function PrescreenStep({
  showSafetyModal,
  onSafetyContinue,
  forWhom,
  setForWhom,
  anonymous,
  setAnonymous,
  onContinue,
}) {
  return (
    <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">

      <SafetyModal open={showSafetyModal} onContinue={onSafetyContinue} />

      <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 py-10 md:px-12 md:py-14">
        <h1 className="text-3xl font-semibold text-gray-900 mb-8">
          Before we begin
        </h1>

        {/* Question 1: who is this for */}
        <p className="text-gray-700 text-lg font-medium mb-4">
          Who is this report for?
        </p>
        <div className="flex gap-4 mb-10">
          <button
            onClick={() => setForWhom("self")}
            className={`flex-1 py-4 rounded-lg text-lg border-2 transition
              focus:outline-none focus:ring-4 focus:ring-gray-400
              ${forWhom === "self"
                ? "bg-gray-900 text-white border-gray-900"
                : "border-gray-900 text-gray-900 hover:bg-gray-900 hover:text-white"}`}
          >
            Myself
          </button>
          <button
            onClick={() => setForWhom("other")}
            className={`flex-1 py-4 rounded-lg text-lg border-2 transition
              focus:outline-none focus:ring-4 focus:ring-gray-400
              ${forWhom === "other"
                ? "bg-gray-900 text-white border-gray-900"
                : "border-gray-900 text-gray-900 hover:bg-gray-900 hover:text-white"}`}
          >
            Someone else
          </button>
        </div>

        {/* Question 2: anonymity, only shown when assessing someone else */}
        {forWhom === "other" && (
          <div className="mb-10">
            <p className="text-gray-700 text-lg font-medium mb-4">
              Would you like to remain anonymous?
            </p>
            <div className="flex gap-4">
              <button
                onClick={() => setAnonymous(true)}
                className={`flex-1 py-4 rounded-lg text-lg border-2 transition
                  focus:outline-none focus:ring-4 focus:ring-gray-400
                  ${anonymous === true
                    ? "bg-gray-900 text-white border-gray-900"
                    : "border-gray-900 text-gray-900 hover:bg-gray-900 hover:text-white"}`}
              >
                Yes
              </button>
              <button
                onClick={() => setAnonymous(false)}
                className={`flex-1 py-4 rounded-lg text-lg border-2 transition
                  focus:outline-none focus:ring-4 focus:ring-gray-400
                  ${anonymous === false
                    ? "bg-gray-900 text-white border-gray-900"
                    : "border-gray-900 text-gray-900 hover:bg-gray-900 hover:text-white"}`}
              >
                No
              </button>
            </div>
          </div>
        )}

        {/* Continue: for "self" appears immediately; for "other" requires anonymity answer */}
        {(forWhom === "self" || (forWhom === "other" && anonymous !== null)) && (
          <button
            onClick={onContinue}
            className="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                       hover:bg-gray-700 focus:outline-none
                       focus:ring-4 focus:ring-gray-400 transition"
          >
            Continue
          </button>
        )}

      </div>
    </main>
  );
}
