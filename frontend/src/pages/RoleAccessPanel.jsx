import { useEffect, useState } from 'react';
import { LoaderCircle } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { useResource } from '../lib/useResource.js';
import { MODULE_GROUPS } from '../modules.js';
import { ROLE_LABELS } from '../roles.js';
import Alert from '../components/Alert.jsx';
import { ErrorCard, LoadingCard } from '../components/LoadState.jsx';

// Arti tiap tingkat, ditulis dari sisi pengguna role itu
const LEVEL_TEXT = {
  lihat: 'Lihat saja',
  isi: 'Lihat dan input data',
  tinjau: 'Lihat dan tinjau',
  kelola: 'Lihat dan kelola (tambah, ubah, hapus)',
};
const NONE = '';

/**
 * Hak akses per role: menu yang tampil dan tindakan di dalamnya. Pilihan per menu hanya
 * tingkat yang benar-benar dipakai menu itu (dari server), ditambah "Tidak tampil".
 */
export default function RoleAccessPanel() {
  const { request, user, refreshSession } = useAuth();
  const { data, error, loading, reload } = useResource('/role-access');
  const [role, setRole] = useState('supervisor');
  const [draft, setDraft] = useState(null);
  const [saved, setSaved] = useState(null);
  const [message, setMessage] = useState('');
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);
  const source = saved ?? data;
  const current = source?.roles[role]?.levels;

  useEffect(() => {
    if (current) setDraft({ ...current });
  }, [current]);

  if (!source || !draft) {
    return loading || !error ? <LoadingCard label="Memuat hak akses…" /> : <ErrorCard title="Hak akses belum bisa dimuat." message={error} onRetry={reload} />;
  }

  const dirty = Object.keys(draft).some((key) => (draft[key] ?? NONE) !== (current[key] ?? NONE));
  const shownCount = Object.values(draft).filter(Boolean).length;

  const choose = (next) => {
    if (next === role) return;
    setRole(next);
    setMessage('');
    setFormError('');
  };

  const save = async () => {
    setSaving(true);
    setMessage('');
    setFormError('');
    try {
      const response = await request(`/role-access/${role}`, { method: 'PUT', body: { levels: draft } });
      setSaved(response.data);
      setMessage(response.message);
      // Bila role sendiri yang diubah, menu Super Admin ikut diperbarui sekarang
      if (role === user.role) refreshSession();
    } catch (err) {
      const first = err.data?.errors ? Object.values(err.data.errors)[0]?.[0] : null;
      setFormError(first ?? err.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <p id="role-picker-label" className="mb-1.5 text-label font-medium text-gray-800">
          Atur role
        </p>
        <div role="group" aria-labelledby="role-picker-label" className="flex flex-wrap gap-2">
          {Object.entries(ROLE_LABELS).map(([key, label]) => (
            <button
              key={key}
              type="button"
              aria-pressed={role === key}
              onClick={() => choose(key)}
              className={`focus-ring h-11 rounded-lg border px-4 text-body font-semibold transition-colors ${
                role === key ? 'border-brand-blue-600 bg-brand-blue-50 text-brand-blue-700' : 'border-gray-200 bg-white text-gray-800 hover:bg-gray-25'
              }`}
            >
              {label}
              <span className="ml-1.5 font-normal text-gray-600">{source.roles[key].user_count}</span>
            </button>
          ))}
        </div>
      </div>

      {message && <Alert tone="success">{message}</Alert>}
      {formError && <Alert tone="danger">{formError}</Alert>}

      {MODULE_GROUPS.map((group) => {
        const modules = group.modules.filter((m) => source.options[m.key]);
        if (!modules.length) return null;
        return (
          <section key={group.label} aria-labelledby={`akses-${group.label}`} className="card">
            <h2 id={`akses-${group.label}`} className="p-4 pb-0 text-h2 font-semibold sm:p-5 sm:pb-0">
              {group.label}
            </h2>
            <ul className="mt-2 divide-y divide-gray-100">
              {modules.map((m) => {
                const options = source.options[m.key];
                const locked = role === 'admin' && m.key === 'pengguna';
                const value = draft[m.key] ?? NONE;
                const changed = value !== (current[m.key] ?? NONE);
                return (
                  <li key={m.key} className="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-12 sm:items-center sm:px-5">
                    <div className="sm:col-span-7">
                      <label htmlFor={`akses-${m.key}`} className="text-body font-semibold text-gray-900">
                        {m.label}
                      </label>
                    </div>
                    <div className="sm:col-span-5">
                      <select
                        id={`akses-${m.key}`}
                        value={value}
                        disabled={locked}
                        onChange={(e) => setDraft((d) => ({ ...d, [m.key]: e.target.value || null }))}
                        className={`field disabled:bg-gray-25 ${changed ? 'border-brand-blue-600' : ''} ${value ? '' : 'text-gray-600'}`}
                        aria-describedby={locked ? `akses-${m.key}-note` : undefined}
                      >
                        <option value={NONE}>Tidak tampil di menu</option>
                        {options.map((level) => (
                          <option key={level} value={level}>
                            {LEVEL_TEXT[level]}
                          </option>
                        ))}
                      </select>
                      {locked && (
                        <p id={`akses-${m.key}-note`} className="mt-1 text-caption text-gray-600">
                          Dikunci supaya Super Admin tetap bisa mengatur pengguna.
                        </p>
                      )}
                      {changed && !locked && <p className="mt-1 text-caption text-brand-blue-700">Belum disimpan</p>}
                    </div>
                  </li>
                );
              })}
            </ul>
          </section>
        );
      })}

      <div className="sticky bottom-0 -mx-4 flex flex-col-reverse gap-3 border-t border-gray-200 bg-gray-25 px-4 py-3 sm:mx-0 sm:flex-row sm:items-center sm:justify-between sm:rounded-card sm:border">
        <p className="text-caption text-gray-600">
          {ROLE_LABELS[role]}: {shownCount} menu tampil.
        </p>
        <div className="flex flex-col-reverse gap-3 sm:flex-row">
          <button type="button" disabled={!dirty || saving} onClick={() => setDraft({ ...current })} className="btn-secondary h-11 px-4 text-body">
            Batalkan perubahan
          </button>
          <button type="button" disabled={!dirty || saving} onClick={save} className="btn-primary h-11 px-4 text-body">
            {saving && <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />}
            {saving ? 'Menyimpan…' : `Simpan hak akses ${ROLE_LABELS[role]}`}
          </button>
        </div>
      </div>
    </div>
  );
}
