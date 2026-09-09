/*
forms.js: the data layer for the two public forms on the Contact page.
*/
const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://127.0.0.1:8000";

export const SERVICES = [
  { id: 1, Name: "Emergency Shelter" },
  { id: 2, Name: "Crisis Counseling" },
  { id: 3, Name: "Support Groups" },
  { id: 4, Name: "Court Advocacy" },
  { id: 5, Name: "Community Education" },
  { id: 6, Name: "24/7 Crisis Line" },
];

export const RESOURCE_TYPES = [
  { id: 1, Name: "Emergency Shelter" },
  { id: 2, Name: "Safety Planning" },
  { id: 3, Name: "Orders of Protection" },
  { id: 4, Name: "Counseling" },
  { id: 5, Name: "Legal Help" },
  { id: 6, Name: "Transportation" },
  { id: 7, Name: "Clothing" },
  { id: 8, Name: "Something Else" },
];

export const COUNTIES = [
  { id: 1, Name: "Bradley" },
  { id: 2, Name: "Polk" },
];

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

// both endpoints are rate limited to 10 requests a minute and return { message, FormID }
export const submitResourceRequest = (values) => postJson("resource-requests", values);
export const submitServiceFeedback = (values) => postJson("service-feedback", values);
