"use client";

import Link from "next/link";

export default function PoliceLandingPage() {
  const handleLogout = async () => {
    await fetch("/api/auth/logout", { method: "POST" });
    window.location.href = "/login";
  };

  return (
    <main className="min-h-screen bg-gray-100 flex items-start md:items-center justify-center p-6">
      <div className="bg-white rounded-2xl shadow-lg w-full max-w-2xl px-8 py-10 md:px-12 md:py-14">
        <h1 className="text-3xl font-semibold text-gray-900 mb-2">
          Law enforcement portal
        </h1>
        <p className="text-sm text-gray-500 mb-10">
          Select an action below.
        </p>

        <div className="flex flex-col gap-4">
          <Link
            href="/police/portal"
            className="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg text-center
                       hover:bg-gray-700 focus:outline-none
                       focus:ring-4 focus:ring-gray-400 transition"
          >
            Start new assessment
          </Link>

          <button
            onClick={handleLogout}
            className="border-2 border-gray-900 text-gray-900 px-8 py-4 rounded-lg text-lg
                       hover:bg-gray-900 hover:text-white
                       focus:outline-none focus:ring-4 focus:ring-gray-400 transition"
          >
            Sign out
          </button>
        </div>
      </div>
    </main>
  );
}
