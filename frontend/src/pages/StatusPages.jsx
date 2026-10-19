import { Link } from 'react-router-dom';
import { FileQuestion, Lock } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { moduleByKey } from '../modules.js';
import { roleLabel } from '../roles.js';
import PageHeader from '../components/PageHeader.jsx';
import EmptyState from '../components/EmptyState.jsx';

function BackHomeLink() {
  const { home } = useAuth();
  const target = moduleByKey(home);

  return (
    <Link to={target?.path ?? '/akun'} className="btn-primary h-11 px-4 text-body">
      Buka {target?.label ?? 'Akun saya'}
    </Link>
  );
}

export function ForbiddenPage({ module }) {
  const { user } = useAuth();

  return (
    <>
      <PageHeader title={module.label} />
      <section className="card mt-6">
        <EmptyState icon={Lock} title="Anda tidak punya akses ke halaman ini." action={<BackHomeLink />}>
          {module.label} tidak termasuk hak akses peran {roleLabel(user.role)}. Hubungi Admin bila pekerjaan Anda
          memerlukannya.
        </EmptyState>
      </section>
    </>
  );
}

export function NotFoundPage() {
  return (
    <>
      <PageHeader title="Halaman tidak ditemukan" />
      <section className="card mt-6">
        <EmptyState icon={FileQuestion} title="Alamat ini tidak ada di aplikasi." action={<BackHomeLink />}>
          Periksa kembali alamat yang dibuka, atau pilih halaman dari menu.
        </EmptyState>
      </section>
    </>
  );
}
