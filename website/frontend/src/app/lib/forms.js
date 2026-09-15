/*
forms.js: the data layer for the two public forms on the Contact page.

The option lists are read from the API's lookup tables rather than hardcoded
here - services/resources/counties are maintained in the database, so adding
a county or renaming a service is a data change, not a frontend deploy.

This site is a static export, so these fetches happen in the browser at
runtime (see ContactContent), not at build time.
*/
const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://127.0.0.1:8000";

async function getJson(path) {
  const response = await fetch(`${API_URL}/api/public/${path}`, {
    headers: { Accept: "application/json" },
  });

  if (!response.ok) {
    throw new Error(`Could not load ${path}`);
  }

  return response.json();
}

async function postJson(path, body) {
  const response = await fetch(`${API_URL}/api/public/${path}`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
  });

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    const error = new Error(data.message || "Something went wrong. Please try again.");
    error.status = response.status;
    error.fieldErrors = data.errors ?? {};
    throw error;
  }

  return data;
}

/*
Loads every option list the Contact page needs in one go. Each entry is
{ id, Name }. Fetched in parallel - one failure rejects the lot, which is
what we want: a form with a half-populated dropdown is worse than a form
that says it couldn't load.
*/
export async function fetchFormOptions() {
  const [services, resourceTypes, counties] = await Promise.all([
    getJson("services"),
    getJson("resources"),
    getJson("counties"),
  ]);

  return { services, resourceTypes, counties };
}

// both endpoints are rate limited to 10 requests a minute and return { message, FormID }
export const submitResourceRequest = (values) => postJson("resource-requests", values);
export const submitServiceFeedback = (values) => postJson("service-feedback", values);
