import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api } from '../lib/api.js';
import { ACCESS_LEVELS } from '../roles.js';

const TOKEN_KEY = 'reclaimer_token';
// Kunci lama dari versi sebelum /auth/me dipakai saat memuat ulang
const LEGACY_KEYS = ['reclaimer_user', 'reclaimer_redirect'];

function readToken() {
  try {
    LEGACY_KEYS.forEach((key) => localStorage.removeItem(key));
    return localStorage.getItem(TOKEN_KEY);
  } catch {
    return null;
  }
}

function writeToken(token) {
  try {
    if (token) localStorage.setItem(TOKEN_KEY, token);
    else localStorage.removeItem(TOKEN_KEY);
  } catch {
    // Penyimpanan diblokir browser: sesi tetap jalan sampai tab ditutup
  }
}

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [token, setToken] = useState(readToken);
  // Profil dan hak akses selalu diambil ulang dari /auth/me, tidak disimpan di browser,
  // supaya perubahan role atau akun nonaktif langsung berlaku.
  const [session, setSession] = useState(null);
  const [status, setStatus] = useState(token ? 'checking' : 'signed-out');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  // Setelah tombol Keluar, halaman terakhir tidak dibawa ke login berikutnya,
  // supaya pengguna lain mendarat di halaman awal role-nya sendiri
  const [signedOutManually, setSignedOutManually] = useState(false);

  const signOutLocally = useCallback((message = '', { manual = false } = {}) => {
    writeToken(null);
    setToken(null);
    setSession(null);
    setNotice(message);
    setSignedOutManually(manual);
    setStatus('signed-out');
  }, []);

  const loadSession = useCallback(
    async (currentToken) => {
      setStatus('checking');
      try {
        const { data } = await api('/auth/me', { token: currentToken });
        setSession(data);
        setStatus('signed-in');
      } catch (err) {
        if (err.status === 401) {
          signOutLocally(err.message);
        } else {
          setError(err.message);
          setStatus('error');
        }
      }
    },
    [signOutLocally],
  );

  useEffect(() => {
    if (token) loadSession(token);
    // Hanya saat aplikasi pertama dibuka; login berikutnya sudah membawa data sesi
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const login = useCallback(async (nik, password) => {
    const { data } = await api('/auth/login', { method: 'POST', body: { nik, password } });
    const { token: newToken, ...sessionData } = data;
    writeToken(newToken);
    setToken(newToken);
    setSession(sessionData);
    setNotice('');
    setStatus('signed-in');
  }, []);

  const logout = useCallback(async () => {
    try {
      await api('/auth/logout', { method: 'POST', token });
    } catch (err) {
      // Token di server mungkin sudah tidak berlaku; sesi lokal tetap dihapus
      console.error('Logout error:', err);
    }
    signOutLocally('', { manual: true });
  }, [token, signOutLocally]);

  // Untuk halaman modul: menyisipkan token dan mengakhiri sesi bila server menjawab 401
  const request = useCallback(
    async (path, options = {}) => {
      try {
        return await api(path, { ...options, token });
      } catch (err) {
        if (err.status === 401) signOutLocally(err.message);
        throw err;
      }
    },
    [token, signOutLocally],
  );

  // Unduh berkas (ekspor laporan). Server mengirim isi berkas dalam JSON (base64) dan
  // berkas disimpan dari blob: pengelola unduhan seperti IDM mencegat respons PDF biasa
  // dan membatalkannya, tetapi tidak mencegat JSON maupun unduhan blob.
  const download = useCallback(
    async (path) => {
      const { data } = await request(path);
      const bytes = Uint8Array.from(atob(data.content), (char) => char.charCodeAt(0));
      const url = URL.createObjectURL(new Blob([bytes], { type: data.content_type }));
      const link = document.createElement('a');
      link.href = url;
      link.download = data.filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      // Beri waktu browser mulai menyimpan sebelum blob dilepas
      setTimeout(() => URL.revokeObjectURL(url), 60_000);
    },
    [request],
  );

  const can = useCallback(
    (feature, level = 'lihat') => {
      const granted = session?.permissions?.[feature];
      return Boolean(granted && ACCESS_LEVELS[granted]?.includes.includes(level));
    },
    [session],
  );

  const value = useMemo(
    () => ({
      status,
      error,
      notice,
      signedOutManually,
      user: session?.user ?? null,
      permissions: session?.permissions ?? {},
      home: session?.home ?? null,
      login,
      logout,
      request,
      download,
      can,
      retry: () => loadSession(token),
    }),
    [status, error, notice, signedOutManually, session, login, logout, request, download, can, loadSession, token],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth harus dipakai di dalam <AuthProvider>.');
  return context;
}
