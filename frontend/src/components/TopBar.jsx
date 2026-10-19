import logo from '../assets/logo-indofood-cbp.png';

// Topbar design.md §5: putih, tinggi 64px, garis bawah 2px biru brand, logo kiri 24px (mobile) / 32px (desktop)
export default function TopBar({ children }) {
  return (
    <header className="sticky top-0 z-30 h-16 border-b-2 border-brand-blue-600 bg-white">
      <div className="mx-auto flex h-full max-w-content items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <div className="flex min-w-0 items-center gap-4">
          <img src={logo} alt="Indofood CBP" className="h-6 w-auto shrink-0 sm:h-8" />
          <span aria-hidden="true" className="hidden h-6 w-px bg-gray-200 md:block" />
          <span className="hidden truncate text-label font-semibold text-gray-900 md:block">
            Material Balance &amp; B3 Tracker
          </span>
        </div>
        {children}
      </div>
    </header>
  );
}
