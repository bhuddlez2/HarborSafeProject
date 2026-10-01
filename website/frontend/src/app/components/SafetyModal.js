"use client";

import { useState, useEffect, startTransition } from "react";


// sessionStorage key marking that the safety notice was dismissed in this tab
const DISMISSED_KEY = "hshac-safety-notice-dismissed";

export default function SafetyModal() {
  /*
  showSafetyModal starts false so the server-rendered HTML and the first client render
  match (the server has no sessionStorage), avoiding a React hydration mismatch,
  the effect below decides whether to show it once the page has hydrated
  */
  const [showSafetyModal, setShowSafetyModal] = useState(false);

  useEffect(() => {
    let dismissed = false;
    try {
      dismissed = window.sessionStorage.getItem(DISMISSED_KEY) === "1";
    } catch {}
    if (!dismissed) startTransition(() => setShowSafetyModal(true));
  }, []);

  /*
  handleDismissSafetyModal is called when the visitor clicks "I understand",
  records the dismissal for this tab session only, then hides the notice
  */
  const handleDismissSafetyModal = () => {
    try {
      window.sessionStorage.setItem(DISMISSED_KEY, "1");
    } catch {}
    setShowSafetyModal(false);
  };

  /*
  safety-modal-open on <body> lets globals.css raise and pulse the Safe Exit button
  above this notice while it is open
  */
  useEffect(() => {
    document.body.classList.toggle("safety-modal-open", showSafetyModal);
    return () => document.body.classList.remove("safety-modal-open");
  }, [showSafetyModal]);

  if (!showSafetyModal) return null;

  return (
    /*
    Icons from Feather Icons (https://feathericons.com) MIT License,
    z-200 places it above the navbar (z-50) and exit button (z-100),
    pb-28 below lg keeps the card clear of the Safe Exit button, which globals.css raises above
    this modal and would otherwise cover the "continue to site" button on phones
    */
    <div
      className="fixed inset-0 z-200 flex items-center justify-center bg-black/60 p-4 pb-28 lg:pb-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="safety-modal-heading"
    >
      {/* Modal card, max-h-full + overflow-y-auto so it scrolls instead of running off short screens */}
      <div className="bg-white rounded-4xl shadow-xl max-w-md w-full max-h-full overflow-y-auto">

        {/* Brand header with shield icon and title */}
        <div className="bg-brand px-6 py-5 flex items-center gap-4">
          {/*
          bg-white/15 creates a subtle semi-transparent circle behind the shield icon
          */}
          <div className="w-10 h-10 rounded-full bg-white/15 flex items-center justify-center shrink-0">
            <svg viewBox="0 0 24 24" className="w-5 h-5 stroke-white/85 stroke-5 fill-none">
              {/* shield icon, feathericons.com */}
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            </svg>
          </div>
          <div>
            {/* Eyebrow label */}
            <p className="text-xs font-semibold tracking-widest uppercase text-purple-300 mb-0.5">Your safety matters</p>
            {/* Modal heading */}
            <h2 id="safety-modal-heading" className="text-xl font-bold text-white">Browse safely &amp; privately</h2>
          </div>
        </div>

        {/* Modal body */}
        <div className="px-6 py-5">
          {/* Introductory paragraph */}
          <p className="text-sm text-gray-600 leading-relaxed mb-5">
            Internet usage can be monitored and is difficult to erase completely. If you&apos;re
            concerned your activity is being watched, here are some ways to stay safer.
          </p>

          {/* Safety tips */}
          <div className="space-y-4 mb-5">

            {/* Exit quickly tip */}
            <div className="flex gap-3 items-start">
              <div className="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center shrink-0">
                <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-brand stroke-2 fill-none">
                  {/* log-out icon, feathericons.com */}
                  <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><polyline points="16 17 21 12 16 7" /><line x1="21" y1="12" x2="9" y2="12" />
                </svg>
              </div>
              <div>
                <p className="text-sm font-semibold text-brand mb-0.5">Exit quickly anytime</p>
                {/*
                kbd is styled to look like a physical keyboard key,
                */}
                <p className="text-xs text-gray-600 leading-relaxed">
                  Use the red &ldquo;Safe Exit&rdquo; button or press{" "}
                  <kbd className="bg-gray-100 border border-gray-500 rounded px-1 text-xs">Esc</kbd>{" "}
                  to leave this site immediately.
                </p>
              </div>
            </div>

            {/* Clear history tip */}
            <div className="flex gap-3 items-start">
              <div className="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center shrink-0">
                <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-brand stroke-2 fill-none">
                  {/* trash icon, feathericons.com */}
                  <polyline points="3 6 5 6 21 6" /><path d="M19 6l-1 14H6L5 6" /><path d="M10 11v6M14 11v6" /><path d="M9 6V4h6v2" />
                </svg>
              </div>
              <div>
                <p className="text-sm font-semibold text-brand mb-0.5">Clear your history after visiting</p>
                <p className="text-xs text-gray-600 leading-relaxed">
                  Delete your browser history or use a private / incognito window before you start.
                </p>
              </div>
            </div>

            {/* Call confidentially tip */}
            <div className="flex gap-3 items-start">
              <div className="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center shrink-0">
                <svg viewBox="0 0 24 24" className="w-4 h-4 stroke-brand stroke-2 fill-none">
                  {/* phone icon, feathericons.com */}
                  <path d="M22 16.92v3a2 2 0 01-2.18 2A19.86 19.86 0 013.09 4.18 2 2 0 015.09 2h3a2 2 0 012 1.72c.13 1 .37 1.97.72 2.9a2 2 0 01-.45 2.11L9.09 10a16 16 0 006.91 6.91l1.27-1.27a2 2 0 012.11-.45c.93.35 1.9.59 2.9.72A2 2 0 0122 16.92z" />
                </svg>
              </div>
              <div>
                <p className="text-sm font-semibold text-brand mb-0.5">Call us confidentially</p>
                <p className="text-xs text-gray-500 leading-relaxed">
                  If you&apos;re worried about being monitored, call{" "}
                  <a href="tel:423-476-3886" className="text-brand font-semibold hover:underline">(423) 476-3886</a>{" "}
                  — available 24/7, always free.
                </p>
              </div>
            </div>

          </div>

          {/* 911 danger callout */}
          <div className="bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-5 flex gap-2 items-start">
            <svg viewBox="0 0 24 24" className="w-4 h-4 shrink-0 mt-0.5 stroke-red-700 stroke-2 fill-none">
              {/* alert-circle icon, feathericons.com */}
              <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <a href="tel:911" className="text-sm font-semibold text-red-700 hover:underline">If you are in immediate danger, call 911.</a>
          </div>

          {/* Action buttons */}
          <div className="space-y-2">
            <button
              onClick={handleDismissSafetyModal}
              className="hover:scale-105 w-full bg-brand text-white py-2.5 rounded-lg font-semibold text-sm border-2 border-brand hover:bg-white hover:text-brand cursor-pointer transition-all"
            >
              I understand — continue to site
            </button>
            <button
              onClick={() => window.location.replace("https://www.google.com")}
              className="hover:scale-105 w-full bg-red-600 text-white py-2.5 rounded-lg font-semibold text-sm border-2 border-red-600 hover:bg-white hover:text-red-600 transition-all cursor-pointer"
            >
              Safe Exit
            </button>
          </div>
        </div>

      </div>
    </div>
  );
}
