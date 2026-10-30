"use client";

import { useEffect, useRef } from "react";

export default function Modal({ title, subtitle, onClose, children, dismissible = true, centered = false }) {
  const centerHeader = centered || !dismissible;
  /*
  closeRef holds the X button so focus can be moved into the dialog when it
  opens, and returnFocusRef remembers whatever was focused beforehand (the card
  the user clicked) so focus can be handed back on close. Without this, keyboard
  and screen reader users get dropped at the top of the document every time they
  dismiss a dialog.

  dismissible={false} removes the X and the click-outside close, for a notice
  that must be acknowledged with a button of its own (the civilian
  assessment's Confidentiality Notice). The dialog card takes focus instead.
  The Safe Exit button still sits above the backdrop either way.
  */
  const closeRef = useRef(null);
  const cardRef = useRef(null);
  const returnFocusRef = useRef(null);

  useEffect(() => {
    returnFocusRef.current = document.activeElement;
    (closeRef.current ?? cardRef.current)?.focus();

    document.body.classList.add("modal-open");
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    return () => {
      document.body.classList.remove("modal-open");
      document.body.style.overflow = previousOverflow;
      returnFocusRef.current?.focus?.();
    };
  }, []);

  return (
    /*
    Backdrop,
    fixed inset-0 to cover the viewport, bg-black/60 to dim the page behind,
    z-200 to sit above the navbar (z-50); globals.css raises the exit button
    above this while modal-open is set,
    the onClick only fires onClose when the click landed on the backdrop itself
    rather than bubbling up from inside the dialog card,
    pb-28 below lg reserves space for the Safe Exit button (fixed bottom-right),
    which would otherwise cover the bottom of the card on narrower screens; from lg
    up the centered card and the button no longer overlap horizontally
    */
    <div
      className="fixed inset-0 bg-black/60 z-200 flex items-center justify-center p-4 pb-28 lg:pb-4"
      onClick={(e) => { if (dismissible && e.target === e.currentTarget) onClose(); }}
      role="dialog"
      aria-modal="true"
      aria-label={title}
    >
      {/*
      Dialog card with an inner scroll area so long descriptions cannot push the close button off screen,
      max-h-full keeps it inside the backdrop's padding (clear of the Safe Exit button) below lg
      */}
      <div ref={cardRef} tabIndex={-1} className="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-full lg:max-h-[80vh] flex flex-col focus:outline-none">

        {/*
        Header: title, optional subtitle, and close button; centred when asked for or with no X,
        a spacer as wide as the X keeps a centred title on the card's centre line
        */}
        <div className="flex flex-wrap items-start justify-between p-6 border-b border-gray-100 shrink-0">
          {centered && dismissible && <span className="w-5 mr-4 shrink-0" aria-hidden="true" />}
          <div className={`min-w-0 wrap-anywhere ${centerHeader ? "flex-1 text-center" : ""}`}>
            {/* centered headers let the title carry the weight: bigger, bolder title over a smaller subtitle */}
            <h2 className={`text-brand leading-snug ${centered ? "text-2xl sm:text-3xl font-bold" : "text-xl font-semibold"}`}>{title}</h2>
            {subtitle && !centered && <p className="text-sm text-gray-500 mt-1">{subtitle}</p>}
          </div>
          {dismissible && (
            <button
              ref={closeRef}
              onClick={onClose}
              aria-label="Close"
              className="tap-target ml-4 shrink-0 text-gray-400 hover:text-brand transition-colors"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
          )}
          {/* a centred subtitle wraps onto its own full-width line, clear of the X and its spacer */}
          {subtitle && centered && <p className="w-full text-center text-xs text-gray-600 mt-2">{subtitle}</p>}
        </div>

        <div className="overflow-y-auto">
          {children}
        </div>
      </div>
    </div>
  );
}
