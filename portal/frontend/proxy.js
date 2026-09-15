
import { NextResponse } from "next/server";

// Proxy middleware to check for session cookie and redirect to login if not present
export function proxy(request) {
  const session = request.cookies.get("session");
  if (!session) {
    return NextResponse.redirect(new URL("/login", request.url));
  }
  return NextResponse.next();
}

// Configuration for the proxy middleware, specifying which routes it should apply to
export const config = {
  matcher: ["/police/:path*", "/admin/:path*"],
};
