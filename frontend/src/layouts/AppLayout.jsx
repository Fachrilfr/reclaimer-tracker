import { useCallback, useEffect, useRef, useState } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import { LoaderCircle, LogOut, Menu, X } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { roleLabel } from '../roles.js';
import TopBar from '../components/TopBar.jsx';
import Sidebar from '../components/Sidebar.jsx';

const FOCUSABLE = 'a[href], button:not([disabled])';

// Sidebar sebagai drawer di bawah breakpoint lg (design.md §5)
function NavDrawer({ onClose }) {
  const panelRef = useRef(null);

  useEffect(() => {
    const panel = panelRef.current;
    panel.querySelector(FOCUSABLE)?.focus();

    const handleKeyDown = (event) => {
      if (event.key === 'Escape') {
        onClose();
        return;
      }
      if (event.key !== 'Tab') return;

      // Fokus tetap di dalam drawer selama terbuka
      const items = [...panel.querySelectorAll(FOCUSABLE)];
      const first = items[0];
      const last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = previousOverflow;
    };
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-40 lg:hidden">
      <div className="absolute inset-0 bg-gray-900/40" onClick={onClose} aria-hidden="true" />
      <div
        ref={panelRef}
        id="menu-navigasi"
        role="dialog"
        aria-modal="true"
        aria-label="Menu navigasi"
        className="absolute inset-y-0 left-0 flex w-72 max-w-[85vw] flex-col bg-white shadow-pop"
      >
        <div className="flex h-16 shrink-0 items-center justify-between border-b border-gray-200 px-4">
          <span className="text-label font-semibold text-gray-900">Menu</span>
          <button
            type="button"
            onClick={onClose}
            aria-label="Tutup menu"
            className="focus-ring -mr-2 flex h-11 w-11 items-center justify-center rounded-lg text-gray-800 hover:bg-gray-25"
          >
            <X className="h-5 w-5" aria-hidden="true" />
          </button>
        </div>
        <Sidebar />
      </div>
    </div>
  );
}

export default function AppLayout() {
  const { user, logout } = useAuth();
  const location = useLocation();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const menuButtonRef = useRef(null);
  const mainRef = useRef(null);
  const isFirstRender = useRef(true);

  // Ditutup tanpa pindah halaman (Escape, tombol tutup, area gelap): fokus kembali ke tombol menu.
  // Fokus dipindah sebelum drawer dilepas, karena setelah itu elemen di dalamnya sudah hilang.
  const closeDrawer = useCallback(() => {
    menuButtonRef.current?.focus();
    setDrawerOpen(false);
  }, []);

  // Pindah halaman: tutup drawer dan pindahkan fokus ke konten agar pembaca layar mulai dari halaman baru
  useEffect(() => {
    setDrawerOpen(false);
    if (isFirstRender.current) {
      isFirstRender.current = false;
      return;
    }
    mainRef.current?.focus();
  }, [location.pathname]);

  const handleLogout = async () => {
    setIsLoggingOut(true);
    await logout();
  };

  return (
    <div className="min-h-screen">
      <a
        href="#konten"
        className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-3 focus:text-body focus:font-semibold focus:text-brand-blue-700 focus:shadow-pop"
      >
        Lewati ke konten
      </a>

      <TopBar
        start={
          <button
            ref={menuButtonRef}
            type="button"
            onClick={() => setDrawerOpen(true)}
            aria-label="Buka menu"
            aria-expanded={drawerOpen}
            aria-controls="menu-navigasi"
            className="focus-ring -ml-2 flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-gray-800 hover:bg-gray-25 lg:hidden"
          >
            <Menu className="h-6 w-6" aria-hidden="true" />
          </button>
        }
      >
        <div className="flex items-center gap-4">
          <div className="hidden text-right sm:block">
            <div className="text-label font-semibold text-gray-900">{user.name}</div>
            <div className="text-caption text-gray-600">
              <span className="font-mono">{user.nik}</span> · {roleLabel(user.role)}
            </div>
          </div>
          <button
            type="button"
            onClick={handleLogout}
            disabled={isLoggingOut}
            className="btn-secondary h-11 px-4 text-body"
          >
            {isLoggingOut ? (
              <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />
            ) : (
              <LogOut className="h-5 w-5" aria-hidden="true" />
            )}
            Keluar
          </button>
        </div>
      </TopBar>

      <div className="flex">
        <aside className="sticky top-16 hidden h-[calc(100vh-4rem)] w-64 shrink-0 flex-col border-r border-gray-200 bg-white lg:flex">
          <Sidebar />
        </aside>

        <main
          id="konten"
          ref={mainRef}
          tabIndex={-1}
          className="min-w-0 flex-1 px-4 py-8 focus:outline-none sm:px-6 lg:px-8"
        >
          <div className="mx-auto max-w-content">
            <Outlet />
          </div>
        </main>
      </div>

      {drawerOpen && <NavDrawer onClose={closeDrawer} />}
    </div>
  );
}
