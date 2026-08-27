"use client";

import { useState, useEffect } from "react";
import Image from "next/image";
import { generalCategory } from "./general";
import { legalCategory } from "./legal";
import { thingsToKnowCategory } from "./things-to-know";
import { unhealthySignsCategory } from "./unhealthy-signs";
import { traumaCategory } from "./trauma";
import { safetyPlanningCategory } from "./safety-planning";
const RESOURCE_CATEGORIES = [
  generalCategory,
  legalCategory,
  thingsToKnowCategory,
  unhealthySignsCategory,
  traumaCategory,
  safetyPlanningCategory,
  { id: "science-of-hope", title: "The Science of Hope", subtitle: "Understanding hope and measuring your resilience", resources: [] },
];

// ── Category icons ────────────────────────────────────────────────────────────

const ICONS = {
  general: (
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.24h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.81a16 16 0 0 0 6.29 6.29l.95-.88a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" />
    </svg>
  ),
  legal: (
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
      <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
    </svg>
  ),
  "things-to-know": (
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5" />
      <path d="M9 18h6" />
      <path d="M10 22h4" />
    </svg>
  ),
  "unhealthy-signs": (
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
      <line x1="12" y1="9" x2="12" y2="13" />
      <line x1="12" y1="17" x2="12.01" y2="17" />
    </svg>
  ),
  trauma: (
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
    </svg>
  ),
  "safety-planning": (
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <circle cx="12" cy="12" r="10" />
      <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76" />
    </svg>
  ),
  "science-of-hope": (
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
    </svg>
  ),
};

// ── Shared modal shell ────────────────────────────────────────────────────────

