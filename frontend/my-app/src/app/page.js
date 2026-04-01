"use client";

export default function Home() {
  return (
    <div>
      <button
        className="fixed top-[19px] right-4 z-[100] bg-red-600 hover:bg-white text-white hover:text-red-600 border-2 border-red-600 rounded-full px-6 py-2.5 shadow-lg transition-all hover:scale-110"
        aria-label="Quick exit button"
        title="Quick Exit (exit to google)"
        onClick={() => window.location.href = 'https://www.google.com'}
      >
        Exit
      </button>
      {/* 
      navigation bar,
      bg-[#5C0F8B] for background color,
      h-[90px] for height,
      flex and items-center for alignment, 
      justify-between to space out logo and nav links,
      px-8 for padding,  
      fixed at the top, 
      top left w for positioning, 
      z-50 ensures it sits above other content, 
      */}
      <nav
        className="bg-[#5C0F8B] h-[90px] flex items-center justify-between px-8 fixed top-0 left-0 right-0 z-50"
      >
        {/* HarborSafe logo on the left side of the navbar */}
        <div className="flex items-center gap-4">
          <img
            src="/HSHAC.svg"
            alt="Harbor Safe House and Advocacy Center Logo"
            className="h-20 w-20"
          />
          <span className="text-white text-xl">HSHAC</span>
        </div>

        <div className="flex items-center gap-10 mr-32">
          <a href="#home" className="text-white font-bold group relative">
            <span className="block bg-white text-[#5C0F8B] px-4 py-2 rounded-full transition-all duration-300">
              Home
              </span>
          </a>
          <a href="#about" className="text-white font-bold group relative">
            <span className="block px-4 py-2 rounded-full hover:bg-white hover:text-[#5C0F8B] transition-all duration-300">
              About
              </span>
          </a>
          <a href="#services" className="text-white font-bold group relative">
            <span className="block px-4 py-2 rounded-full hover:bg-white hover:text-[#5C0F8B] transition-all duration-300">
              Get Support
              </span>
          </a>
          <a href="#donate" className="text-white font-bold group relative">
            <span className="block px-4 py-2 rounded-full hover:bg-white hover:text-[#5C0F8B] transition-all duration-300">
              Give Support
              </span>
          </a>
          <a href="#resources" className="text-white font-bold group relative">
            <span className="block px-4 py-2 rounded-full hover:bg-white hover:text-[#5C0F8B] transition-all duration-300">
              Resources
              </span>
          </a>
          <div className="h-8 w-px bg-white"></div>

          <a href="#espanol" className="text-white text-sm hover:underline transition-all">
            En Español
            </a>
        </div>
      </nav>

      {/* Page content below */}
      <main className="pt-[90px]">
        {/* Your page content goes here */}
      </main>
    </div>
  );
}
