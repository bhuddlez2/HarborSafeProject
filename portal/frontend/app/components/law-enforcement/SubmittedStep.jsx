export default function SubmittedStep({ onReset }) {
  return (
    <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">
      <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 py-10 md:px-12 md:py-14">
        <h1 className="text-3xl font-semibold text-gray-900 mb-6">
          Assessment submitted
        </h1>
        <p className="text-gray-700 text-lg mb-10 leading-relaxed">
          The assessment has been saved successfully.
        </p>
        <button
          onClick={onReset}
          className="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                     hover:bg-gray-700 focus:outline-none
                     focus:ring-4 focus:ring-gray-400 transition"
        >
          Start new assessment
        </button>
      </div>
    </main>
  );
}
