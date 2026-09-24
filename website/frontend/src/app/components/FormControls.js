"use client";

/*
FormControls: the pieces shared by the two public forms.

Field wraps a label, its control and any server-side error for that field.
htmlFor/id tie the label to the control, and aria-describedby points at the
hint and the error so a screen reader reads them as part of the field rather
than as loose text nearby.
*/
export function Field({ id, label, hint, error, requirement = "optional", children }) {
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="font-semibold text-gray-900">
        {label}
        {requirement === "required" && <span className="text-red-700 ml-1" aria-hidden="true">*</span>}
        {requirement === "optional" && <span className="text-gray-500 font-normal ml-2 text-sm">Optional</span>}
      </label>

      {hint && <p id={`${id}-hint`} className="text-sm text-gray-600">{hint}</p>}

      {children}

      {error && (
        <p id={`${id}-error`} className="text-sm font-semibold text-red-700">{error}</p>
      )}
    </div>
  );
}

export const inputClass =
  "w-full border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 " +
  "focus:outline-none focus:border-brand focus:ring-2 focus:ring-purple-200 transition-all";

export const describedBy = (id, hint, error) =>
  [hint && `${id}-hint`, error && `${id}-error`].filter(Boolean).join(" ") || undefined;

export function SubmitButton({ status, children }) {
  return (
    <button
      type="submit"
      disabled={status === "submitting"}
      className="self-start bg-brand text-white px-8 py-3 rounded-lg font-semibold
      hover:bg-purple-800 transition-all disabled:opacity-50 disabled:cursor-not-allowed
      focus:outline-none focus-visible:ring-2 focus-visible:ring-brand"
    >
      {status === "submitting" ? "Sending…" : children}
    </button>
  );
}

export function FormMessage({ tone, children }) {
  const styles =
    tone === "success"
      ? "bg-purple-50 border-purple-200 text-brand"
      : "bg-red-50 border-red-200 text-red-800";

  return (
    <p role="alert" className={`border rounded-lg px-4 py-3 font-semibold ${styles}`}>
      {children}
    </p>
  );
}

export function FormSection({ status, children }) {
  if (status === "loading") {
    return <p className="text-gray-600 text-center">Loading the form…</p>;
  }

  if (status === "error") {
    return (
      <FormMessage tone="error">
        We could not load this form just now. Please call or text our crisis line at
        (423) 476-3886, or try again in a few minutes.
      </FormMessage>
    );
  }

  return children;
}

export function CrisisHotlineStrip() {
  return (
    <div className="flex items-center justify-center gap-8 px-8 py-5 bg-purple-50 border-b border-purple-100 flex-wrap">
      <span className="text-sm tracking-widest text-purple-700">24/7 Confidential Crisis Hotline</span>
      <div className="flex items-center gap-5">
        <a href="tel:423-476-3886" className="text-lg font-semibold text-brand hover:underline transition-all">Call (423) 476-3886</a>
        <span className="text-purple-300">|</span>
        <a href="sms:423-715-9614" className="text-lg font-semibold text-brand hover:underline transition-all">Text (423) 715-9614</a>
      </div>
      <span className="text-sm text-purple-700">Free &nbsp;·&nbsp; Confidential &nbsp;·&nbsp; 24 hours a day</span>
    </div>
  );
}
