import { NextResponse } from "next/server";

// Handles user logout by clearing the session cookie and redirecting to the login page
// TODO: Set `NEXT_PUBLIC_APP_URL` in `.env.local` for production
export async function GET() {
  const response = NextResponse.redirect(new URL("/login", process.env.NEXT_PUBLIC_APP_URL || "http://localhost:3000"));
  response.cookies.set("session", "", {
    httpOnly: true,
    path: "/",
    sameSite: "lax",
    maxAge: 0,
  });
  return response;
}

export async function POST() {
  return GET();
}
