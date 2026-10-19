import { useState } from 'react';
import { Construction, LoaderCircle, LogOut } from 'lucide-react';
import TopBar from '../components/TopBar.jsx';
import { roleInfo } from '../roles.js';

export default function HomePage({ user, onLogout }) {
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const role = roleInfo(user.role);

  const handleLogout = async () => {
    setIsLoggingOut(true);
    await onLogout();
  };

  return (
    <div className="flex min-h-screen flex-col">
      <TopBar>
        <div className="flex items-center gap-4">
          <div className="hidden text-right sm:block">
            <div className="text-label font-semibold text-gray-900">{user.name}</div>
            <div className="text-caption text-gray-600">
              <span className="font-mono">{user.nik}</span> · {role.label}
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

      <main className="mx-auto w-full max-w-content flex-1 px-4 py-8 sm:px-6 lg:px-8">
        <h1 className="text-h1 font-bold">Selamat datang, {user.name}</h1>
        <p className="mt-1 text-body text-gray-600">Anda masuk sebagai {role.label}.</p>

        <div className="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-12">
          <section aria-labelledby="module-title" className="card p-4 sm:p-5 lg:col-span-8">
            <h2 id="module-title" className="text-h2 font-semibold">
              {role.module}
            </h2>
            <div className="flex flex-col items-center px-4 py-12 text-center">
              <Construction className="h-12 w-12 text-gray-400" strokeWidth={1.5} aria-hidden="true" />
              <p className="mt-4 max-w-md text-body-lg text-gray-800">
                Modul ini sedang dibangun dan belum bisa dipakai.
              </p>
              <p className="mt-1 max-w-md text-body text-gray-600">
                Setelah modul selesai, halaman ini langsung membuka {role.module.toLowerCase()} untuk peran{' '}
                {role.label}.
              </p>
            </div>
          </section>

          <section aria-labelledby="account-title" className="card p-4 sm:p-5 lg:col-span-4">
            <h2 id="account-title" className="text-h2 font-semibold">
              Akun
            </h2>
            <dl className="mt-4 divide-y divide-gray-100">
              <div className="flex justify-between gap-4 py-3">
                <dt className="text-label font-medium text-gray-600">NIK</dt>
                <dd className="font-mono text-body text-gray-900">{user.nik}</dd>
              </div>
              <div className="flex justify-between gap-4 py-3">
                <dt className="text-label font-medium text-gray-600">Nama</dt>
                <dd className="text-right text-body text-gray-900">{user.name}</dd>
              </div>
              <div className="flex justify-between gap-4 py-3">
                <dt className="text-label font-medium text-gray-600">Peran</dt>
                <dd className="text-body text-gray-900">{role.label}</dd>
              </div>
              {user.email && (
                <div className="flex justify-between gap-4 py-3">
                  <dt className="text-label font-medium text-gray-600">Email</dt>
                  <dd className="min-w-0 break-all text-right text-body text-gray-900">{user.email}</dd>
                </div>
              )}
            </dl>
          </section>
        </div>
      </main>
    </div>
  );
}
