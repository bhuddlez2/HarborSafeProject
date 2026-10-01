"use client";

import { useEffect, useRef } from "react";

export default function Modal({ title, subtitle, onClose, children }) {
  /*
  closeRef holds the X button so focus can be moved into the dialog when it
  opens, and returnFocusRef remembers whatever was focused beforehand (the card
  the user clicked) so focus can be handed back on close. Without this, keyboard
  and screen reader users get dropped at the top of the document every time they
  dismiss a dialog.
  */
  const closeRef = useRef(null);
  const returnFocusRef = useRef(null);

  useEffect(() => {
    returnFocusRef.current = document.activeElement;
    closeRef.current?.focus();

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
      onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}
      role="dialog"
      aria-modal="true"
      aria-label={title}
    >
      {/*
      Dialog card with an inner scroll area so long descriptions cannot push the close button off screen,
      max-h-full keeps it inside the backdrop's padding (clear of the Safe Exit button) below lg
      */}
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-full lg:max-h-[80vh] flex flex-col">

        {/* Header: title, optional subtitle, and close button */}
        <div className="flex items-start justify-between p-6 border-b border-gray-100 shrink-0">
          <div>
            <h2 className="text-xl font-semibold text-brand leading-snug">{title}</h2>
            {subtitle && <p className="text-sm text-gray-500 mt-1">{subtitle}</p>}
          </div>
          <button
            ref={closeRef}
            onClick={onClose}
            aria-label="Close"
            className="ml-4 shrink-0 text-gray-400 hover:text-brand transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
              <line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>

        <div className="overflow-y-auto">
          {children}
        </div>
      </div>
    </div>
  );
}
