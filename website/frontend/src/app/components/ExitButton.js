/*
ExitButton component,
extracted into its own file so it renders on every page via layout.js,
internal links push history entries as normal so Back and Forward move through the site,
Safe Exit then works one way through a latch in SAFE_EXIT_SCRIPT below
*/

export const SAFE_EXIT_URL = "https://www.google.com";

export const SAFE_EXIT_SCRIPT = `(function () {
  var KEY = "hshac-safe-exit";
  function exited() { try { return sessionStorage.getItem(KEY) === "1"; } catch (e) { return false; } }
  function go() {
    document.documentElement.style.visibility = "hidden";
    window.location.replace(${JSON.stringify(SAFE_EXIT_URL)});
  }
  function leave() { try { sessionStorage.setItem(KEY, "1"); } catch (e) {} go(); }

  var nav = window.performance && performance.getEntriesByType && performance.getEntriesByType("navigation")[0];
  if (exited() && nav && nav.type === "back_forward") go();
  window.addEventListener("pageshow", function (e) { if (e.persisted && exited()) go(); });

  window.addEventListener("keyup", function (e) { if (e.key === "Escape") leave(); });
  document.addEventListener("click", function (e) {
    if (e.target && e.target.closest && e.target.closest("[data-safe-exit]")) leave();
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
    data-safe-exit hands the click to SAFE_EXIT_SCRIPT above,
    aria-label and title for accessibility and tooltip text
    */
    <button
      id="exit-button"
      data-safe-exit
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
