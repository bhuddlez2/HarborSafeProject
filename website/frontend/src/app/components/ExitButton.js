"use client";

import { useEffect } from "react";

/*
ExitButton component,
extracted into its own file so it renders on every page via layout.js,
handles both the visible exit button and the Escape key listener,
navbar links use the replace prop so every page visit overwrites the previous
history entry instead of pushing a new one, meaning only one site entry ever
exists in the browser history at a time,
exit then uses location.replace() to overwrite that single remaining entry
with Google, so the back button cannot return to the site
*/
export default function ExitButton() {

  /*
  Escape key quick-exit listener,
  listens on the keyup event instead of keydown because browsers cancel any navigation
  that starts during the keydown phase of the Escape key,
  by waiting for keyup the navigation fires after the browser's cancellation window has passed,
  window.addEventListener attaches the listener globally so it works anywhere on the page,
  the return function removes the listener when the component unmounts to prevent memory leaks,
  empty dependency array means this runs once on mount and cleans up on unmount
  */
  useEffect(() => {
    const handleKeyUp = (e) => {
      if (e.key === "Escape") {
        window.location.replace("https://www.google.com");
      }
    };
    window.addEventListener("keyup", handleKeyUp);
    return () => window.removeEventListener("keyup", handleKeyUp);
  }, []);

  return (
    /*
    Quick exit button,
    safety feature for users who need to leave the site quickly,
    fixed to the bottom right corner of the page so it is always visible,
    z-100 to ensure it sits above all other content including the navbar (higher stack order),
    bg-red-600 for red background color to signal urgency,
    hover:bg-white and hover:text-red-600 to invert colors on hover,
    border-2 border-red-600 keeps the red border visible in both states,
    rounded-full for pill shape,
    px-20 py-5 text-2xl for sizing,
    shadow-lg for depth,
    transition-all and hover:scale-110 for smooth scale animation on hover,
    onClick uses location.replace so the site is removed from browser history,
    aria-label and title for accessibility and tooltip text
    */
    <button
      id="exit-button"
      className="fixed bottom-4 right-4 z-100 bg-red-600 hover:bg-white text-white hover:text-red-600 border-2
      border-red-600 rounded-full px-10 py-5 text-2xl font-semibold shadow-lg transition-all hover:scale-110"
      aria-label="Quick exit button"
      title="Quick Exit (exit to google)"
      onClick={() => window.location.replace("https://www.google.com")}
    >
      Safe Exit
    </button>
  );
}
