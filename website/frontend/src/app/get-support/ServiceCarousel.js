"use client";

import { Children, useCallback, useEffect, useRef, useState } from "react";

/*
ServiceCarousel lays the service cards out as a swipeable row on phones (B17) and
leaves them as the wrapped grid from md up. Every card stays on the page at every
size, so the home page's #shelter/#counseling links and HashHighlight still find them.
labels names each card for its dot, in the same order as the children.
*/
export default function ServiceCarousel({ labels, children }) {
  const scrollerRef = useRef(null);
  const [index, setIndex] = useState(0);
  const count = Children.count(children);

  const cards = () => Array.from(scrollerRef.current?.children ?? []);

  // Centres a card in the row, scrollTo rather than scrollIntoView so the page itself never moves
  const goTo = useCallback((target, smooth = true) => {
    const scroller = scrollerRef.current;
    const card = scroller?.children[target];
    if (!card) return;
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    scroller.scrollTo({
      left: card.offsetLeft - (scroller.clientWidth - card.offsetWidth) / 2,
      behavior: smooth && !reduceMotion ? "smooth" : "instant",
    });
  }, []);

  // Keeps the dots in step with swiping, and with tabbing onto a card's link, by picking the card nearest the centre
  useEffect(() => {
    const scroller = scrollerRef.current;
    let frame = 0;
    const onScroll = () => {
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(() => {
        const centre = scroller.scrollLeft + scroller.clientWidth / 2;
        const distances = cards().map((card) => Math.abs(card.offsetLeft + card.offsetWidth / 2 - centre));
        setIndex(distances.indexOf(Math.min(...distances)));
      });
    };
    scroller.addEventListener("scroll", onScroll, { passive: true });
    return () => {
      cancelAnimationFrame(frame);
      scroller.removeEventListener("scroll", onScroll);
    };
  }, []);

  // A home page "Learn more" link opens the row on the first card it highlights
  useEffect(() => {
    const showHashCard = () => {
      const key = decodeURIComponent(window.location.hash.slice(1));
      const target = cards().findIndex((card) => card.dataset.highlight === key);
      if (key && target > -1) goTo(target, false);
    };
    showHashCard();
    window.addEventListener("hashchange", showHashCard);
    return () => window.removeEventListener("hashchange", showHashCard);
  }, [goTo]);

  const arrowClass =
    "p-3 rounded-full border border-purple-200 text-brand hover:bg-purple-50 transition-all disabled:opacity-40 disabled:hover:bg-transparent";

  return (
    <section aria-roledescription="carousel" aria-label="Services">
      {/*
      Below md: one row that snaps each card to the centre, -mx-4 px-4 runs it to the
      screen edges so the next card peeks in, the scrollbar is hidden since the dots
      show the position. md up: the wrapped, centred grid the page had before.
      */}
      <div
        ref={scrollerRef}
        className="flex gap-4 overflow-x-auto snap-x snap-mandatory -mx-4 px-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden md:flex-wrap md:justify-center md:gap-x-4 md:gap-y-8 md:overflow-visible md:mx-0 md:px-0"
      >
        {children}
      </div>

      {count > 1 && (
        <div className="md:hidden flex items-center justify-center gap-4 mt-8">
          <button
            type="button"
            onClick={() => goTo(index - 1)}
            disabled={index === 0}
            aria-label="Previous service"
            className={arrowClass}
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
              <polyline points="15 18 9 12 15 6" />
            </svg>
          </button>

          {/* Each dot is a 32px button around the small dot it draws, so it's easy to tap (B1) */}
          <div className="flex items-center">
            {labels.map((label, i) => (
              <button
                key={label}
                type="button"
                onClick={() => goTo(i)}
                aria-label={`${label}, service ${i + 1} of ${count}`}
                aria-current={i === index}
                className="group w-8 h-8 flex items-center justify-center"
              >
                <span
                  className={`w-2.5 h-2.5 rounded-full transition-all ${
                    i === index ? "bg-brand scale-125" : "bg-purple-200 group-hover:bg-purple-300"
                  }`}
                />
              </button>
            ))}
          </div>

          <button
            type="button"
            onClick={() => goTo(index + 1)}
            disabled={index === count - 1}
            aria-label="Next service"
            className={arrowClass}
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
              <polyline points="9 18 15 12 9 6" />
            </svg>
          </button>
        </div>
      )}
    </section>
  );
}
