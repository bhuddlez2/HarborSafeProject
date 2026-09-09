import Link from "next/link";

export const metadata = {
  title: "HarborSafe Portal",
};

export default function HomePage() {
  return (
    <main className="min-h-screen bg-gray-100 flex items-center justify-center p-6">
      <div className="bg-white rounded-2xl shadow-lg w-full max-w-md px-8 py-10 text-center">
        <h1 className="text-3xl font-semibold text-gray-900 mb-8">
          HarborSafe Portal
        </h1>
        <div className="flex flex-col gap-4">
          <Link
            href="/civilian"
            className="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                       hover:bg-gray-700 focus:outline-none
                       focus:ring-4 focus:ring-gray-400 transition"
          >
            Civilian assessment
          </Link>
          <Link
            href="/law-enforcement"
            className="border-2 border-gray-900 text-gray-900 px-8 py-4 rounded-lg text-lg
                       hover:bg-gray-900 hover:text-white
                       focus:outline-none focus:ring-4 focus:ring-gray-400 transition"
          >
            Law enforcement assessment
          </Link>
        </div>
      </div>
    </main>
  );
}
