import { useCallback, useEffect, useRef, useState } from 'react';
import { useAuth } from '../auth/AuthContext.jsx';

/**
 * Ambil data GET dari API untuk sebuah halaman, dengan state memuat dan error.
 * Respons yang datang terlambat (misalnya setelah periode dashboard diganti) diabaikan.
 * `path` null berarti tidak mengambil apa pun (misalnya role tanpa akses isi).
 */
export function useResource(path) {
  const { request } = useAuth();
  const [state, setState] = useState({ data: null, error: '', loading: Boolean(path) });
  const latest = useRef(0);

  const load = useCallback(async () => {
    if (!path) return;
    const id = ++latest.current;
    setState((s) => ({ ...s, loading: true, error: '' }));
    try {
      const response = await request(path);
      if (id === latest.current) setState({ data: response.data, error: '', loading: false });
    } catch (err) {
      if (id === latest.current) setState((s) => ({ data: s.data, error: err.message, loading: false }));
    }
  }, [request, path]);

  useEffect(() => {
    load();
  }, [load]);

  return { ...state, reload: load };
}

/** Pesan validasi Laravel ({ field: [pesan] }) menjadi { field: pesan pertama }. */
export function fieldErrorsFrom(err) {
  if (err?.status !== 422 || !err.data?.errors) return {};
  return Object.fromEntries(Object.entries(err.data.errors).map(([field, messages]) => [field, messages[0]]));
}
