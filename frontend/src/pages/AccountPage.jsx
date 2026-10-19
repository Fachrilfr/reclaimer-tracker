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
    ['Email', user.email ?? 'Belum diisi'],
  ];

  return (
    <>
      <PageHeader title="Akun saya" description="Profil dan hak akses Anda. Perubahan data akun dilakukan oleh Admin." />

      <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-12">
        <section aria-labelledby="profil-title" className="card p-4 sm:p-5 lg:col-span-4">
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
        </section>

        <section aria-labelledby="akses-title" className="card lg:col-span-8">
          <div className="p-4 sm:p-5">
            <h2 id="akses-title" className="text-h2 font-semibold">
              Hak akses
            </h2>
            <p className="mt-1 text-body text-gray-600">
              Modul yang bisa Anda buka sebagai {roleLabel(user.role)}, sesuai matriks akses PRD.
            </p>
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
