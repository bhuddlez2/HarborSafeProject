"use client";

import { useEffect } from "react";

/*
HashHighlight briefly tints the service cards matching the URL hash (e.g. #shelter)
so a visitor arriving from a home page "Learn more" link can see which cards they
came for. The hash picks cards by their data-highlight attribute rather than id, so
one link can light up several cards (Counseling & Advocacy -> Crisis Counseling and
Court Advocacy); the browser still scrolls to the appropriate element.
*/
export default function HashHighlight() {
  useEffect(() => {
    const highlight = () => {
      const key = decodeURIComponent(window.location.hash.slice(1));
      if (!key) return;
      const cards = document.querySelectorAll(`.service-card[data-highlight="${CSS.escape(key)}"]`);
      // remove, force a reflow, then re-add so the animation restarts on repeat visits
      cards.forEach((card) => {
        card.classList.remove("is-highlighted");
        void card.offsetWidth;
        card.classList.add("is-highlighted");
      });
    };

    highlight();
    window.addEventListener("hashchange", highlight);
    return () => window.removeEventListener("hashchange", highlight);
  }, []);

  return null;
}
