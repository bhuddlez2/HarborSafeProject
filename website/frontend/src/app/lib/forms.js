/*
forms.js: the data layer for the two public forms.

The option lists come from the API's lookup tables rather than being hardcoded. 
The site is a static export, so these fetches happen in the browser at runtime.
*/
const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://127.0.0.1:8000";

/*
TEMPORARY: delete before the site goes live.

Placeholder options so the forms still render while the API is not running,
which is what lets the pages be reviewed on the dev branch. Removing this is
two deletions in this file: this constant, and the try/catch in getJson below.
*/
const IS_LOCAL_PREVIEW =
  typeof window !== "undefined" &&
  ["localhost", "127.0.0.1"].includes(window.location.hostname);

const FALLBACK_OPTIONS = {
  services: [
    { id: 1, Name: "Emergency Shelter" },
    { id: 2, Name: "Crisis Counseling" },
    { id: 3, Name: "Support Groups" },
    { id: 4, Name: "Court Advocacy" },
    { id: 5, Name: "Community Education" },
    { id: 6, Name: "24/7 Crisis Line" },
  ],
  resources: [
    { id: 1, Name: "Emergency Shelter" },
    { id: 2, Name: "Safety Planning" },
    { id: 3, Name: "Orders of Protection" },
    { id: 4, Name: "Counseling" },
    { id: 5, Name: "Legal Help" },
    { id: 6, Name: "Transportation" },
    { id: 7, Name: "Clothing" },
    { id: 8, Name: "Something Else" },
  ],
  counties: [
    { id: 1, Name: "Bradley" },
    { id: 2, Name: "Polk" },
  ],
};

async function getJson(path) {
  try {
    const response = await fetch(`${API_URL}/api/public/${path}`, {
      headers: { Accept: "application/json" },
    });

    if (!response.ok) {
      throw new Error(`Could not load ${path}`);
    }

    return await response.json();
  } catch (error) {
    // TEMPORARY: anywhere but a local preview, fail properly
    if (!IS_LOCAL_PREVIEW) throw error;

    // TEMPORARY: see FALLBACK_OPTIONS above
    console.warn(`[forms] "${path}" unavailable; showing placeholder options.`);
    return FALLBACK_OPTIONS[path];
  }
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

export async function fetchRequestOptions() {
  const [resourceTypes, counties] = await Promise.all([
    getJson("resources"),
    getJson("counties"),
  ]);

  return { resourceTypes, counties };
}

export async function fetchServices() {
  return getJson("services");
}

// both endpoints are rate limited to 10 requests a minute and return { message, FormID }
export const submitResourceRequest = (values) => postJson("resource-requests", values);
export const submitServiceFeedback = (values) => postJson("service-feedback", values);
