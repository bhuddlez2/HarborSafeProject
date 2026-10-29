/*
ExitButton component,
extracted into its own file so it renders on every page via layout.js,
navbar links (and every other internal link) use the replace prop so every page visit
overwrites the tab's current Back/Forward entry instead of pushing a new one, meaning only
one site entry ever exists in the tab's Back/Forward history at a time,
exit then uses location.replace() to overwrite that single remaining entry
with Google so the back button cannot return to the site
*/

export const SAFE_EXIT_URL = "https://www.google.com";

export const SAFE_EXIT_SCRIPT = `(function () {
  function leave() { window.location.replace(${JSON.stringify(SAFE_EXIT_URL)}); }
  window.addEventListener("keyup", function (e) { if (e.key === "Escape") leave(); });
  document.addEventListener("click", function (e) {
    if (e.target && e.target.closest && e.target.closest("#exit-button")) leave();
  });
})();`;

export default function ExitButton() {
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
    clicking it is handled by SAFE_EXIT_SCRIPT above (matched by id="exit-button"),
    aria-label and title for accessibility and tooltip text
    */
    <button
      id="exit-button"
      type="button"
      className="fixed bottom-4 right-4 z-100 bg-red-600 hover:bg-white text-white hover:text-red-600 border-2
      border-red-600 rounded-full px-10 py-5 text-2xl font-semibold shadow-lg transition-all hover:scale-110"
      aria-label="Quick exit button"
      title="Quick Exit (exit to google)"
    >
      Safe Exit
    </button>
  );
}
