import { useState } from 'react';
import { Eye, EyeOff, LoaderCircle } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import AuthShell from '../components/AuthShell.jsx';
import Field from '../components/Field.jsx';
import Alert from '../components/Alert.jsx';
import PageHeader from '../components/PageHeader.jsx';
import { SEEDED_ACCOUNTS, SEEDED_PASSWORD, roleLabel } from '../roles.js';

export default function LoginPage() {
  const { login, notice } = useAuth();
  const [nik, setNik] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [fieldErrors, setFieldErrors] = useState({});
  const [formError, setFormError] = useState('');

  const fillSeededAccount = (seededNik) => {
    setNik(seededNik);
    setPassword(SEEDED_PASSWORD);
    setFieldErrors({});
    setFormError('');
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setFormError('');

    const errors = {};
    if (!nik) errors.nik = 'Isi NIK sesuai kartu karyawan.';
    else if (!/^\d{8}$/.test(nik)) errors.nik = `NIK berupa 8 digit angka, baru ${nik.length} digit.`;
    if (!password) errors.password = 'Isi kata sandi akun Anda.';
    setFieldErrors(errors);
    if (Object.keys(errors).length) return;

    setIsLoading(true);
    try {
      await login(nik, password);
    } catch (err) {
      setFormError(err.message);
      setIsLoading(false);
    }
  };

  return (
    <AuthShell>
      <div className="card p-4 sm:p-8">
        <PageHeader
          title="Masuk"
          description="Catat proses reclaimer, cek material balance, dan pantau masa simpan residu."
        />

        {formError ? (
          <Alert tone="danger" className="mt-6">
            {formError}
          </Alert>
        ) : (
          notice && (
            <Alert tone="info" className="mt-6">
              {notice}
            </Alert>
          )
        )}

        <form onSubmit={handleSubmit} noValidate className="mt-6 space-y-4">
          <Field
            id="nik"
            label="NIK"
            required
            hint="Nomor Induk Karyawan, 8 digit angka."
            error={fieldErrors.nik}
          >
            {(props) => (
              <input
                {...props}
                name="nik"
                type="text"
                inputMode="numeric"
                autoComplete="username"
                maxLength={8}
                spellCheck={false}
                value={nik}
                // Hanya angka; huruf dan tanda baca tidak ikut terketik
                onChange={(e) => setNik(e.target.value.replace(/\D/g, '').slice(0, 8))}
                className="field font-mono"
                autoFocus
              />
            )}
          </Field>

          <Field id="password" label="Kata sandi" required error={fieldErrors.password}>
            {(props) => (
              <div className="relative">
                <input
                  {...props}
                  name="password"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="current-password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="field pr-12"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((v) => !v)}
                  aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                  aria-pressed={showPassword}
                  className="focus-ring absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-gray-600 hover:text-gray-900"
                >
                  {showPassword ? <EyeOff className="h-5 w-5" /> : <Eye className="h-5 w-5" />}
                </button>
              </div>
            )}
          </Field>

          <button type="submit" disabled={isLoading} className="btn-primary h-12 w-full text-body-lg">
            {isLoading ? (
              <>
                <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />
                Memeriksa…
              </>
            ) : (
              'Masuk'
            )}
          </button>
        </form>
      </div>

      {import.meta.env.DEV && (
        <section aria-labelledby="seeded-title" className="mt-4 rounded-card border border-dashed border-gray-400 p-4">
          <h2 id="seeded-title" className="text-label font-semibold">
            Akun uji dari seeder
          </h2>
          <p className="mt-0.5 text-caption text-gray-600">
            Hanya tampil di mode pengembangan. Kata sandi semua akun:{' '}
            <span className="font-mono text-gray-800">{SEEDED_PASSWORD}</span>
          </p>
          <ul className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
            {SEEDED_ACCOUNTS.map((account) => (
              <li key={account.nik}>
                <button
                  type="button"
                  onClick={() => fillSeededAccount(account.nik)}
                  aria-pressed={nik === account.nik}
                  className={`focus-ring flex min-h-11 w-full items-center justify-between gap-2 rounded-lg border px-3 py-2 text-left transition-colors ${
                    nik === account.nik
                      ? 'border-brand-blue-600 bg-brand-blue-50'
                      : 'border-gray-200 bg-white hover:bg-gray-25'
                  }`}
                >
                  <span className="font-mono text-label text-gray-900">{account.nik}</span>
                  <span className="text-caption text-gray-600">{roleLabel(account.role)}</span>
                </button>
              </li>
            ))}
          </ul>
        </section>
      )}
    </AuthShell>
  );
}
