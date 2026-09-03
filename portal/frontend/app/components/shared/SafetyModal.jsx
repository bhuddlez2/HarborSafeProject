export default function SafetyModal({ open, onContinue }) {
  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="safety-modal-heading"
    >
      <div className="bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden">

        {/* Header */}
        <div className="bg-gray-900 px-6 py-5 text-center">
          <p className="text-xs font-semibold tracking-widest uppercase text-gray-400 mb-0.5">Before you begin</p>
          <h2 id="safety-modal-heading" className="text-xl font-bold text-white">Confidentiality notice</h2>
        </div>

        {/* Body */}
        <div className="px-6 py-5">
          <p className="text-sm text-gray-600 leading-relaxed mb-5">
            Information you provide in this assessment is kept confidential and used solely to
            connect you with support services.
          </p>

          <div className="space-y-4 mb-5">

            {/* Confidentiality item */}
            <div className="flex gap-3 items-start">
              <div className="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
                <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-gray-700 stroke-2 fill-none">
                  {/* lock icon, feathericons.com */}
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
              </div>
              <div>
                <p className="text-sm font-semibold text-gray-900 mb-0.5">Your information is protected</p>
                <p className="text-xs text-gray-600 leading-relaxed">
                  Responses are treated as confidential. We will not share your information
                  without your consent except as required by law.
                </p>
              </div>
            </div>

            {/* Mandatory reporting item */}
            <div className="flex gap-3 items-start">
              <div className="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
                <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-amber-600 stroke-2 fill-none">
                  {/* alert-triangle icon, feathericons.com */}
                  <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
              </div>
              <div>
                <p className="text-sm font-semibold text-gray-900 mb-0.5">Mandatory reporting</p>
                <p className="text-xs text-gray-600 leading-relaxed">
                  If information disclosed in this assessment indicates that a minor is being
                  abused or is at risk, we may be legally required to report it to the
                  appropriate authorities.
                </p>
              </div>
            </div>

            {/* Browse safely item */}
            <div className="flex gap-3 items-start">
              <div className="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
                <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-gray-700 stroke-2 fill-none">
                  {/* trash icon, feathericons.com */}
                  <polyline points="3 6 5 6 21 6" /><path d="M19 6l-1 14H6L5 6" /><path d="M10 11v6M14 11v6" /><path d="M9 6V4h6v2" />
                </svg>
              </div>
              <div>
                <p className="text-sm font-semibold text-gray-900 mb-0.5">Browse privately</p>
                <p className="text-xs text-gray-600 leading-relaxed">
                  If you are concerned about someone monitoring your activity, consider using
                  a private window and clearing your browser history afterward.
                </p>
              </div>
            </div>

          </div>

          {/* Action button */}
          <button
            onClick={onContinue}
            className="w-full bg-gray-900 text-white py-2.5 rounded-lg font-semibold text-sm
                       hover:bg-gray-700 focus:outline-none focus:ring-4 focus:ring-gray-400 transition"
          >
            I understand, continue
          </button>
        </div>

      </div>
    </div>
  );
}
