import { Montserrat, Nunito } from "next/font/google";
import Navbar from "./components/Navbar";
import ExitButton from "./components/ExitButton";
import Footer from "./components/Footer";
import "./globals.css";

// font weights: 400 = Regular, 700 = Bold, 800 = Extra Bold
const montserrat = Montserrat({
  variable: "--font-montserrat",
  subsets: ["latin"],
  weight: ["400", "800"], // 400 = Regular, 800 = Extra Bold
});

// font weights: 700 = Bold
const nunito = Nunito({
  variable: "--font-nunito",
  subsets: ["latin"],
  weight: ["700"], // 700 = Bold
});

/*
metadata is exported from a server component (no "use client") so Next.js
can inject the title and description into the HTML head at build time,
this is what controls the browser tab title and search engine description
*/
export const metadata = {
  title: "Domestic Violence Support | Harbor Safe House & Advocacy Center",
  description: "Providing a safe, caring place and high-quality advocacy for survivors of domestic violence and sexual assault in the Cleveland, TN area.",
};

export default function RootLayout({ children }) {
  return (
    <html
      lang="en"
      className={`${montserrat.variable} ${nunito.variable} h-full antialiased light`}
    >
      <body className="min-h-full flex flex-col">
        {/*
        Navbar and ExitButton are client components imported here so layout.js can stay a server component,
        it renders on every page automatically since layout.js wraps all routes
        */}
        <Navbar />
        <ExitButton />
        {/*
        pt-22.5 offsets the page content below the fixed navbar height,
        flex-1 lets the content area grow to fill the remaining viewport height
        */}
        <div className="pt-22.5 flex-1">
          {children}
        </div>
        {/* Footer is a client component imported here and renders on every page automatically */}
        <Footer />
      </body>
    </html>
  );
}
