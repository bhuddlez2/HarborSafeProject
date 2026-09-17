"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";

export default function LoginPage() {
  // State and handlers for the login form
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    // Prevent the default form submission behavior
    e.preventDefault();
    setError(null);

    // Basic client-side validation for email and password
    if (!email || !password) {
      setError("Email and password are required.");
      return;
    }

    setLoading(true);
    // Attempt to log in the user by sending a POST request to the login API
    try {
      const res = await fetch("/api/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password }),
      });

      if (!res.ok) {
        const data = await res.json();
        setError(data.error || "Invalid credentials.");
        return;
      }

      // Parse the response data to determine the user's role, admin in username routes to admin dashboard, others to police dashboard
      const data = await res.json();
      if (data.role === "admin") {
        router.push("/admin");
      } else {
        router.push("/police");
      }
    } catch {
      setError("Something went wrong. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <main className="min-h-screen bg-gray-100 flex items-center justify-center p-6">
      <div className="bg-white rounded-2xl shadow-lg w-full max-w-md px-8 py-10 md:px-12 md:py-14">
        <h1 className="text-3xl font-semibold text-gray-900 mb-2">Sign in</h1>
        <p className="text-sm text-gray-500 mb-10">HarborSafe staff portal</p>

        <form onSubmit={handleSubmit} noValidate>
          <div className="mb-6">
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Email
            </label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              autoComplete="email"
              className="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                         focus:outline-none focus:border-gray-900 transition"
            />
          </div>

          <div className="mb-10">
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Password
            </label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="current-password"
              className="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-gray-900
                         focus:outline-none focus:border-gray-900 transition"
            />
          </div>

          {error && (
            <p className="text-sm text-red-600 mb-6">{error}</p>
          )}

          <button
            type="submit"
            disabled={loading}
            className={`w-full bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                       hover:bg-gray-700 focus:outline-none
                       focus:ring-4 focus:ring-gray-400 transition
                       ${loading ? "opacity-50 cursor-not-allowed" : ""}`}
          >
            {loading ? "Signing in..." : "Sign in"}
          </button>
        </form>
      </div>
    </main>
  );
}
