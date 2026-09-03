import ProgressBar from "@/app/components/shared/ProgressBar";
import YesNoQuestion from "@/app/components/shared/YesNoQuestion";

export default function QuestionsStep({ index, total, current, onAnswer, onBack }) {
  return (
    <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">
      <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 pt-10 pb-36 md:pb-14 md:px-12">

        <ProgressBar index={index} total={total} />

        {/* Question text */}
        <h2
          className="text-2xl md:text-3xl text-gray-900 font-medium leading-snug mb-10"
          aria-live="polite"
        >
          {current.text}
        </h2>

        {/* Back + Yes/No, inside card on desktop */}
        <div className="hidden md:block">
          <YesNoQuestion showBack={index > 0} onBack={onBack} onAnswer={onAnswer} />
        </div>

      </div>

      {/* Back + Yes/No, fixed to bottom on mobile only */}
      <div className="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-100 px-6 pb-10 pt-4">
        <div className="mx-auto max-w-2xl">
          <YesNoQuestion showBack={index > 0} onBack={onBack} onAnswer={onAnswer} />
        </div>
      </div>

    </main>
  );
}
