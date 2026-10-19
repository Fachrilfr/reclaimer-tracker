import { useState } from 'react';
import LoginPage from './pages/LoginPage.jsx';
import HomePage from './pages/HomePage.jsx';

const STORAGE_KEYS = ['reclaimer_token', 'reclaimer_user', 'reclaimer_redirect'];

function loadSession() {
  try {
    const token = localStorage.getItem('reclaimer_token');
    const user = JSON.parse(localStorage.getItem('reclaimer_user'));
    return token && user ? { token, user } : null;
  } catch {
    return null;
  }
}

function clearSession() {
  STORAGE_KEYS.forEach((key) => localStorage.removeItem(key));
}

async function readJson(response) {
  try {
    return await response.json();
  } catch {
    return null;
  }
}

export default function App() {
  const [session, setSession] = useState(loadSession);

  const handleLogin = async (nik, password) => {
    let response;
    try {
      response = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ nik, password }),
      });
    } catch {
      throw new Error('Server tidak bisa dihubungi. Periksa koneksi jaringan, lalu coba lagi.');
    }

    const data = await readJson(response);

    if (response.status === 429) {
      throw new Error('Terlalu banyak percobaan masuk. Tunggu 1 menit, lalu coba lagi.');
    }
    if (!response.ok || !data) {
      throw new Error(
        data?.message ?? 'Server sedang bermasalah. Coba lagi beberapa saat, atau hubungi Admin bila berulang.',
      );
    }

    const { token, user, redirect } = data.data;
    localStorage.setItem('reclaimer_token', token);
    localStorage.setItem('reclaimer_user', JSON.stringify(user));
    localStorage.setItem('reclaimer_redirect', redirect);
    setSession({ token, user });
  };

  const handleLogout = async () => {
    try {
      await fetch('/api/auth/logout', {
        method: 'POST',
        headers: { Authorization: `Bearer ${session.token}`, Accept: 'application/json' },
      });
    } catch (err) {
      console.error('Logout error:', err);
    }
    clearSession();
    setSession(null);
  };

  return session ? (
    <HomePage user={session.user} onLogout={handleLogout} />
  ) : (
    <LoginPage onLogin={handleLogin} />
  );
}
