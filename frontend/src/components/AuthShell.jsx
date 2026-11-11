import TopBar from './TopBar.jsx';

// Bingkai layar akun di luar aplikasi: halaman masuk dan ganti password memakai tampilan yang sama
export default function AuthShell({ children }) {
  return (
    <div className="flex min-h-screen flex-col">
      <TopBar />

      <main className="flex flex-1 items-start justify-center px-4 py-10 sm:items-center sm:py-16">
        <div className="w-full max-w-[400px]">{children}</div>
      </main>

      <footer className="px-4 py-6 text-center text-caption text-gray-600">
        © 2026 PT Indofood CBP Sukses Makmur Tbk · Packaging Division, Tangerang
      </footer>
    </div>
  );
}
