import logo from '../assets/logo-indofood-cbp.png';

// Topbar design.md §5: putih, tinggi 64px, garis bawah 2px biru brand, logo kiri 24px (mobile) / 32px (desktop).
// `start` untuk tombol menu di layar kecil, `children` untuk isi sisi kanan.
export default function TopBar({ start, children }) {
  return (
    <header className="sticky top-0 z-30 h-16 border-b-2 border-brand-blue-600 bg-white">
      <div className="flex h-full items-center justify-between gap-4 px-4 sm:px-6">
        <div className="flex min-w-0 items-center gap-3 sm:gap-4">
          {start}
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
