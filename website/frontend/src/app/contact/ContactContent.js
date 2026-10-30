"use client";

import { useState, useEffect, useRef } from "react";
import Link from "next/link";
import { fetchRequestOptions, submitResourceRequest } from "../lib/forms";
import { formatPhoneInput, PHONE_INPUT_MAX_LENGTH } from "../lib/validators";
import {
  Field,
  OPTIONAL_PLACEHOLDER,
  inputClass,
  selectClass,
  describedBy,
  SubmitButton,
  RequiredNote,
  FormMessage,
  FormSection,
} from "../components/FormControls";
import CrisisHotlineStrip from "../components/CrisisHotlineStrip";
import PhoneLink from "../components/PhoneLink";
import { CRISIS_CALL } from "../lib/phone";

const EMPTY_REQUEST = {
  FirstName: "",
  LastName: "",
  EmailAddress: "",
  SafePhoneNumber: "",
  ResourceTypeIDs: [],
  CountyID: "",
  Message: "",
};

function ResourceRequestForm({ resourceTypes, counties, preselected }) {
  const [values, setValues] = useState({ ...EMPTY_REQUEST, ResourceTypeIDs: preselected });
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
        our crisis line is answered 24 hours a day at{" "}
        <PhoneLink number={CRISIS_CALL} className="underline" />.
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
            placeholder={OPTIONAL_PLACEHOLDER}
            aria-describedby={describedBy("last-name", false, errorFor("LastName"))}
            className={inputClass}
          />
        </Field>
      </div>

      <fieldset className="flex flex-col gap-4 border border-gray-200 rounded-lg p-5">
        <legend className="font-semibold text-gray-900 px-2">
          How can we reach you?
          <span className="text-red-700 ml-1" aria-hidden="true">*</span>
          <span className="text-sm font-normal text-gray-600 ml-2">At least one of these is required</span>
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
            maxLength={PHONE_INPUT_MAX_LENGTH}
            value={values.SafePhoneNumber}
            onChange={(event) => setValues((current) => ({ ...current, SafePhoneNumber: formatPhoneInput(event.target.value) }))}
            aria-describedby={describedBy("phone", false, errorFor("SafePhoneNumber"))}
            className={inputClass}
          />
        </Field>
      </fieldset>

      <fieldset className="flex flex-col gap-3">
        <legend className="font-semibold text-gray-900 mb-1">
          Resources of interest
        </legend>

        <p className="text-sm text-gray-600 -mt-1">Select any that apply.</p>

        {errorFor("ResourceTypeIDs") && (
          <p className="text-sm font-semibold text-red-700">{errorFor("ResourceTypeIDs")}</p>
        )}

        <div className="grid sm:grid-cols-2 gap-2">
          {resourceTypes.map((resource) => {
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
            className={selectClass(values.CountyID)}
          >
            <option value="">{OPTIONAL_PLACEHOLDER}</option>
            {counties.map((county) => (
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
          placeholder={OPTIONAL_PLACEHOLDER}
          aria-describedby={describedBy("message", false, errorFor("Message"))}
          className={inputClass}
        />
      </Field>

      <RequiredNote className="-mb-3" />
      <SubmitButton status={status}>Send request</SubmitButton>
    </form>
  );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function ContactContent() {
  const [options, setOptions] = useState(null);
  const [status, setStatus] = useState("loading"); // loading | ready | error
  const needsRef = useRef(null);

  useEffect(() => {
    let active = true;

    // Get Support's service cards link here as /contact?need=<resource type name>
    // to tick that box. Matched by name, since staff manage the list; a name
    // that no longer matches just ticks nothing. The address is cleaned at once
    // so browser history never shows what someone asked for. Read once (a ref),
    // since a re-run would find the address already cleaned.
    if (needsRef.current === null) {
      needsRef.current = new URLSearchParams(window.location.search).getAll("need").map((n) => n.toLowerCase());
      if (needsRef.current.length) window.history.replaceState(null, "", window.location.pathname);
    }
    const needs = needsRef.current;

    fetchRequestOptions()
      .then((loaded) => {
        if (!active) return;
        loaded.preselected = loaded.resourceTypes
          .filter((type) => needs.includes(type.Name.toLowerCase()))
          .map((type) => type.id);
        setOptions(loaded);
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

      <section className="bg-purple-950 px-4 pt-12 pb-10 md:pt-16 md:pb-12 text-center">
        <h1 className="text-5xl md:text-6xl font-semibold text-white mb-4">Contact Us</h1>
        <p className="text-white/80 text-lg max-w-xl mx-auto leading-relaxed">
          Tell us what you need and an advocate will get back to you.
        </p>
      </section>

      <CrisisHotlineStrip />

      <section className="pt-12 md:pt-16 pb-16 px-4 bg-white">
        <div className="max-w-3xl mx-auto">
          <p className="text-gray-700 leading-relaxed text-center mb-10">
            If you need help right now, please call or text our crisis hotline — it is answered
            24 hours a day.
          </p>

          <FormSection status={status}>
            <ResourceRequestForm
              resourceTypes={options?.resourceTypes ?? []}
              counties={options?.counties ?? []}
              preselected={options?.preselected ?? []}
            />
          </FormSection>
        </div>
      </section>

      <section className="pb-16 px-4 bg-white">
        <p className="text-center text-gray-600 text-sm">
          Have you used one of our services?{" "}
          <Link href="/feedback" className="text-brand font-semibold hover:underline">
            Share your feedback
          </Link>
          .
        </p>
      </section>

    </main>
  );
}
