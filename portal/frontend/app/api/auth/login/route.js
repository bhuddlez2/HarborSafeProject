import { NextResponse } from "next/server";

export async function POST(request) {
  const { email, password } = await request.json();

  if (!email || !password) {
    return NextResponse.json({ error: "Email and password are required." }, { status: 400 });
  }

  // TODO: replace with real Laravel backend auth call
  // Example: POST to process.env.NEXT_PUBLIC_API_URL + '/api/login'
  // For now, accept any non-empty credentials and return a dev token with a stubbed role
  const role = email.includes("admin") ? "admin" : "law_enforcement";

  const response = NextResponse.json({ ok: true, role });
  response.cookies.set("session", "dev-token", {
    httpOnly: true,
    path: "/",
    sameSite: "lax",
  });
  return response;
}
