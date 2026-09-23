"use client";

import { useState, useEffect } from "react";
import Link from "next/link";
import { fetchServices, submitServiceFeedback } from "../lib/forms";
import {
  Field,
  inputClass,
  describedBy,
  SubmitButton,
  FormMessage,
  FormSection,
  CrisisHotlineStrip,
} from "../components/FormControls";

const RATINGS = [1, 2, 3, 4, 5];

function ServiceFeedbackForm({ services }) {
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
          {services.map((service) => (
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

export default function FeedbackContent() {
  const [services, setServices] = useState([]);
  const [status, setStatus] = useState("loading"); // loading | ready | error

  useEffect(() => {
    let active = true;

    fetchServices()
      .then((loaded) => {
        if (!active) return;
        setServices(loaded);
        setStatus("ready");
      })
      .catch(() => {
        if (active) setStatus("error");
      });

    return () => {
      active = false;
    };
  }, []);

  return (
    <main>

      <section className="bg-purple-950 px-4 pt-20 pb-16 text-center">
        <p className="text-xs font-semibold tracking-widest uppercase text-purple-300 mb-3">
          Harbor Safe House &amp; Advocacy Center
        </p>
        <h1 className="text-5xl md:text-6xl font-semibold text-white mb-4">Share Your Feedback</h1>
        <p className="text-white/80 text-lg max-w-xl mx-auto leading-relaxed">
          Tell us how one of our services went.
        </p>
      </section>

      <CrisisHotlineStrip />

      <section className="py-16 px-4 bg-white">
        <div className="max-w-3xl mx-auto">
          <p className="text-gray-700 leading-relaxed text-center mb-10">
            If you have used one of our services, we would like to know how it went. All feedback is
            anonymous.
          </p>

          <FormSection status={status}>
            <ServiceFeedbackForm services={services} />
          </FormSection>
        </div>
      </section>

      <section className="pb-16 px-4 bg-white">
        <p className="text-center text-gray-700">
          Looking for help instead?{" "}
          <Link href="/contact" className="text-brand font-semibold hover:underline">
            Request resources
          </Link>
          .
        </p>
      </section>

    </main>
  );
}
