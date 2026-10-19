import { Construction } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { ACCESS_LEVELS } from '../roles.js';
import PageHeader from '../components/PageHeader.jsx';
import EmptyState from '../components/EmptyState.jsx';

// Dipakai selama modul belum punya halaman (module.page masih null di modules.js)
export default function ModulePlaceholderPage({ module }) {
  const { permissions } = useAuth();
  const level = ACCESS_LEVELS[permissions[module.key]];

  return (
    <>
      <PageHeader title={module.label} description={module.summary} />
      <section className="card mt-6">
        <EmptyState icon={Construction} title="Modul ini belum tersedia.">
          Halaman ini sedang dibangun. Akses Anda di modul ini nanti:{' '}
          <span className="font-semibold text-gray-800">{level.label}</span> ({level.meaning.toLowerCase()}).
        </EmptyState>
      </section>
    </>
  );
}
