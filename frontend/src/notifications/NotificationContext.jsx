import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { useAuth } from '../auth/AuthContext.jsx';

const POLL_MS = 60_000;

const NotificationContext = createContext(null);

/**
 * Isi lonceng pemberitahuan dan status mesin untuk banner maintenance (FR-21).
 * Diperbarui tiap 60 detik dan saat tab kembali aktif; halaman yang mengubah status
 * mesin memanggil reload() supaya banner langsung berubah.
 */
export function NotificationProvider({ children }) {
  const { request } = useAuth();
  const [state, setState] = useState({ enabled: false, unread: 0, items: [], machine: null, loaded: false });
  const busy = useRef(false);

  const reload = useCallback(async () => {
    if (busy.current) return;
    busy.current = true;
    try {
      const { data } = await request('/notifications');
      setState({ enabled: data.enabled, unread: data.unread_count, items: data.items, machine: data.machine, loaded: true });
    } catch {
      // Gagal sesaat (jaringan): tampilan lama dipertahankan, dicoba lagi di putaran berikutnya
    } finally {
      busy.current = false;
    }
  }, [request]);

  useEffect(() => {
    reload();
    const timer = setInterval(reload, POLL_MS);
    const onVisible = () => document.visibilityState === 'visible' && reload();
    document.addEventListener('visibilitychange', onVisible);
    return () => {
      clearInterval(timer);
      document.removeEventListener('visibilitychange', onVisible);
    };
  }, [reload]);

  const markRead = useCallback(
    async (id) => {
      setState((s) => ({
        ...s,
        unread: Math.max(0, s.unread - (s.items.find((n) => n.id === id && !n.read) ? 1 : 0)),
        items: s.items.map((n) => (n.id === id ? { ...n, read: true } : n)),
      }));
      try {
        await request(`/notifications/${id}/read`, { method: 'POST' });
      } catch {
        reload();
      }
    },
    [request, reload],
  );

  const markAllRead = useCallback(async () => {
    setState((s) => ({ ...s, unread: 0, items: s.items.map((n) => ({ ...n, read: true })) }));
    try {
      await request('/notifications/read-all', { method: 'POST' });
    } catch {
      reload();
    }
  }, [request, reload]);

  const value = useMemo(() => ({ ...state, reload, markRead, markAllRead }), [state, reload, markRead, markAllRead]);

  return <NotificationContext.Provider value={value}>{children}</NotificationContext.Provider>;
}

export function useNotifications() {
  const context = useContext(NotificationContext);
  if (!context) throw new Error('useNotifications harus dipakai di dalam <NotificationProvider>.');
  return context;
}
