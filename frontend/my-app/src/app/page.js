

export default function Home() {
  return (
    <div>
      {/* navigation bar, fixed at the top, positioning, and z-50 ensures it sits above other content */}
      <nav
        className="fixed top-0 left-0 w-full z-50"
        style={{ backgroundColor: '#5c0f8b', height: '70px'}}
      >
        {/* Your logo, links etc. go here */}
      </nav>

      {/* Page content below */}
      <main className="pt-[70px]">
        {/* Your page content goes here */}
      </main>
    </div>
  );
}
