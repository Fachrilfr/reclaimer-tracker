import React, { useState, useEffect } from 'react';
import { 
  ShieldCheck, 
  UserCheck, 
  Lock, 
  Eye, 
  EyeOff, 
  Building2, 
  Cpu, 
  AlertTriangle, 
  ArrowRight, 
  LogOut, 
  CheckCircle2, 
  Database,
  Clock,
  Boxes,
  Activity,
  Layers,
  FileText
} from 'lucide-react';

const DEMO_ACCOUNTS = [
  { role: 'operator', name: 'Operator Aziz', nik: 'OP-1001', target: 'Form Input Batch Reclaimer', color: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' },
  { role: 'supervisor', name: 'Supervisor Reclaimer', nik: 'SPV-2001', target: 'Dashboard Alert & Toleransi', color: 'bg-blue-500/10 text-blue-400 border-blue-500/30' },
  { role: 'hse', name: 'HSE Officer', nik: 'HSE-3001', target: 'Monitoring B3 & Timer Masa Simpan', color: 'bg-amber-500/10 text-amber-400 border-amber-500/30' },
  { role: 'management', name: 'Manajemen Divisi', nik: 'MGT-4001', target: 'Dashboard Eksekutif & Laporan', color: 'bg-purple-500/10 text-purple-400 border-purple-500/30' },
  { role: 'admin', name: 'Admin Sistem', nik: 'ADM-5001', target: 'Kelola Master Data & Parameter', color: 'bg-rose-500/10 text-rose-400 border-rose-500/30' },
];

export default function App() {
  const [nik, setNik] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const [user, setUser] = useState(null);
  const [token, setToken] = useState(null);
  const [redirectPath, setRedirectPath] = useState('');

  // Periksa sesi yang tersimpan di localStorage
  useEffect(() => {
    const savedToken = localStorage.getItem('reclaimer_token');
    const savedUser = localStorage.getItem('reclaimer_user');
    const savedRedirect = localStorage.getItem('reclaimer_redirect');

    if (savedToken && savedUser) {
      setToken(savedToken);
      setUser(JSON.parse(savedUser));
      setRedirectPath(savedRedirect || '/dashboard');
    }
  }, []);

  const handleFillDemo = (demoNik) => {
    setNik(demoNik);
    setPassword('password123');
    setErrorMessage('');
  };

  const handleLogin = async (e) => {
    e.preventDefault();
    setErrorMessage('');

    if (!nik.trim()) {
      setErrorMessage('Nomor Induk Karyawan (NIK) wajib diisi.');
      return;
    }
    if (!password) {
      setErrorMessage('Kata sandi wajib diisi.');
      return;
    }

    setIsLoading(true);

    try {
      const response = await fetch('/api/auth/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          nik: nik.trim().toUpperCase(),
          password: password,
        }),
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Gagal masuk ke sistem. Silakan periksa NIK dan kata sandi.');
      }

      // Berhasil Login
      const authToken = data.data.token;
      const userData = data.data.user;
      const destination = data.data.redirect;

      setToken(authToken);
      setUser(userData);
      setRedirectPath(destination);

      localStorage.setItem('reclaimer_token', authToken);
      localStorage.setItem('reclaimer_user', JSON.stringify(userData));
      localStorage.setItem('reclaimer_redirect', destination);
    } catch (err) {
      setErrorMessage(err.message);
    } finally {
      setIsLoading(false);
    }
  };

  const handleLogout = async () => {
    if (token) {
      try {
        await fetch('/api/auth/logout', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
          },
        });
      } catch (err) {
        console.error('Logout error:', err);
      }
    }

    localStorage.removeItem('reclaimer_token');
    localStorage.removeItem('reclaimer_user');
    localStorage.removeItem('reclaimer_redirect');

    setToken(null);
    setUser(null);
    setNik('');
    setPassword('');
    setErrorMessage('');
  };

  // Tampilan Sesudah Login (Dashboard / Session Active)
  if (user) {
    const roleInfo = DEMO_ACCOUNTS.find(a => a.role === user.role) || {
      name: user.role.toUpperCase(),
      color: 'bg-blue-500/10 text-blue-400 border-blue-500/30',
      target: 'Dashboard Utama'
    };

    return (
      <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
        {/* Top Navbar */}
        <header className="border-b border-slate-800 bg-slate-900/60 backdrop-blur sticky top-0 z-40 px-6 py-3.5 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center font-bold text-white shadow-lg shadow-blue-500/20">
              <Cpu className="w-5 h-5" />
            </div>
            <div>
              <div className="text-xs uppercase tracking-wider font-semibold text-blue-400">
                PT Indofood CBP Sukses Makmur Tbk
              </div>
              <h1 className="text-base font-bold text-slate-100">
                Material Balance & B3 Tracker
              </h1>
            </div>
          </div>

          <div className="flex items-center gap-4">
            <div className="flex items-center gap-2.5 px-3 py-1.5 rounded-lg bg-slate-800/80 border border-slate-700">
              <div className="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></div>
              <div className="text-xs">
                <span className="font-semibold text-slate-200">{user.name}</span>
                <span className="text-slate-400 ml-1.5">({user.nik})</span>
              </div>
              <span className={`text-[10px] uppercase font-bold px-2 py-0.5 rounded border ml-1 ${roleInfo.color}`}>
                {user.role}
              </span>
            </div>

            <button
              onClick={handleLogout}
              className="flex items-center gap-2 text-xs font-semibold px-3 py-1.5 rounded-lg bg-red-500/10 text-red-400 border border-red-500/20 hover:bg-red-500/20 transition-all cursor-pointer"
            >
              <LogOut className="w-3.5 h-3.5" />
              Keluar
            </button>
          </div>
        </header>

        {/* Content Area */}
        <main className="flex-1 max-w-6xl w-full mx-auto px-6 py-8">
          {/* Welcome Banner */}
          <div className="p-6 rounded-2xl bg-gradient-to-r from-blue-900/40 via-indigo-900/20 to-slate-900 border border-blue-500/20 shadow-xl mb-8 relative overflow-hidden">
            <div className="absolute right-0 top-0 translate-x-8 -translate-y-8 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>
            
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div>
                <div className="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium mb-3">
                  <CheckCircle2 className="w-3.5 h-3.5" />
                  Autentikasi Berhasil • Session Token Aktif
                </div>
                <h2 className="text-2xl font-bold text-white mb-1">
                  Selamat Datang, {user.name}
                </h2>
                <p className="text-sm text-slate-300">
                  Anda login sebagai <span className="font-semibold text-blue-300 uppercase">{user.role}</span> dengan NIK <code className="bg-slate-800 px-1.5 py-0.5 rounded text-amber-300">{user.nik}</code>.
                </p>
              </div>

              <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <div className="px-4 py-2 rounded-xl bg-slate-800/80 border border-slate-700 text-xs flex items-center gap-2">
                  <Database className="w-4 h-4 text-emerald-400" />
                  <div>
                    <div className="text-slate-400 text-[10px]">Database MAMP</div>
                    <div className="font-semibold text-slate-200">MySQL 127.0.0.1:8889</div>
                  </div>
                </div>

                <div className="px-4 py-2 rounded-xl bg-blue-600/20 border border-blue-500/30 text-xs flex items-center gap-2">
                  <ArrowRight className="w-4 h-4 text-blue-400" />
                  <div>
                    <div className="text-blue-300 text-[10px]">Rute Default Role</div>
                    <div className="font-semibold text-white">{redirectPath}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Quick Metrics from Database Seeder */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div className="p-5 rounded-xl bg-slate-900/60 border border-slate-800/80 hover:border-slate-700 transition-all">
              <div className="flex items-center justify-between text-slate-400 text-xs mb-2">
                <span>Target Recovery Minimum</span>
                <Activity className="w-4 h-4 text-blue-400" />
              </div>
              <div className="text-2xl font-bold text-white mb-1">35.0%</div>
              <div className="text-[11px] text-slate-400">Parameter PRD Draf Baru</div>
            </div>

            <div className="p-5 rounded-xl bg-slate-900/60 border border-slate-800/80 hover:border-slate-700 transition-all">
              <div className="flex items-center justify-between text-slate-400 text-xs mb-2">
                <span>Saldo Solvent Terkini</span>
                <Layers className="w-4 h-4 text-emerald-400" />
              </div>
              <div className="text-2xl font-bold text-emerald-400 mb-1">1,000 kg</div>
              <div className="text-[11px] text-slate-400">Buku Besar Mutasi Aktif</div>
            </div>

            <div className="p-5 rounded-xl bg-slate-900/60 border border-slate-800/80 hover:border-slate-700 transition-all">
              <div className="flex items-center justify-between text-slate-400 text-xs mb-2">
                <span>Batas Simpan Limbah B3</span>
                <Clock className="w-4 h-4 text-amber-400" />
              </div>
              <div className="text-2xl font-bold text-amber-300 mb-1">90 Hari</div>
              <div className="text-[11px] text-slate-400">1 Pallet = 3 Drum Residu</div>
            </div>

            <div className="p-5 rounded-xl bg-slate-900/60 border border-slate-800/80 hover:border-slate-700 transition-all">
              <div className="flex items-center justify-between text-slate-400 text-xs mb-2">
                <span>Status Mesin Reclaimer</span>
                <Cpu className="w-4 h-4 text-indigo-400" />
              </div>
              <div className="text-2xl font-bold text-white mb-1">IRAC-01</div>
              <div className="text-[11px] text-emerald-400 font-medium flex items-center gap-1">
                <span className="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Operating (Siap Batch)
              </div>
            </div>
          </div>

          {/* Module Next Steps Card */}
          <div className="p-6 rounded-2xl bg-slate-900/70 border border-slate-800">
            <h3 className="text-lg font-bold text-white mb-2 flex items-center gap-2">
              <FileText className="w-5 h-5 text-blue-400" />
              Modul Berikutnya: Siap Diimplementasikan
            </h3>
            <p className="text-sm text-slate-400 mb-6">
              Fitur autentikasi telah selesai. Berikut adalah modul yang siap kita bangun selanjutnya sesuai alur PRD:
            </p>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="p-4 rounded-xl bg-slate-950/60 border border-slate-800 hover:border-blue-500/50 transition-all group">
                <div className="text-xs font-bold text-blue-400 mb-1">Langkah 2 (Fase 1)</div>
                <h4 className="font-semibold text-slate-100 group-hover:text-blue-300 transition-colors">
                  Form Input Batch Reclaimer
                </h4>
                <p className="text-xs text-slate-400 mt-1">
                  Input Tinta X, solvent, suhu oli & uap, serta kalkulasi otomatis selisih dan recovery rate.
                </p>
              </div>

              <div className="p-4 rounded-xl bg-slate-950/60 border border-slate-800 hover:border-emerald-500/50 transition-all group">
                <div className="text-xs font-bold text-emerald-400 mb-1">Langkah 3 (Fase 1)</div>
                <h4 className="font-semibold text-slate-100 group-hover:text-emerald-300 transition-colors">
                  Dashboard & Alert Toleransi
                </h4>
                <p className="text-xs text-slate-400 mt-1">
                  Pantau 4 angka utama, perbandingan antar shift, dan alert jika recovery di bawah 35%.
                </p>
              </div>

              <div className="p-4 rounded-xl bg-slate-950/60 border border-slate-800 hover:border-amber-500/50 transition-all group">
                <div className="text-xs font-bold text-amber-400 mb-1">Langkah 4 (Fase 2)</div>
                <h4 className="font-semibold text-slate-100 group-hover:text-amber-300 transition-colors">
                  Pelacakan B3 & Surat Jalan
                </h4>
                <p className="text-xs text-slate-400 mt-1">
                  Pencatatan pallet 3 drum, hitung mundur masa simpan eksak, dan unggah surat jalan limbah.
                </p>
              </div>
            </div>
          </div>
        </main>
      </div>
    );
  }

  // Tampilan Halaman Login
  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-between relative overflow-hidden">
      {/* Decorative background glows */}
      <div className="absolute -top-32 -left-32 w-96 h-96 bg-blue-600/15 rounded-full blur-3xl pointer-events-none"></div>
      <div className="absolute -bottom-32 -right-32 w-96 h-96 bg-indigo-600/15 rounded-full blur-3xl pointer-events-none"></div>

      {/* Top Banner Header */}
      <header className="px-6 py-4 flex items-center justify-between border-b border-slate-800/80 bg-slate-900/30 backdrop-blur z-10">
        <div className="flex items-center gap-3">
          <div className="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center font-bold text-white shadow-md shadow-blue-500/20">
            <Cpu className="w-5 h-5" />
          </div>
          <div>
            <div className="text-[11px] uppercase tracking-wider font-semibold text-blue-400">
              PT INDOFOOD CBP SUKSES MAKMUR Tbk
            </div>
            <div className="text-xs font-medium text-slate-300">
              PACKAGING DIVISION — TANGERANG
            </div>
          </div>
        </div>

        <div className="hidden sm:flex items-center gap-2 text-xs text-slate-400 bg-slate-900/60 px-3 py-1.5 rounded-full border border-slate-800">
          <ShieldCheck className="w-4 h-4 text-emerald-400" />
          <span>Standar Kepatuhan ISO 14001</span>
        </div>
      </header>

      {/* Main Container */}
      <main className="flex-1 flex items-center justify-center px-4 py-8 z-10">
        <div className="max-w-md w-full">
          {/* Card Login */}
          <div className="p-8 rounded-2xl glass-card shadow-2xl border border-slate-800/90 relative">
            {/* Header Form */}
            <div className="text-center mb-6">
              <div className="inline-flex p-3 rounded-2xl bg-blue-600/10 border border-blue-500/20 text-blue-400 mb-3">
                <UserCheck className="w-6 h-6" />
              </div>
              <h2 className="text-xl font-bold text-white">
                Masuk ke Sistem
              </h2>
              <p className="text-xs text-slate-400 mt-1">
                Material Balance & B3 Tracker Reclaimer
              </p>
            </div>

            {/* Error Message */}
            {errorMessage && (
              <div className="mb-5 p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 text-xs flex items-start gap-2.5 animate-shake">
                <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" />
                <span>{errorMessage}</span>
              </div>
            )}

            {/* Form */}
            <form onSubmit={handleLogin} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-1.5">
                  Nomor Induk Karyawan (NIK)
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                    <Building2 className="w-4 h-4" />
                  </div>
                  <input
                    type="text"
                    value={nik}
                    onChange={(e) => setNik(e.target.value.toUpperCase())}
                    placeholder="Contoh: OP-1001"
                    className="w-full pl-10 pr-4 py-2.5 bg-slate-900/90 border border-slate-700 rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors uppercase font-mono tracking-wider"
                    autoFocus
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-1.5">
                  Kata Sandi
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                    <Lock className="w-4 h-4" />
                  </div>
                  <input
                    type={showPassword ? 'text' : 'password'}
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="Masukkan kata sandi"
                    className="w-full pl-10 pr-10 py-2.5 bg-slate-900/90 border border-slate-700 rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-500 hover:text-slate-300 transition-colors cursor-pointer"
                  >
                    {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                  </button>
                </div>
              </div>

              <button
                type="submit"
                disabled={isLoading}
                className="w-full mt-2 py-3 px-4 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold text-sm shadow-lg shadow-blue-600/30 active:scale-[0.99] transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {isLoading ? (
                  <>
                    <div className="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></div>
                    <span>Memverifikasi...</span>
                  </>
                ) : (
                  <>
                    <span>Masuk ke Sistem</span>
                    <ArrowRight className="w-4 h-4" />
                  </>
                )}
              </button>
            </form>

            {/* Quick Demo Role Picker */}
            <div className="mt-6 pt-5 border-t border-slate-800">
              <div className="text-[11px] font-semibold text-slate-400 mb-2.5 flex items-center justify-between">
                <span>Pilih Akun Demo (1-Klik Isi):</span>
                <span className="text-[10px] text-slate-500">PW: password123</span>
              </div>
              <div className="grid grid-cols-2 gap-2">
                {DEMO_ACCOUNTS.map((account) => (
                  <button
                    key={account.nik}
                    type="button"
                    onClick={() => handleFillDemo(account.nik)}
                    className={`p-2 rounded-lg border text-left transition-all hover:scale-[1.02] cursor-pointer ${
                      nik === account.nik 
                        ? 'bg-blue-600/20 border-blue-500 text-white' 
                        : 'bg-slate-900/60 border-slate-800 hover:border-slate-700 text-slate-300'
                    }`}
                  >
                    <div className="text-xs font-semibold truncate">{account.name}</div>
                    <div className="text-[10px] text-slate-400 font-mono flex items-center justify-between mt-0.5">
                      <span>{account.nik}</span>
                      <span className="uppercase text-[9px] text-blue-400">{account.role}</span>
                    </div>
                  </button>
                ))}
              </div>
            </div>
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="px-6 py-4 border-t border-slate-900 text-center text-xs text-slate-500 z-10">
        &copy; 2026 PT Indofood CBP Sukses Makmur Tbk — Packaging Division. All rights reserved.
      </footer>
    </div>
  );
}
