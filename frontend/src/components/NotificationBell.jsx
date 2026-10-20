import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Bell } from 'lucide-react';
import { useNotifications } from '../notifications/NotificationContext.jsx';
import { formatDateTime } from '../lib/format.js';

// Lonceng di topbar (design.md §5 dan §8): jumlah belum dibaca, daftar pemberitahuan,
// item belum dibaca berlatar brand-blue-50.
export default function NotificationBell() {
  const { enabled, unread, items, markRead, markAllRead } = useNotifications();
  const [open, setOpen] = useState(false);
  const rootRef = useRef(null);
  const buttonRef = useRef(null);
  const navigate = useNavigate();

  useEffect(() => {
    if (!open) return undefined;
    const onKey = (e) => {
      if (e.key === 'Escape') {
        setOpen(false);
        buttonRef.current?.focus();
      }
    };
    const onClick = (e) => !rootRef.current?.contains(e.target) && setOpen(false);
    document.addEventListener('keydown', onKey);
    document.addEventListener('mousedown', onClick);
    return () => {
      document.removeEventListener('keydown', onKey);
      document.removeEventListener('mousedown', onClick);
    };
  }, [open]);

  if (!enabled) return null;

  const openItem = (item) => {
    if (!item.read) markRead(item.id);
    setOpen(false);
    if (item.url) navigate(item.url);
  };

  return (
    <div ref={rootRef} className="relative">
      <button
        ref={buttonRef}
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        aria-controls="panel-pemberitahuan"
        aria-label={unread ? `Pemberitahuan, ${unread} belum dibaca` : 'Pemberitahuan'}
        className="focus-ring relative flex h-11 w-11 items-center justify-center rounded-lg text-gray-800 hover:bg-gray-25"
      >
        <Bell className="h-5 w-5" aria-hidden="true" />
        {unread > 0 && (
          <span className="absolute right-1 top-1 min-w-[18px] rounded-full bg-danger-fg px-1 text-center text-[11px] font-semibold leading-[18px] text-white">
            {unread > 9 ? '9+' : unread}
          </span>
        )}
      </button>

      {open && (
        <div
          id="panel-pemberitahuan"
          className="fixed inset-x-4 top-16 z-40 rounded-card border border-gray-200 bg-white shadow-pop sm:absolute sm:inset-x-auto sm:right-0 sm:top-12 sm:w-[22rem]"
        >
          <div className="flex items-center justify-between gap-2 border-b border-gray-100 px-4 py-2">
            <h2 className="text-label font-semibold text-gray-900">Pemberitahuan</h2>
            {unread > 0 && (
              <button
                type="button"
                onClick={markAllRead}
                className="focus-ring min-h-11 rounded px-1 text-label font-semibold text-brand-blue-700 hover:underline"
              >
                Tandai semua dibaca
              </button>
            )}
          </div>
          {items.length ? (
            <ul className="max-h-96 overflow-y-auto">
              {items.map((item) => (
                <li key={item.id} className="border-b border-gray-100 last:border-0">
                  <button
                    type="button"
                    onClick={() => openItem(item)}
                    className={`focus-ring block w-full px-4 py-3 text-left hover:bg-gray-25 ${item.read ? '' : 'bg-brand-blue-50'}`}
                  >
                    <span className="flex items-start justify-between gap-2">
                      <span className="text-body font-semibold text-gray-900">{item.title}</span>
                      {!item.read && <span className="sr-only">(belum dibaca)</span>}
                    </span>
                    <span className="mt-0.5 block text-caption text-gray-800">{item.message}</span>
                    <span className="mt-1 block text-caption text-gray-600">{formatDateTime(item.created_at)}</span>
                  </button>
                </li>
              ))}
            </ul>
          ) : (
            <p className="px-4 py-6 text-body text-gray-600">Belum ada pemberitahuan.</p>
          )}
        </div>
      )}
    </div>
  );
}
