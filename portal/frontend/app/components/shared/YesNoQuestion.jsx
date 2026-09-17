export default function YesNoQuestion({ showBack, onBack, onAnswer }) {
  return (
    <>
      {showBack && (
        <button
          onClick={onBack}
          className="text-sm text-gray-500 hover:text-gray-900 transition block mb-4"
        >
          ← Previous question
        </button>
      )}
      <div className="flex gap-4">
        <button
          onClick={() => onAnswer(true)}
          className="flex-1 border-2 border-gray-900 text-gray-900 text-lg py-4 rounded-lg
                     hover:bg-gray-900 hover:text-white
                     focus:outline-none focus-visible:ring-4 focus-visible:ring-gray-400 transition"
        >
          Yes
        </button>
        <button
          onClick={() => onAnswer(false)}
          className="flex-1 border-2 border-gray-900 text-gray-900 text-lg py-4 rounded-lg
                     hover:bg-gray-900 hover:text-white
                     focus:outline-none focus-visible:ring-4 focus-visible:ring-gray-400 transition"
        >
          No
        </button>
      </div>
    </>
  );
}
