"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Modal from "../components/Modal";

/*
A newsletter issue read one page at a time, drawn by pdf.js (Mozilla's PDF
reader), which loads only when a preview opens. The page is fitted to the
pop-up and never scrolls: turn it with the arrow buttons, the arrow keys,
a swipe, or a tap on the left or right half of the page.

On a phone, where a whole page is too small to read, Zoom enlarges it and the
page is dragged around instead; while zoomed, the arrow buttons and keys turn
pages and each new page starts at its top left.

The X (top left) is the only way to close it; a click outside does nothing,
and Esc is still the site's Safe Exit. Pages are pictures, so a screen reader
can't read them: the Download link stays for that, here and on the list.
*/

// how far a finger must travel sideways to count as a swipe rather than a tap
const SWIPE_PX = 40;
const TAP_PX = 10;

// zoom is offered below this width (Tailwind's sm), where the page is too small to read
const PHONE_WIDTH = 640;
const ZOOM_STEPS = [1, 1.5, 2, 3];
// the largest canvas side drawn, so a zoomed page stays within a phone's memory
const MAX_CANVAS_PX = 4096;

export default function NewsletterPreview({ issue, onClose }) {
  const [pdf, setPdf] = useState(null);
  const [status, setStatus] = useState("loading");
  const [page, setPage] = useState(1);
  // fit: the space the page is fitted into; room: the whole page area it moves within
  const [stage, setStage] = useState(null);
  // the page's size at 100%, once its first drawing is measured
  const [base, setBase] = useState(null);
  const [zoomStep, setZoomStep] = useState(0);
  const [pan, setPan] = useState({ x: 0, y: 0 });
  const stageRef = useRef(null);
  const pageRef = useRef(null);
  const pointerRef = useRef(null);
  const url = issue.file.url;

  const zoom = stage?.isPhone ? ZOOM_STEPS[zoomStep] : 1;

  useEffect(() => {
    let cancelled = false;
    let loading;

    (async () => {
      try {
        const pdfjs = await import("pdfjs-dist");
        pdfjs.GlobalWorkerOptions.workerSrc = new URL("pdfjs-dist/build/pdf.worker.min.mjs", import.meta.url).toString();
        // disableRange: one plain GET, since the API's CORS rules don't allow a Range header
        loading = pdfjs.getDocument({ url, disableRange: true });
        const loaded = await loading.promise;
        if (!cancelled) {
          setPdf(loaded);
          setStatus("ready");
        }
      } catch {
        if (!cancelled) setStatus("error");
      }
    })();

    return () => {
      cancelled = true;
      loading?.destroy();
    };
  }, [url]);

  // re-measured when the window resizes or a phone turns
  useEffect(() => {
    const element = stageRef.current;
    const observer = new ResizeObserver(([entry]) => {
      setStage({
        fit: { width: entry.contentRect.width, height: entry.contentRect.height },
        room: { width: element.clientWidth, height: element.clientHeight },
        isPhone: window.innerWidth < PHONE_WIDTH,
      });
    });
    observer.observe(element);
    return () => observer.disconnect();
  }, []);

  /*
  Draws the page on a new canvas and swaps it in once finished, so the old page
  (stretched to the new size meanwhile) stays until the new one is ready and two
  drawings never share a canvas
  */
  useEffect(() => {
    if (!pdf || !stage?.fit.width || !stage?.fit.height) return;
    let cancelled = false;
    let drawing;

    pdf.getPage(page).then((pdfPage) => {
      if (cancelled) return;
      const natural = pdfPage.getViewport({ scale: 1 });
      const fit = Math.min(stage.fit.width / natural.width, stage.fit.height / natural.height);
      const shown = { w: natural.width * fit, h: natural.height * fit };
      setBase((current) => (current?.w === shown.w && current?.h === shown.h ? current : shown));

      const sharpness = Math.min(
        window.devicePixelRatio || 1,
        MAX_CANVAS_PX / (shown.w * zoom),
        MAX_CANVAS_PX / (shown.h * zoom),
      );
      const viewport = pdfPage.getViewport({ scale: fit * zoom * sharpness });

      const canvas = document.createElement("canvas");
      canvas.width = Math.floor(viewport.width);
      canvas.height = Math.floor(viewport.height);
      canvas.className = "block w-full h-full bg-white shadow-md";

      drawing = pdfPage.render({ canvas, viewport });
      return drawing.promise.then(() => {
        if (!cancelled) pageRef.current?.replaceChildren(canvas);
      });
    }).catch(() => {
      // a cancelled drawing rejects too, and that one isn't an error
      if (!cancelled) setStatus("error");
    });

    return () => {
      cancelled = true;
      drawing?.cancel();
    };
  }, [pdf, page, stage, zoom]);

  /*
  Keeps the page in view: a page smaller than the area is centred on that side,
  a bigger one can be moved until its edge meets the area's edge, no further
  */
  const keepInView = useCallback(({ x, y }, level) => {
    if (!base || !stage) return { x: 0, y: 0 };
    const side = (offset, size, room) =>
      size <= room ? (room - size) / 2 : Math.min(0, Math.max(room - size, offset));
    return {
      x: side(x, base.w * level, stage.room.width),
      y: side(y, base.h * level, stage.room.height),
    };
  }, [base, stage]);

  const shownPan = keepInView(pan, zoom);

  const pageCount = pdf?.numPages ?? 0;
  const turn = useCallback((delta) => {
    setPage((current) => Math.min(Math.max(current + delta, 1), Math.max(pageCount, 1)));
    // a new page starts at its top left
    setPan({ x: 0, y: 0 });
  }, [pageCount]);

  // zooms around the middle of the page area, so what was in the middle stays there
  const changeZoom = (step) => {
    const nextStep = Math.min(Math.max(zoomStep + step, 0), ZOOM_STEPS.length - 1);
    const next = ZOOM_STEPS[nextStep];
    const middle = { x: stage.room.width / 2, y: stage.room.height / 2 };
    setPan(keepInView({
      x: middle.x - (middle.x - shownPan.x) * (next / zoom),
      y: middle.y - (middle.y - shownPan.y) * (next / zoom),
    }, next));
    setZoomStep(nextStep);
  };

  useEffect(() => {
    const onKey = (event) => {
      if (event.key === "ArrowRight" || event.key === "PageDown") {
        event.preventDefault();
        turn(1);
      } else if (event.key === "ArrowLeft" || event.key === "PageUp") {
        event.preventDefault();
        turn(-1);
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [turn]);

  // a second finger down makes it a pinch, which never turns or moves the page
  const onPointerDown = (event) => {
    const gesture = pointerRef.current;
    if (gesture) {
      pointerRef.current = { ...gesture, fingers: gesture.fingers + 1, pinch: true };
      return;
    }
    pointerRef.current = { x: event.clientX, y: event.clientY, pan: shownPan, fingers: 1, pinch: false };
    if (zoom > 1) event.currentTarget.setPointerCapture?.(event.pointerId);
  };

  const onPointerMove = (event) => {
    const start = pointerRef.current;
    if (!start || start.pinch || zoom === 1) return;
    setPan({ x: start.pan.x + event.clientX - start.x, y: start.pan.y + event.clientY - start.y });
  };

  const onPointerUp = (event) => {
    const start = pointerRef.current;
    if (!start) return;
    pointerRef.current = start.fingers > 1 ? { ...start, fingers: start.fingers - 1 } : null;
    // zoomed, a drag moves the page instead of turning it
    if (start.pinch || status !== "ready" || zoom > 1) return;

    const dx = event.clientX - start.x;
    const dy = event.clientY - start.y;

    if (Math.abs(dx) > SWIPE_PX && Math.abs(dx) > Math.abs(dy)) {
      turn(dx < 0 ? 1 : -1);
    } else if (Math.abs(dx) < TAP_PX && Math.abs(dy) < TAP_PX) {
      const box = event.currentTarget.getBoundingClientRect();
      turn(event.clientX < box.left + box.width / 2 ? -1 : 1);
    }
  };

  const ready = status === "ready";
  const outlineButton = "tap-target shrink-0 inline-flex items-center justify-center gap-1.5 rounded-lg font-semibold text-sm border-2 border-brand text-brand " +
    "hover:bg-brand hover:text-white transition-all disabled:opacity-40 disabled:pointer-events-none";
  const arrowClass = `${outlineButton} px-4 py-2`;
  const zoomClass = `${outlineButton} w-10 h-10 text-lg`;

  return (
    <Modal title={issue.title} centered closeLeft viewer onClose={onClose}>
      {/*
      The page area: touch-action pinch-zoom lets a phone still zoom the whole
      screen, while one finger comes to us, for a swipe or for moving a zoomed page;
      no padding on a phone, where the page needs every pixel of the width
      */}
      <div
        ref={stageRef}
        className={`relative flex-1 min-h-0 overflow-hidden bg-gray-100 p-0 sm:p-4 select-none ${zoom > 1 ? "cursor-grab" : "cursor-pointer"}`}
        style={{ touchAction: "pinch-zoom" }}
        onPointerDown={onPointerDown}
        onPointerMove={onPointerMove}
        onPointerUp={onPointerUp}
        onPointerCancel={() => { pointerRef.current = null; }}
      >
        <div
          ref={pageRef}
          role="img"
          aria-label={ready ? `Page ${page} of ${pageCount}` : undefined}
          className={ready && base ? "absolute left-0 top-0" : "hidden"}
          style={base ? {
            width: `${base.w * zoom}px`,
            height: `${base.h * zoom}px`,
            transform: `translate(${shownPan.x}px, ${shownPan.y}px)`,
          } : undefined}
        />
        {status === "loading" && <p className="absolute inset-0 flex items-center justify-center text-sm text-gray-600">Loading…</p>}
        {status === "error" && (
          <p className="absolute inset-0 flex items-center justify-center p-4 text-sm text-gray-700 text-center">
            This preview couldn&apos;t load. You can still download the PDF below.
          </p>
        )}
      </div>

      <div className="shrink-0 border-t border-gray-100 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <button type="button" onClick={() => turn(-1)} disabled={!ready || page <= 1} className={arrowClass}>
            <span aria-hidden="true">←</span> Previous
          </button>
          <p className="text-sm text-gray-700 min-w-[6.5rem] text-center" aria-live="polite">
            {ready && `Page ${page} of ${pageCount}`}
          </p>
          <button type="button" onClick={() => turn(1)} disabled={!ready || page >= pageCount} className={arrowClass}>
            Next <span aria-hidden="true">→</span>
          </button>
        </div>

        {/* phones only: a whole page fits a desktop screen readably */}
        <div className="flex items-center gap-2 sm:hidden">
          <button type="button" onClick={() => changeZoom(-1)} disabled={!ready || zoomStep === 0} className={zoomClass} aria-label="Zoom out">
            <span aria-hidden="true">−</span>
          </button>
          <p className="text-sm text-gray-700 w-12 text-center" aria-live="polite">
            <span className="sr-only">Zoom </span>{Math.round(zoom * 100)}%
          </p>
          <button type="button" onClick={() => changeZoom(1)} disabled={!ready || zoomStep === ZOOM_STEPS.length - 1} className={zoomClass} aria-label="Zoom in">
            <span aria-hidden="true">+</span>
          </button>
        </div>

        <a
          href={url}
          target="_blank"
          rel="noopener noreferrer"
          className="tap-target text-sm font-semibold text-brand hover:underline"
        >
          Download PDF<span className="sr-only"> to read with a screen reader or save it</span>
        </a>
      </div>
    </Modal>
  );
}
