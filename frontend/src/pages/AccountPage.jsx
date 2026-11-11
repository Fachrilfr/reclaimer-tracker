import { Link } from 'react-router-dom';
import { KeyRound } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { ACCESS_LEVELS, roleLabel } from '../roles.js';
import { MODULES } from '../modules.js';
import PageHeader from '../components/PageHeader.jsx';
import { Table, Td, Th } from '../components/Table.jsx';

export default function AccountPage() {
  const { user, permissions } = useAuth();
  const accessible = MODULES.filter((module) => permissions[module.key]);

  const profile = [
    ['NIK', <span className="font-mono">{user.nik}</span>],
    ['Nama', user.name],
    ['Peran', roleLabel(user.role)],
  ];

  return (
    <>
      <PageHeader title="Akun saya" />

      <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-12">
        <section aria-labelledby="profil-title" className="card self-start p-4 sm:p-5 lg:col-span-4">
          <h2 id="profil-title" className="text-h2 font-semibold">
            Profil
          </h2>
          <dl className="mt-4 divide-y divide-gray-100">
            {profile.map(([label, value]) => (
              <div key={label} className="flex justify-between gap-4 py-3">
                <dt className="text-label font-medium text-gray-600">{label}</dt>
                <dd className="min-w-0 break-words text-right text-body text-gray-900">{value}</dd>
              </div>
            ))}
          </dl>
          {/* Halaman sendiri seperti layar masuk; setelah tersimpan, kembali ke halaman masuk */}
          <Link to="/akun/ganti-password" className="btn-secondary mt-4 h-11 w-full px-4 text-body">
            <KeyRound className="h-4 w-4" aria-hidden="true" />
            Ganti password
          </Link>
        </section>

        <section aria-labelledby="akses-title" className="card self-start lg:col-span-8">
          <div className="p-4 sm:p-5">
            <h2 id="akses-title" className="text-h2 font-semibold">
              Hak akses
            </h2>
          </div>
          <Table caption="Hak akses per modul">
            <thead>
              <tr>
                <Th>Modul</Th>
                <Th>Akses</Th>
                <Th className="hidden sm:table-cell">Artinya</Th>
              </tr>
            </thead>
            <tbody>
              {accessible.map(({ key, label }) => {
                const level = ACCESS_LEVELS[permissions[key]];
                return (
                  <tr key={key}>
                    <Td className="font-medium text-gray-900">{label}</Td>
                    <Td>{level.label}</Td>
                    <Td className="hidden text-gray-600 sm:table-cell">{level.meaning}</Td>
                  </tr>
                );
              })}
            </tbody>
          </Table>
        </section>
      </div>
    </>
  );
}
