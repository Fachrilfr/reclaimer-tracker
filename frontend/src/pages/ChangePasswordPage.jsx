import { useState } from 'react';
import { Link } from 'react-router-dom';
import { LoaderCircle } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { fieldErrorsFrom } from '../lib/useResource.js';
import AuthShell from '../components/AuthShell.jsx';
import Field from '../components/Field.jsx';
import Alert from '../components/Alert.jsx';
import PageHeader from '../components/PageHeader.jsx';

const MIN_PASSWORD = 8;

/**
 * Ganti password sendiri, semua role, dibuka dari tombol di kartu Profil (Akun saya).
 * Tampil seperti halaman masuk. Setelah tersimpan, semua sesi akun ini keluar dan pengguna
 * kembali ke halaman masuk untuk mencoba password barunya.
 */
export default function ChangePasswordPage() {
  const { user, request, signOut } = useAuth();
  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' });
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [busy, setBusy] = useState(false);
  const set = (key) => (e) => setForm((f) => ({ ...f, [key]: e.target.value }));

  const onSubmit = async (e) => {
    e.preventDefault();
    setFormError('');
    const errs = {};
    if (!form.current_password) errs.current_password = 'Isi password saat ini.';
    if (form.password.length < MIN_PASSWORD) errs.password = `Password baru paling sedikit ${MIN_PASSWORD} karakter.`;
    else if (form.password === form.current_password) errs.password = 'Password baru harus berbeda dari password saat ini.';
    if (!form.password_confirmation) errs.password_confirmation = 'Ulangi password baru.';
    else if (form.password_confirmation !== form.password) errs.password_confirmation = 'Belum sama dengan password baru.';
    setErrors(errs);
    if (Object.keys(errs).length) return;

    setBusy(true);
    try {
      const res = await request('/auth/password', { method: 'POST', body: form });
      // Tanpa halaman asal: setelah masuk lagi, pengguna mendarat di halaman awal role-nya
      signOut(res.message, { manual: true });
    } catch (err) {
      const server = fieldErrorsFrom(err);
      setErrors(server);
      if (!Object.keys(server).length) setFormError(err.message);
      setBusy(false);
    }
  };

  const passwordInput = (key, autoComplete) => (props) => (
    <input {...props} type="password" autoComplete={autoComplete} value={form[key]} onChange={set(key)} className="field" />
  );

  return (
    <AuthShell>
      <div className="card p-4 sm:p-8">
        <PageHeader title="Ganti password" />

        {formError && (
          <Alert tone="danger" className="mt-6">
            {formError}
          </Alert>
        )}

        <form onSubmit={onSubmit} noValidate className="mt-6 space-y-4">
          <Field id="account-nik" label="NIK" hint={user.name}>
            {(props) => (
              <input
                {...props}
                name="nik"
                type="text"
                autoComplete="username"
                value={user.nik}
                readOnly
                className="field bg-gray-25 font-mono text-gray-800"
              />
            )}
          </Field>
          <Field id="current-password" label="Password saat ini" required error={errors.current_password}>
            {passwordInput('current_password', 'current-password')}
          </Field>
          <Field id="new-password" label="Password baru" required hint={`Paling sedikit ${MIN_PASSWORD} karakter.`} error={errors.password}>
            {passwordInput('password', 'new-password')}
          </Field>
          <Field id="confirm-password" label="Ulangi password baru" required error={errors.password_confirmation}>
            {passwordInput('password_confirmation', 'new-password')}
          </Field>

          <button type="submit" disabled={busy} className="btn-primary h-12 w-full text-body-lg">
            {busy && <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />}
            {busy ? 'Menyimpan…' : 'Simpan password baru'}
          </button>
          <Link to="/akun" className="btn-secondary h-11 w-full text-body">
            Batal
          </Link>
        </form>
      </div>
    </AuthShell>
  );
}