function Modal({ title, subtitle, onClose, children }) {
  useEffect(() => {
    const onKey = (e) => { if (e.key === "Escape") onClose(); };
    window.addEventListener("keyup", onKey);
    return () => window.removeEventListener("keyup", onKey);
  }, [onClose]);

  return (
    <div
      className="fixed inset-0 bg-black/60 z-200 flex items-center justify-center p-4"
      onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}
      role="dialog"
      aria-modal="true"
      aria-label={title}
    >
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[80vh] flex flex-col">
        <div className="flex items-start justify-between p-6 border-b border-gray-100 shrink-0">
          <div>
            <h2 className="text-xl font-semibold text-brand leading-snug">{title}</h2>
            {subtitle && <p className="text-sm text-gray-500 mt-1">{subtitle}</p>}
          </div>
          <button
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

// ── Modal variants ────────────────────────────────────────────────────────────

function SubResourceModal({ resource, onClose }) {
  return (
    <Modal title={resource.name} subtitle={resource.description} onClose={onClose}>
      <div className="divide-y-2 divide-purple-300">
        {resource.subResources.map((sub, index) => sub.alert ? (
          <div key={index} className="px-6 py-5 bg-amber-50">
            <div className="border-l-4 border-amber-400 pl-4 space-y-2">
              {sub.services && sub.services.map((line, i) => (
                <p key={i} className="text-sm text-amber-900 leading-relaxed">{line}</p>
              ))}
            </div>
          </div>
        ) : (
          <div key={index} className="p-6">
            <p className="font-semibold text-gray-800 mb-1">{sub.name}</p>
            {sub.description && (
              <p className="text-sm text-gray-600 leading-relaxed mb-2">{sub.description}</p>
            )}
            {sub.services && sub.services.length > 0 && (
              <ul className="mb-2 space-y-0.5">
                {sub.services.map((s, i) => (
                  <li key={i} className="flex items-start gap-1.5 text-sm text-gray-600">
                    <span className="text-brand mt-0.5 shrink-0" aria-hidden="true">•</span>
                    <span>{s}</span>
                  </li>
                ))}
              </ul>
            )}
            {sub.locations && sub.locations.length > 0 && (
              <div className="mb-2 space-y-2">
                {sub.locations.map((loc, i) => (
                  <div key={i}>
                    <p className="text-sm font-medium text-gray-700">{loc.label}</p>
                    {loc.address && <p className="text-xs text-gray-500">{loc.address}</p>}
                    {loc.phone && (
                      <a href={`tel:${loc.phone.replace(/\D/g, "")}`} className="text-xs font-semibold text-brand hover:underline">
                        {loc.phone}
                      </a>
                    )}
                  </div>
                ))}
              </div>
            )}
            <div className="space-y-1">
              <div className="flex flex-wrap gap-4">
                {sub.phone && (
                  <a href={`tel:${sub.phone.replace(/\D/g, "")}`} className="text-xs font-semibold text-brand hover:underline">
                    {sub.phone}
                  </a>
                )}
                {sub.phones && sub.phones.map((p, i) => {
                  const isSms = p.toLowerCase().startsWith("text");
                  const digits = p.replace(/\D/g, "");
                  return (
                    <a key={i} href={`${isSms ? "sms" : "tel"}:${digits}`} className="text-xs font-semibold text-brand hover:underline">
                      {p}
                    </a>
                  );
                })}
                {sub.url && (
                  <a href={sub.url} target="_blank" rel="noopener noreferrer" className="text-xs font-semibold text-brand hover:underline">
                    Visit website →
                  </a>
                )}
              </div>
              {sub.address && (
                <p className="text-xs text-gray-500">{sub.address}</p>
              )}
            </div>
          </div>
        ))}
      </div>
    </Modal>
  );
}

function InfoModal({ resource, onClose }) {
  return (
    <Modal title={resource.name} subtitle={resource.description} onClose={onClose}>
      <div>
        {resource.sections.map((section, i) => {
          const border = i > 0 && !section.noDivider ? "border-t-2 border-purple-300" : "";
          return section.alert ? (
            <div key={i} className={`px-6 py-5 bg-amber-50 ${border}`}>
              <div className="border-l-4 border-amber-400 pl-4 space-y-2">
                {(Array.isArray(section.alert) ? section.alert : [section.alert]).map((line, j) => (
                  <p key={j} className="text-sm text-amber-900 leading-relaxed italic">{line}</p>
                ))}
              </div>
            </div>
          ) : section.myth ? (
            <div key={i} className={`px-6 py-5 ${border}`}>
              <p className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5">Myth</p>
              <p className="text-sm text-gray-700 leading-relaxed mb-3">{section.myth}</p>
              <div className="pt-3 border-t border-purple-100">
                <p className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5">Fact</p>
                <p className="text-sm text-gray-600 leading-relaxed">{section.fact}</p>
              </div>
            </div>
          ) : (
            <div key={i} className={`px-6 py-5 ${border}`}>
              {section.heading && (
                <h3 className="text-xs font-bold text-brand uppercase tracking-wider mb-2">{section.heading}</h3>
              )}
              {section.subheading && (
                <h4 className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5">{section.subheading}</h4>
              )}
              {section.intro && (
                <p className="text-sm text-gray-700 leading-relaxed mb-2">{section.intro}</p>
              )}
              {section.paragraphs && section.paragraphs.map((p, j) => (
                <p key={j} className="text-sm text-gray-700 leading-relaxed mb-2">{p}</p>
              ))}
              {section.bullets && section.bullets.length > 0 && (
                <ul className="space-y-1.5">
                  {section.bullets.map((b, j) => (
                    <li key={j} className="flex items-start gap-1.5 text-sm text-gray-600">
                      <span className="text-brand mt-0.5 shrink-0" aria-hidden="true">•</span>
                      <span>{b}</span>
                    </li>
                  ))}
                </ul>
              )}
              {section.outro && (
                <p className="text-sm text-gray-700 leading-relaxed mt-2">{section.outro}</p>
              )}
            </div>
          );
        })}
        {resource.disclaimer && (
          <div className="px-6 py-5 border-t-2 border-purple-300">
            <p className="text-xs text-gray-400 leading-relaxed italic">{resource.disclaimer}</p>
          </div>
        )}
      </div>
    </Modal>
  );
}

function ImageModal({ resource, onClose }) {
  return (
    <Modal title={resource.name} subtitle={resource.description} onClose={onClose}>
      <div className="p-6">
        <Image
          src={resource.image}
          alt={resource.name}
          width={0}
          height={0}
          sizes="100vw"
          className="w-full h-auto rounded-lg"
        />
        {resource.disclaimer && (
          <p className="text-xs text-gray-400 leading-relaxed italic mt-4">{resource.disclaimer}</p>
        )}
      </div>
    </Modal>
  );
}

// ── Resource button ───────────────────────────────────────────────────────────

const MODAL_MAP = { modal: SubResourceModal, image: ImageModal, info: InfoModal };

function ResourceButton({ resource }) {
  const [modalOpen, setModalOpen] = useState(false);
  const ActiveModal = MODAL_MAP[resource.type];

  return (
    <>
      <button
        onClick={() => setModalOpen(true)}
        className="group flex items-center py-4 border-b border-gray-100 last:border-0 hover:bg-brand -mx-6 px-6 transition-all duration-300 w-full text-left"
      >
        <div className="flex items-center justify-between w-full">
          <div className="text-left">
            <p className="font-semibold text-brand group-hover:text-white transition-colors duration-300">{resource.name}</p>
            {resource.description && (
              <p className="text-sm text-gray-500 group-hover:text-white/75 mt-0.5 transition-colors duration-300">{resource.description}</p>
            )}
          </div>
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" className="shrink-0 text-brand group-hover:text-white group-hover:translate-x-1 ml-4 transition-all duration-300">
            <polyline points="9 18 15 12 9 6" />
          </svg>
        </div>
      </button>
      {modalOpen && ActiveModal && <ActiveModal resource={resource} onClose={() => setModalOpen(false)} />}
    </>
  );
}

// ── Science of Hope inline content ───────────────────────────────────────────

const HOPE_QUESTIONS = [
  { id: 1, text: "I can think of many ways to get out of a jam." },
  { id: 2, text: "I energetically pursue my goals." },
  { id: 3, text: "There are lots of ways around any problem." },
  { id: 4, text: "I can think of many ways to get the things in life that are most important to me." },
  { id: 5, text: "Even when others get discouraged, I know I can find a way to solve my problem." },
  { id: 6, text: "My past experiences have prepared me well for my future." },
  { id: 7, text: "I've been pretty successful in life." },
  { id: 8, text: "I meet the goals that I set for myself." },
];

const ANSWER_LABELS = {
  1: "Definitely False", 2: "Mostly False", 3: "Somewhat False", 4: "Slightly False",
  5: "Slightly True",    6: "Somewhat True", 7: "Mostly True",   8: "Definitely True",
};

function getHopeLevel(score) {
  if (score <= 16) return { label: "Low Hope",      color: "text-red-600",   bg: "bg-red-50   border-red-200"   };
  if (score <= 39) return { label: "Slight Hope",   color: "text-amber-600", bg: "bg-amber-50 border-amber-200" };
  if (score <= 55) return { label: "Moderate Hope", color: "text-blue-600",  bg: "bg-blue-50  border-blue-200"  };
  return              { label: "High Hope",      color: "text-green-600", bg: "bg-green-50 border-green-200" };
}

function ScienceOfHopeContent() {
  const [answers, setAnswers] = useState({});
  const [showResults, setShowResults] = useState(false);

  const answered = Object.keys(answers).length;
  const allAnswered = answered === HOPE_QUESTIONS.length;
  const pathways = [1, 3, 4, 5].reduce((s, id) => s + (answers[id] || 0), 0);
  const willpower = [2, 6, 7, 8].reduce((s, id) => s + (answers[id] || 0), 0);
  const total = pathways + willpower;
  const level = showResults ? getHopeLevel(total) : null;

  const handleAnswer = (qId, val) => {
    setAnswers(prev => ({ ...prev, [qId]: val }));
    setShowResults(false);
  };

  const printResults = () => {
    const levelColor = { "Low Hope": "#dc2626", "Slight Hope": "#d97706", "Moderate Hope": "#2563eb", "High Hope": "#16a34a" }[level.label] || "#5c0f8b";
    const win = window.open("", "_blank");
    win.document.write(`<!DOCTYPE html><html><head><title>Adult Hope Scale Results</title><link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet"><style>
      @page{margin:.5in}
      body{font-family:'Montserrat',sans-serif;font-size:14px;color:#1f2937;max-width:680px;margin:28px auto}
      h1{color:#5c0f8b;font-size:24px;margin:0 0 2px 0}
      .sub{color:#6b7280;font-size:13px;margin:0 0 16px 0}
      .banner{text-align:center;padding:14px 16px;border-radius:9px;background:#f5f3ff;border:1px solid #ddd6fe;margin-bottom:14px}
      .label{font-size:20px;font-weight:bold;color:${levelColor}}
      .total{font-size:13px;color:#6b7280}
      .grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px}
      .box{border:1px solid #e5e7eb;border-radius:8px;padding:12px;text-align:center}
      .bl{font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em}
      .bv{font-size:28px;font-weight:bold;color:#5c0f8b;line-height:1.2}
      .bn{font-size:11px;color:#9ca3af}
      .ranges{background:#f9fafb;border-radius:7px;padding:8px 14px;margin-bottom:16px;font-size:12px;color:#4b5563}
      .qt{font-size:15px;font-weight:bold;color:#5c0f8b;margin-bottom:8px;border-bottom:1px solid #e5e7eb;padding-bottom:5px}
      .q{margin-bottom:7px;font-size:13px;line-height:1.4}
      .qa{color:#5c0f8b;font-weight:bold;font-size:12px}
      .cite{margin-top:16px;font-size:11px;color:#9ca3af;font-style:italic;border-top:1px solid #e5e7eb;padding-top:10px}
    </style></head><body>
      <h1>Adult Hope Scale Results</h1>
      <p class="sub">Harbor Safe House &amp; Advocacy Center</p>
      <div class="banner">
        <div class="label">${level.label}</div>
        <div class="total">Total Score: ${total} / 64</div>
      </div>
      <div class="grid">
        <div class="box"><div class="bl">Pathways Score</div><div class="bv">${pathways}</div><div class="bn">items 1, 3, 4, 5 — out of 32</div></div>
        <div class="box"><div class="bl">Willpower Score</div><div class="bv">${willpower}</div><div class="bn">items 2, 6, 7, 8 — out of 32</div></div>
      </div>
      <div class="ranges"><strong>Score Ranges:</strong> Low Hope: 8–16 · Slight Hope: 17–39 · Moderate Hope: 40–55 · High Hope: 56–64</div>
      <div class="qt">Your Responses</div>
      ${HOPE_QUESTIONS.map(q => `<div class="q"><strong>${q.id}.</strong> ${q.text} <span class="qa">— ${answers[q.id]}: ${ANSWER_LABELS[answers[q.id]]}</span></div>`).join("")}
      <p class="cite">Snyder, C. R., Harris, C., Anderson, J. R., Holeran, S. A., Irving, L. M., Sigmon, S. T., et al. (1991). The will and the ways: Development and validation of an individual-differences measure of hope. <em>Journal of Personality and Social Psychology, 60</em>, 570–585.</p>
    </body></html>`);
    win.document.close();
    win.focus();
    win.print();
  };

  return (
    <div className="space-y-5 text-sm text-gray-700 leading-relaxed">

      {/* Gentle Reminder */}
      <div className="bg-purple-50 border border-purple-100 rounded-xl p-5 text-center">
        <p className="text-xs font-bold text-brand uppercase tracking-wider mb-3">A Gentle Reminder</p>
        <div className="space-y-1 text-gray-600 italic">
          <p>You do not have to fix everything today.</p>
          <p>You do not have to do this perfectly.</p>
          <p>You do not have to do it alone.</p>
          <p>Hope grows one step at a time.</p>
          <p>And every step forward—no matter how small—counts.</p>
        </div>
      </div>

      <p>Hope is a word people use often, especially during hard times. We might say, "I hope things get better," or "I'm trying to stay hopeful." But hope is more than a feeling or a wish. Research shows that hope is something people can learn and build, step by step. Hope is linked to better stress coping, stronger problem solving, more motivation, and greater resilience.</p>

      <p>The science of hope shows that hope can grow again. This is good news, especially for people who have been through stress, trauma, or major life changes. If hope feels low right now, it doesn't mean anything is wrong with you. It may simply mean you've been carrying a lot.</p>

      <div>
        <p className="font-semibold text-brand mb-1">Hope Is Not Pretending</p>
        <p>Hope does not mean pretending everything is okay. It doesn't mean ignoring problems or forcing yourself to be positive. Real hope says, "This is hard, and I can still look for a way forward." Hope makes room for honesty and struggle while still believing that change is possible. Hope doesn't remove challenges. It helps people face them one step at a time.</p>
      </div>

      <div className="space-y-3">
        <p className="font-semibold text-brand">The Three Parts of Hope</p>
        <p>Researchers have found that hope has three simple parts: <strong>goals</strong>, <strong>pathways</strong>, and <strong>willpower</strong>. A goal is something that matters to you. It can be big or small. Examples include finding safe housing, saving money, getting a job, feeling more stable. A goal gives you a direction. It's okay if your goal changes over time.</p>
        <p>Pathways are the different ways you might reach your goal. There is usually more than one way to reach your goal. If one plan doesn't work, another might. Hitting a barrier does not mean you failed, it may just mean you need a different route. Case managers can help you find pathways and connect you to resources that can help you meet your goals.</p>
        <p>Agency, or willpower, is the belief that your effort matters. After trauma or many setbacks, this belief can feel small. Willpower grows when you practice principles of self-care. Another part of willpower is making sure your steps on your pathway aren't too big to manage. When hope feels low, even the smallest steps matter. As you take care to replenish your energy, your willpower will move you along your pathway and those tiny steps will increase your hope.</p>
      </div>

      <p>When hope feels low you might notice that you feel stuck, tired, overwhelmed, have difficulty making plans, or feel like you want to give up. These are common responses to stress and trauma, not personal failures. Hope grows slowly in small and steady ways. Notice your effort and give yourself credit for trying. Progress, no matter how little, is still progress. You don't have to build hope alone — borrowing hope by talking with an advocate, counselor, case manager, or trusted person will grow your hope as well.</p>

      <p>You can measure your hope using the Adult Hope Scale below. It can be very helpful in determining if you need help with pathways or willpower to meet your goals.</p>

      {/* Adult Hope Scale */}
      <div className="border-t-2 border-purple-200 pt-6 space-y-6">
        <div className="text-center">
          <p className="text-base font-bold text-brand uppercase tracking-wide">The Adult Hope Scale</p>
          <p className="text-xs text-gray-500 mt-1">Read each sentence carefully and select the number that best describes you in most situations. There are no right or wrong answers.</p>
        </div>

        <div className="grid grid-cols-8 gap-1 bg-purple-50 rounded-lg px-3 py-3 text-center">
          {[
            [1, "Definitely", "False"],
            [2, "Mostly",     "False"],
            [3, "Somewhat",   "False"],
            [4, "Slightly",   "False"],
            [5, "Slightly",   "True" ],
            [6, "Somewhat",   "True" ],
            [7, "Mostly",     "True" ],
            [8, "Definitely", "True" ],
          ].map(([num, line1, line2]) => (
            <div key={num} className="flex flex-col items-center gap-0.5">
              <strong className="text-brand text-xs">{num}</strong>
              <span className="text-[9px] leading-none text-gray-500">{line1}</span>
              <span className="text-[9px] leading-none text-gray-500">{line2}</span>
            </div>
          ))}
        </div>

        <div className="space-y-5">
          {HOPE_QUESTIONS.map((q) => (
            <div key={q.id}>
              <p className="mb-2 text-gray-700">
                <span className="font-semibold text-brand mr-1.5">{q.id}.</span>{q.text}
              </p>
              <div className="flex gap-1.5">
                {[1, 2, 3, 4, 5, 6, 7, 8].map(val => (
                  <button
                    key={val}
                    onClick={() => handleAnswer(q.id, val)}
                    className={`flex-1 h-9 rounded-lg text-sm font-bold transition-colors ${
                      answers[q.id] === val
                        ? "bg-brand text-white shadow-sm"
                        : "bg-gray-100 text-gray-500 hover:bg-purple-100 hover:text-brand"
                    }`}
                  >
                    {val}
                  </button>
                ))}
              </div>
            </div>
          ))}
        </div>

        <button
          onClick={() => setShowResults(true)}
          disabled={!allAnswered}
          className={`w-full py-3 rounded-xl font-semibold text-sm transition-colors ${
            allAnswered
              ? "bg-brand text-white hover:bg-brand/90"
              : "bg-gray-100 text-gray-400 cursor-not-allowed"
          }`}
        >
          {allAnswered ? "Calculate My Score" : `${answered} of 8 answered`}
        </button>

        {showResults && level && (
          <div className={`rounded-xl border p-5 space-y-4 ${level.bg}`}>
            <p className={`text-center text-lg font-bold ${level.color}`}>
              {level.label} — {total} / 64
            </p>
            <div className="grid grid-cols-2 gap-3">
              <div className="bg-white rounded-lg p-3 text-center">
                <p className="text-xs text-gray-500 mb-0.5">Pathways Score</p>
                <p className="text-2xl font-bold text-brand">{pathways}</p>
                <p className="text-[10px] text-gray-400">items 1, 3, 4, 5 — out of 32</p>
              </div>
              <div className="bg-white rounded-lg p-3 text-center">
                <p className="text-xs text-gray-500 mb-0.5">Willpower Score</p>
                <p className="text-2xl font-bold text-brand">{willpower}</p>
                <p className="text-[10px] text-gray-400">items 2, 6, 7, 8 — out of 32</p>
              </div>
            </div>
            <div className="text-xs text-gray-600 bg-white rounded-lg p-3">
              <p className="font-semibold text-gray-700 mb-1">Score Ranges</p>
              <p>Low Hope: 8–16 · Slight Hope: 17–39 · Moderate Hope: 40–55 · High Hope: 56–64</p>
            </div>
            <button
              onClick={printResults}
              className="w-full py-2 rounded-lg text-xs font-semibold bg-brand text-white hover:bg-purple-800 transition-colors"
            >
              Print / Save as PDF
            </button>
            <button
              onClick={() => { setAnswers({}); setShowResults(false); }}
              className="w-full py-2 rounded-lg text-xs font-semibold text-brand border border-brand/30 hover:bg-brand hover:text-white transition-colors"
            >
              Retake Assessment
            </button>
          </div>
        )}
      </div>

      <p className="text-xs text-gray-400 leading-relaxed italic border-t border-gray-100 pt-4">
        Snyder, C. R., Harris, C., Anderson, J. R., Holeran, S. A., Irving, L. M., Sigmon, S. T., et al. (1991). The will and the ways: Development and validation of an individual-differences measure of hope. <em>Journal of Personality and Social Psychology, 60</em>, 570–585.
      </p>

    </div>
  );
}

// ── Accordion category ────────────────────────────────────────────────────────

function CategoryAccordion({ category, isOpen, onToggle }) {
  return (
    <div className="border border-gray-200 rounded-xl overflow-hidden">
      <button
        onClick={onToggle}
        aria-expanded={isOpen}
        className="w-full flex items-center justify-between p-6 text-left bg-white hover:bg-purple-50 transition-colors"
      >
        <div className="flex items-center gap-4">
          <div className="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center shrink-0 text-brand">
            {ICONS[category.id]}
          </div>
          <div>
            <h2 className="text-base font-semibold text-brand leading-snug">{category.title}</h2>
            {category.subtitle && <p className="text-sm text-gray-500 mt-0.5">{category.subtitle}</p>}
          </div>
        </div>
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
          aria-hidden="true"
          className={`shrink-0 text-brand transition-transform duration-300 ${isOpen ? "rotate-180" : ""}`}
        >
          <polyline points="6 9 12 15 18 9" />
        </svg>
      </button>

      <div
        className="grid transition-[grid-template-rows] duration-300 ease-in-out"
        style={{ gridTemplateRows: isOpen ? "1fr" : "0fr" }}
      >
        <div className="overflow-hidden">
          <div className="px-6 pb-6 border-t border-gray-100 bg-white">
            {category.id === "science-of-hope" ? (
              <div className="pt-5">
                <ScienceOfHopeContent />
              </div>
            ) : (
              <div className="pt-2">
                {category.resources.map((resource, index) => (
                  <ResourceButton key={index} resource={resource} />
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function Resources() {
  const [openId, setOpenId] = useState(null);

  return (
    <main>

      {/* ── RESOURCE CATEGORIES ────────────────────────────────────── */}
      <section className="py-20 px-4 bg-white">
        <div className="max-w-3xl mx-auto">

          <h1 className="text-4xl md:text-5xl font-semibold text-brand text-center mb-6">Resources</h1>

          <p className="text-gray-600 text-center leading-relaxed mb-12">
            Select a category below to explore available resources. If you need immediate
            assistance, our crisis hotline is available 24 hours a day, 7 days a week.
          </p>

          <div className="flex flex-col gap-3">
            {RESOURCE_CATEGORIES.map((category) => (
              <CategoryAccordion
                key={category.id}
                category={category}
                isOpen={openId === category.id}
                onToggle={() => setOpenId((prev) => (prev === category.id ? null : category.id))}
              />
            ))}
          </div>

        </div>
      </section>

    </main>
  );
}
