export default function ProgressBar({ index, total }) {
  const percent = Math.round(((index + 1) / total) * 100);

  return (
    <div className="mb-10">
      <div className="flex justify-between text-sm text-gray-500 mb-2">
        <span>Question {index + 1} of {total}</span>
        <span>{percent}%</span>
      </div>
      <div
        className="h-2 bg-gray-200 rounded-full overflow-hidden"
        role="progressbar"
        aria-valuenow={percent}
        aria-valuemin={0}
        aria-valuemax={100}
      >
        <div
          className="h-full bg-gray-900 transition-all duration-300"
          style={{ width: `${percent}%` }}
        />
      </div>
    </div>
  );
}
