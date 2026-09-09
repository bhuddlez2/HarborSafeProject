"use client";

import { useState } from "react";
import {
  SERVICES,
  RESOURCE_TYPES,
  COUNTIES,
  submitResourceRequest,
  submitServiceFeedback,
} from "../lib/forms";

/*
ContactContent: the Contact page, holding the two public forms.
*/

// ── Small shared pieces ───────────────────────────────────────────────────────

/*
Field wraps a label, its control and any server-side error for that field.
htmlFor/id tie the label to the control, and aria-describedby points at the
hint and the error so a screen reader reads them as part of the field rather
than as loose text nearby.
*/
function Field({ id, label, hint, error, requirement = "optional", children }) {
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

const inputClass =
  "w-full border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 " +
  "focus:outline-none focus:border-brand focus:ring-2 focus:ring-purple-200 transition-all";

/*
describedBy builds the aria-describedby value from whichever of the hint and
error actually exist, so the attribute is never left pointing at a missing id.
*/
const describedBy = (id, hint, error) =>
  [hint && `${id}-hint`, error && `${id}-error`].filter(Boolean).join(" ") || undefined;

function SubmitButton({ status, children }) {
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

function FormMessage({ tone, children }) {
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

// ── Resource request form ─────────────────────────────────────────────────────

const EMPTY_REQUEST = {
  FirstName: "",
  LastName: "",
  EmailAddress: "",
  SafePhoneNumber: "",
  ResourceTypeIDs: [],
  CountyID: "",
  Message: "",
};

function ResourceRequestForm() {
  const [values, setValues] = useState(EMPTY_REQUEST);
  const [status, setStatus] = useState("idle"); // idle | submitting | success | error
  const [message, setMessage] = useState("");
  const [fieldErrors, setFieldErrors] = useState({});
  const [contactError, setContactError] = useState("");

  const update = (name) => (event) =>
    setValues((current) => ({ ...current, [name]: event.target.value }));

  const toggleResource = (id) => (event) =>
    setValues((current) => ({
      ...current,
      ResourceTypeIDs: event.target.checked
        ? [...current.ResourceTypeIDs, id]
        : current.ResourceTypeIDs.filter((selected) => selected !== id),
    }));

  const handleSubmit = async (event) => {
    event.preventDefault();

    if (!values.EmailAddress.trim() && !values.SafePhoneNumber.trim()) {
      setContactError("Please give us either an email address or a phone number so we can reply.");
      document.getElementById("email")?.focus();
      return;
    }

    setContactError("");
    setStatus("submitting");
    setFieldErrors({});

    try {
      const payload = {
        ...values,
        CountyID: values.CountyID ? Number(values.CountyID) : null,
      };

      await submitResourceRequest(payload);
      setValues(EMPTY_REQUEST);
      setStatus("success");
    } catch (error) {
      setFieldErrors(error.fieldErrors ?? {});
      setMessage(
        error.status === 429
          ? "Too many requests just now. Please wait a minute and try again."
          : error.message
      );
      setStatus("error");
    }
  };

  const errorFor = (name) => fieldErrors[name]?.[0];

  if (status === "success") {
    return (
      <FormMessage tone="success">
        Thank you — your request has been received. If you need help before we reach you,
        our crisis line is answered 24 hours a day at (423) 476-3886.
      </FormMessage>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-6" noValidate>
      {status === "error" && <FormMessage tone="error">{message}</FormMessage>}

      <div className="grid md:grid-cols-2 gap-6">
        <Field id="first-name" label="First name" requirement="required" error={errorFor("FirstName")}>
          <input
            id="first-name"
            name="FirstName"
            type="text"
            required
            maxLength={50}
            value={values.FirstName}
            onChange={update("FirstName")}
            aria-describedby={describedBy("first-name", false, errorFor("FirstName"))}
            className={inputClass}
          />
        </Field>

        <Field id="last-name" label="Last name" error={errorFor("LastName")}>
          <input
            id="last-name"
            name="LastName"
            type="text"
            maxLength={50}
            value={values.LastName}
            onChange={update("LastName")}
            aria-describedby={describedBy("last-name", false, errorFor("LastName"))}
            className={inputClass}
          />
        </Field>
      </div>

      <fieldset className="flex flex-col gap-4 border border-gray-200 rounded-lg p-5">
        <legend className="font-semibold text-gray-900 px-2">
          How can we reach you?
          <span className="text-red-700 ml-1" aria-hidden="true">*</span>
          <span className="sr-only">(at least one of the two is required)</span>
        </legend>

        <p id="contact-hint" className="text-sm text-gray-600 -mt-1">
          Choose whichever is safer for you; you do not have to
          provide both.
        </p>

        {contactError && (
          <p role="alert" className="text-sm font-semibold text-red-700">{contactError}</p>
        )}

        <Field id="email" label="Email address" requirement="none" error={errorFor("EmailAddress")}>
          <input
            id="email"
            name="EmailAddress"
            type="email"
            maxLength={250}
            value={values.EmailAddress}
            onChange={update("EmailAddress")}
            aria-describedby={describedBy("email", false, errorFor("EmailAddress"))}
            className={inputClass}
          />
        </Field>

        <Field
          id="phone"
          label="Safe phone number"
          requirement="none"
          error={errorFor("SafePhoneNumber")}
        >
          <input
            id="phone"
            name="SafePhoneNumber"
            type="tel"
            maxLength={20}
            value={values.SafePhoneNumber}
            onChange={update("SafePhoneNumber")}
            aria-describedby={describedBy("phone", true, errorFor("SafePhoneNumber"))}
            className={inputClass}
          />
        </Field>
      </fieldset>

      <fieldset className="flex flex-col gap-3">
        <legend className="font-semibold text-gray-900 mb-1">
          Resources of interest
          <span className="text-gray-500 font-normal ml-2 text-sm">Optional</span>
        </legend>

        <p className="text-sm text-gray-600 -mt-1">Select any that apply.</p>

        {errorFor("ResourceTypeIDs") && (
          <p className="text-sm font-semibold text-red-700">{errorFor("ResourceTypeIDs")}</p>
        )}

        <div className="grid sm:grid-cols-2 gap-2">
          {RESOURCE_TYPES.map((resource) => {
            const checked = values.ResourceTypeIDs.includes(resource.id);
            return (
              <label
                key={resource.id}
                className={`flex items-center gap-3 cursor-pointer border rounded-lg px-4 py-2.5 transition-all
                focus-within:ring-2 focus-within:ring-brand ${
                  checked
                    ? "border-brand bg-purple-50 text-brand font-semibold"
                    : "border-gray-300 text-gray-700 hover:border-brand"
                }`}
              >
                <input
                  type="checkbox"
                  name="ResourceTypeIDs"
                  value={resource.id}
                  checked={checked}
                  onChange={toggleResource(resource.id)}
                  className="w-4 h-4 accent-[#5c0f8b]"
                />
                {resource.Name}
              </label>
            );
          })}
        </div>
      </fieldset>

      <div className="grid md:grid-cols-2 gap-6">
        <Field id="county" label="County" error={errorFor("CountyID")}>
          <select
            id="county"
            name="CountyID"
            value={values.CountyID}
            onChange={update("CountyID")}
            className={inputClass}
          >
            <option value="">Please choose…</option>
            {COUNTIES.map((county) => (
              <option key={county.id} value={county.id}>{county.Name}</option>
            ))}
          </select>
        </Field>
      </div>

      <Field
        id="message"
        label="Anything you would like us to know"
        error={errorFor("Message")}
      >
        <textarea
          id="message"
          name="Message"
          rows={5}
          maxLength={1000}
          value={values.Message}
          onChange={update("Message")}
          aria-describedby={describedBy("message", true, errorFor("Message"))}
          className={inputClass}
        />
      </Field>

      <SubmitButton status={status}>Send request</SubmitButton>
    </form>
  );
}

// ── Service feedback form ─────────────────────────────────────────────────────

const RATINGS = [1, 2, 3, 4, 5];

function ServiceFeedbackForm() {
  const [values, setValues] = useState({ ServiceID: "", Rating: "", Comment: "" });
  const [status, setStatus] = useState("idle");
  const [message, setMessage] = useState("");
  const [fieldErrors, setFieldErrors] = useState({});

  const update = (name) => (event) =>
    setValues((current) => ({ ...current, [name]: event.target.value }));

  const handleSubmit = async (event) => {
    event.preventDefault();
    setStatus("submitting");
    setFieldErrors({});

    try {
      await submitServiceFeedback({
        ServiceID: Number(values.ServiceID),
        Rating: Number(values.Rating),
        Comment: values.Comment,
      });
      setValues({ ServiceID: "", Rating: "", Comment: "" });
      setStatus("success");
    } catch (error) {
      setFieldErrors(error.fieldErrors ?? {});
      setMessage(
        error.status === 429
          ? "Too many submissions just now. Please wait a minute and try again."
          : error.message
      );
      setStatus("error");
    }
  };

  const errorFor = (name) => fieldErrors[name]?.[0];

  if (status === "success") {
    return <FormMessage tone="success">Thank you — your feedback has been received.</FormMessage>;
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-6" noValidate>
      {status === "error" && <FormMessage tone="error">{message}</FormMessage>}

      <Field id="service" label="Which service is this about?" requirement="required" error={errorFor("ServiceID")}>
        <select
          id="service"
          name="ServiceID"
          required
          value={values.ServiceID}
          onChange={update("ServiceID")}
          aria-describedby={describedBy("service", false, errorFor("ServiceID"))}
          className={inputClass}
        >
          <option value="">Please choose…</option>
          {SERVICES.map((service) => (
            <option key={service.id} value={service.id}>{service.Name}</option>
          ))}
        </select>
      </Field>

      <fieldset className="flex flex-col gap-1.5">
        <legend className="font-semibold text-gray-900 mb-1.5">
          How would you rate it?
          <span className="text-red-700 ml-1" aria-hidden="true">*</span>
        </legend>

        <div className="flex items-center gap-2 flex-wrap">
          {RATINGS.map((rating) => {
            const isActive = values.Rating === String(rating);
            return (
              <label
                key={rating}
                className={`cursor-pointer border rounded-lg px-5 py-2.5 font-semibold transition-all
                focus-within:ring-2 focus-within:ring-brand ${
                  isActive
                    ? "bg-brand text-white border-brand"
                    : "border-gray-300 text-gray-700 hover:border-brand hover:text-brand"
                }`}
              >
                <input
                  type="radio"
                  name="Rating"
                  value={rating}
                  checked={isActive}
                  onChange={update("Rating")}
                  onClick={() => {
                    if (isActive) setValues((current) => ({ ...current, Rating: "" }));
                  }}
                  onKeyDown={(event) => {
                    if (isActive && (event.key === " " || event.key === "Enter")) {
                      event.preventDefault();
                      setValues((current) => ({ ...current, Rating: "" }));
                    }
                  }}
                  className="sr-only"
                />
                {rating}
              </label>
            );
          })}
          <span className="text-sm text-gray-500 ml-2">1 = poor, 5 = excellent</span>
        </div>

        {errorFor("Rating") && (
          <p className="text-sm font-semibold text-red-700 mt-1">{errorFor("Rating")}</p>
        )}
      </fieldset>

      <Field id="comment" label="Comments" error={errorFor("Comment")}>
        <textarea
          id="comment"
          name="Comment"
          rows={5}
          maxLength={1000}
          value={values.Comment}
          onChange={update("Comment")}
          aria-describedby={describedBy("comment", false, errorFor("Comment"))}
          className={inputClass}
        />
      </Field>

      <SubmitButton status={status}>Send feedback</SubmitButton>
    </form>
  );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function ContactContent() {
  return (
    <main>

      <section className="bg-purple-950 px-4 pt-20 pb-16 text-center">
        <p className="text-xs font-semibold tracking-widest uppercase text-purple-300 mb-3">
          Harbor Safe House &amp; Advocacy Center
        </p>
        <h1 className="text-5xl md:text-6xl font-semibold text-white mb-4">Contact Us</h1>
        <p className="text-white/80 text-lg max-w-xl mx-auto leading-relaxed">
          Request resources, or tell us how we did.
        </p>
      </section>

      <div className="flex items-center justify-center gap-8 px-8 py-5 bg-purple-50 border-b border-purple-100 flex-wrap">
        <span className="text-sm tracking-widest text-purple-700">24/7 Confidential Crisis Hotline</span>
        <div className="flex items-center gap-5">
          <a href="tel:423-476-3886" className="text-lg font-semibold text-brand hover:underline transition-all">Call (423) 476-3886</a>
          <span className="text-purple-300">|</span>
          <a href="sms:423-715-9614" className="text-lg font-semibold text-brand hover:underline transition-all">Text (423) 715-9614</a>
        </div>
        <span className="text-sm text-purple-700">Free &nbsp;·&nbsp; Confidential &nbsp;·&nbsp; 24 hours a day</span>
      </div>

      <section className="py-16 px-4 bg-white">
        <div className="max-w-3xl mx-auto">
          <h2 className="text-3xl md:text-4xl font-semibold text-gray-900 mb-4 text-center">
            Request Resources
          </h2>

          <p className="text-gray-700 leading-relaxed text-center mb-10">
            Tell us what you need and an advocate will get back to you. If you need help right
            now, please call or text our crisis hotline.
          </p>

          <ResourceRequestForm />
        </div>
      </section>

      <div className="w-full h-1 bg-brand"></div>

      <section className="py-16 px-4 bg-white">
        <div className="max-w-3xl mx-auto">
          <h2 className="text-3xl md:text-4xl font-semibold text-gray-900 mb-4 text-center">
            Share Your Feedback
          </h2>
          <p className="text-gray-700 leading-relaxed text-center mb-10">
            If you have used one of our services, we would like to know how it went. All feedback is
            anonymous.
          </p>

          <ServiceFeedbackForm />
        </div>
      </section>

    </main>
  );
}
