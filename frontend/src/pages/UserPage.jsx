import { useMemo, useState } from 'react';
import { Eye, EyeOff, KeyRound, LoaderCircle, Pencil, Plus, Trash2, Users } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { fieldErrorsFrom, useResource } from '../lib/useResource.js';
import { formatDateTime } from '../lib/format.js';
import { ROLE_LABELS, roleLabel } from '../roles.js';
import PageHeader from '../components/PageHeader.jsx';
import Field from '../components/Field.jsx';
import Alert from '../components/Alert.jsx';
import Modal from '../components/Modal.jsx';
import EmptyState from '../components/EmptyState.jsx';
import { ErrorCard, LoadingCard } from '../components/LoadState.jsx';
import { Table, Td, Th } from '../components/Table.jsx';
import RoleAccessPanel from './RoleAccessPanel.jsx';

const MIN_PASSWORD = 8;

function ActiveBadge({ active }) {
  return active ? (
    <span className="inline-flex rounded-full border border-success-border bg-success-bg px-2 py-0.5 text-caption font-medium text-success-fg">
      Aktif
    </span>
  ) : (
    <span className="inline-flex rounded-full border border-gray-200 bg-gray-25 px-2 py-0.5 text-caption font-medium text-gray-600">
      Nonaktif
    </span>
  );
}

function SaveButton({ busy, children }) {
  return (
    <button type="submit" disabled={busy} className="btn-primary h-11 px-4 text-body">
      {busy && <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />}
      {busy ? 'Menyimpan…' : children}
    </button>
  );
}

// Satu form untuk tiga dialog: tambah, ubah, dan reset password
function useSubmit(send, onSaved) {
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async (localErrors) => {
    setErrors(localErrors);
    setFormError('');
    if (Object.keys(localErrors).length) return;
    setBusy(true);
    try {
      onSaved((await send()).message);
    } catch (err) {
      const server = fieldErrorsFrom(err);
      setErrors(server);
      if (!Object.keys(server).length) setFormError(err.message);
    } finally {
      setBusy(false);
    }
  };

  return { errors, formError, busy, submit };
}

function RoleSelect({ value, onChange, disabled, ...props }) {
  return (
    <select {...props} value={value} disabled={disabled} onChange={(e) => onChange(e.target.value)} className="field disabled:bg-gray-25">
      {Object.entries(ROLE_LABELS).map(([role, label]) => (
        <option key={role} value={role}>
          {label}
        </option>
      ))}
    </select>
  );
}

function PasswordInput({ value, onChange, ...props }) {
  // Teks terlihat: Super Admin perlu membaca ulang password sementara sebelum memberikannya
  return <input {...props} type="text" autoComplete="off" spellCheck={false} value={value} onChange={(e) => onChange(e.target.value)} className="field font-mono" />;
}

function AddDialog({ onClose, onSaved }) {
  const { request } = useAuth();
  const [form, setForm] = useState({ nik: '', name: '', role: 'operator', password: '' });
  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }));
  const { errors, formError, busy, submit } = useSubmit(() => request('/users', { method: 'POST', body: form }), onSaved);

  const onSubmit = (e) => {
    e.preventDefault();
    const errs = {};
    if (!form.nik.trim()) errs.nik = 'Isi NIK.';
    if (!form.name.trim()) errs.name = 'Isi nama.';
    if (form.password.length < MIN_PASSWORD) errs.password = `Password paling sedikit ${MIN_PASSWORD} karakter.`;
    submit(errs);
  };

  return (
    <Modal title="Tambah pengguna" onClose={() => !busy && onClose()}>
      <form onSubmit={onSubmit} noValidate className="space-y-4">
        {formError && <Alert tone="danger">{formError}</Alert>}
        <Field id="user-nik" label="NIK" required hint="Dipakai untuk login dan tidak bisa diubah. Contoh OP-1002." error={errors.nik}>
          {(props) => <input {...props} type="text" maxLength={30} value={form.nik} onChange={(e) => set('nik')(e.target.value)} className="field font-mono uppercase" />}
        </Field>
        <Field id="user-name" label="Nama" required error={errors.name}>
          {(props) => <input {...props} type="text" maxLength={100} value={form.name} onChange={(e) => set('name')(e.target.value)} className="field" />}
        </Field>
        <Field id="user-role" label="Role" required error={errors.role}>
          {(props) => <RoleSelect {...props} value={form.role} onChange={set('role')} />}
        </Field>
        <Field id="user-password" label="Password" required hint={`Paling sedikit ${MIN_PASSWORD} karakter. Berikan ke pengguna setelah disimpan.`} error={errors.password}>
          {(props) => <PasswordInput {...props} value={form.password} onChange={set('password')} />}
        </Field>
        <div className="flex flex-col-reverse gap-3 pt-1 sm:flex-row sm:justify-end">
          <button type="button" onClick={onClose} disabled={busy} className="btn-secondary h-11 px-4 text-body">
            Batal
          </button>
          <SaveButton busy={busy}>Tambah pengguna</SaveButton>
        </div>
      </form>
    </Modal>
  );
}

function EditDialog({ user, isSelf, onClose, onSaved }) {
  const { request } = useAuth();
  const [form, setForm] = useState({ name: user.name, role: user.role, is_active: user.is_active });
  const { errors, formError, busy, submit } = useSubmit(() => request(`/users/${user.id}`, { method: 'PUT', body: form }), onSaved);

  const onSubmit = (e) => {
    e.preventDefault();
    submit(form.name.trim() ? {} : { name: 'Isi nama.' });
  };

  return (
    <Modal title={`Ubah pengguna ${user.nik}`} onClose={() => !busy && onClose()}>
      <form onSubmit={onSubmit} noValidate className="space-y-4">
        {formError && <Alert tone="danger">{formError}</Alert>}
        <Field id="edit-name" label="Nama" required error={errors.name}>
          {(props) => (
            <input {...props} type="text" maxLength={100} value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} className="field" />
          )}
        </Field>
        <Field
          id="edit-role"
          label="Role"
          required
          hint={isSelf ? 'Role akun Anda sendiri tidak bisa diubah.' : 'Pengguna otomatis keluar bila role diubah, lalu login ulang dengan hak akses baru.'}
          error={errors.role}
        >
          {(props) => <RoleSelect {...props} value={form.role} disabled={isSelf} onChange={(role) => setForm((f) => ({ ...f, role }))} />}
        </Field>
        <div>
          <label className="flex min-h-11 items-center gap-3 text-body text-gray-800">
            <input
              type="checkbox"
              checked={form.is_active}
              disabled={isSelf}
              onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked }))}
              className="h-5 w-5 accent-brand-blue-600"
            />
            Aktif, bisa login
          </label>
          <p className={`mt-1 text-caption ${errors.is_active ? 'text-danger-fg' : 'text-gray-600'}`}>
            {errors.is_active ??
              (isSelf ? 'Akun Anda sendiri tidak bisa dinonaktifkan.' : 'Akun nonaktif langsung keluar dan tidak bisa login. Riwayatnya tetap tersimpan.')}
          </p>
        </div>
        <div className="flex flex-col-reverse gap-3 pt-1 sm:flex-row sm:justify-end">
          <button type="button" onClick={onClose} disabled={busy} className="btn-secondary h-11 px-4 text-body">
            Batal
          </button>
          <SaveButton busy={busy}>Simpan</SaveButton>
        </div>
      </form>
    </Modal>
  );
}

function PasswordDialog({ user, onClose, onSaved }) {
  const { request } = useAuth();
  const [password, setPassword] = useState('');
  const { errors, formError, busy, submit } = useSubmit(
    () => request(`/users/${user.id}/password`, { method: 'POST', body: { password } }),
    onSaved,
  );

  return (
    <Modal title={`Reset password ${user.nik}?`} onClose={() => !busy && onClose()}>
      <form
        onSubmit={(e) => {
          e.preventDefault();
          submit(password.length < MIN_PASSWORD ? { password: `Password paling sedikit ${MIN_PASSWORD} karakter.` } : {});
        }}
        noValidate
        className="space-y-4"
      >
        <p className="text-body text-gray-800">
          {user.name} keluar dari semua perangkat dan harus login dengan password baru ini.
        </p>
        {formError && <Alert tone="danger">{formError}</Alert>}
        <Field id="reset-password" label="Password baru" required hint={`Paling sedikit ${MIN_PASSWORD} karakter.`} error={errors.password}>
          {(props) => <PasswordInput {...props} value={password} onChange={setPassword} />}
        </Field>
        <div className="flex flex-col-reverse gap-3 pt-1 sm:flex-row sm:justify-end">
          <button type="button" onClick={onClose} disabled={busy} className="btn-secondary h-11 px-4 text-body">
            Batal
          </button>
          <SaveButton busy={busy}>Ganti password</SaveButton>
        </div>
      </form>
    </Modal>
  );
}

// Kartu detail satu pengguna. Password tersimpan sebagai hash, jadi tidak bisa ditampilkan;
// yang bisa dilakukan adalah mengganti dengan password baru.
// Password diambil dari server hanya saat tombol Tampilkan ditekan (dicatat di audit log),
// dan tidak disimpan setelah kartu ditutup
function PasswordRow({ user, onChange }) {
  const { request } = useAuth();
  const [state, setState] = useState({ shown: false, value: null, loading: false, error: '' });

  const reveal = async () => {
    if (state.shown) return setState((s) => ({ ...s, shown: false }));
    setState({ shown: false, value: null, loading: true, error: '' });
    try {
      const { data } = await request(`/users/${user.id}/password`);
      setState({ shown: true, value: data.password, loading: false, error: '' });
    } catch (err) {
      setState({ shown: false, value: null, loading: false, error: err.message });
    }
  };

  return (
    <div className="py-2">
      <div className="flex items-center justify-between gap-4">
        <dt className="text-gray-600">Password</dt>
        <dd className="min-w-0 text-right">
          {state.shown ? (
            state.value ? (
              <span className="break-all font-mono text-gray-900">{state.value}</span>
            ) : (
              <span className="text-caption text-gray-600">Belum tersimpan</span>
            )
          ) : (
            <span className="font-mono tracking-widest text-gray-600" aria-label="Disembunyikan">
              ••••••••
            </span>
          )}
        </dd>
      </div>
      {state.shown && !state.value && (
        <p className="mt-1 text-caption text-gray-600">
          Password akun ini dibuat sebelum fitur lihat password ada, jadi tidak bisa dibaca. Ganti password sekali, setelah itu bisa dilihat di
          sini.
        </p>
      )}
      {state.error && <p className="mt-1 text-caption text-danger-fg">{state.error}</p>}
      <div className="mt-2 flex flex-wrap justify-end gap-2">
        <button type="button" onClick={reveal} disabled={state.loading} aria-pressed={state.shown} className="btn-secondary h-11 px-3 text-body">
          {state.loading ? (
            <LoaderCircle className="h-4 w-4 motion-safe:animate-spin" aria-hidden="true" />
          ) : state.shown ? (
            <EyeOff className="h-4 w-4" aria-hidden="true" />
          ) : (
            <Eye className="h-4 w-4" aria-hidden="true" />
          )}
          {state.shown ? 'Sembunyikan' : 'Tampilkan'}
        </button>
        <button type="button" onClick={onChange} className="btn-secondary h-11 px-3 text-body">
          <KeyRound className="h-4 w-4" aria-hidden="true" />
          Ganti password
        </button>
      </div>
    </div>
  );
}

function DetailDialog({ user, isSelf, onClose, onAction }) {
  const rows = [
    ['NIK', <span key="nik" className="font-mono">{user.nik}</span>],
    ['Nama', user.name],
    ['Role', roleLabel(user.role)],
    ['Status', <ActiveBadge key="status" active={user.is_active} />],
    ['Dibuat', user.created_at ? formatDateTime(user.created_at) : '–'],
    ['Terakhir aktif', user.last_active_at ? formatDateTime(user.last_active_at) : 'Belum pernah login'],
  ];

  return (
    <Modal title={`Pengguna ${user.nik}`} onClose={onClose}>
      <dl className="divide-y divide-gray-100 text-body">
        {rows.map(([label, value]) => (
          <div key={label} className="flex justify-between gap-4 py-2">
            <dt className="text-gray-600">{label}</dt>
            <dd className="text-right text-gray-900">{value}</dd>
          </div>
        ))}
        <PasswordRow user={user} onChange={() => onAction('password')} />
      </dl>
      {isSelf && <p className="mt-3 text-caption text-gray-600">Ini akun Anda: role, status, dan hapus akun tidak bisa diubah dari sini.</p>}
      <div className="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
        <button
          type="button"
          onClick={() => onAction('delete')}
          disabled={isSelf}
          className="btn-secondary h-11 px-4 text-body text-danger-fg"
        >
          <Trash2 className="h-4 w-4" aria-hidden="true" />
          Hapus akun
        </button>
        <div className="flex flex-col-reverse gap-3 sm:flex-row">
          <button type="button" onClick={onClose} className="btn-secondary h-11 px-4 text-body">
            Tutup
          </button>
          <button type="button" onClick={() => onAction('edit')} className="btn-primary h-11 px-4 text-body">
            <Pencil className="h-4 w-4" aria-hidden="true" />
            Ubah data
          </button>
        </div>
      </div>
    </Modal>
  );
}

function DeleteDialog({ user, onClose, onSaved }) {
  const { request } = useAuth();
  const { errors, formError, busy, submit } = useSubmit(() => request(`/users/${user.id}`, { method: 'DELETE' }), onSaved);
  const problem = errors.user ?? formError;

  return (
    <Modal title={`Hapus akun ${user.nik}?`} onClose={() => !busy && onClose()}>
      <p className="text-body text-gray-800">
        {user.name} langsung keluar dan tidak bisa login lagi. Namanya tetap tampil di riwayat input, surat jalan, dan laporan lama.
        NIK {user.nik} tidak bisa dipakai untuk akun baru.
      </p>
      <p className="mt-2 text-caption text-gray-600">Bila hanya ingin menahan akses sementara, nonaktifkan lewat Ubah data.</p>
      {problem && (
        <Alert tone="danger" className="mt-4">
          {problem}
        </Alert>
      )}
      <div className="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <button type="button" onClick={onClose} disabled={busy} className="btn-secondary h-11 px-4 text-body">
          Batal
        </button>
        <button type="button" onClick={() => submit({})} disabled={busy} className="btn-danger h-11 px-4 text-body">
          {busy && <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />}
          {busy ? 'Menghapus…' : 'Ya, hapus akun'}
        </button>
      </div>
    </Modal>
  );
}

function UserList() {
  const { user: me } = useAuth();
  const { data, error, loading, reload } = useResource('/users');
  const [q, setQ] = useState('');
  const [role, setRole] = useState('');
  const [status, setStatus] = useState('');
  const [dialog, setDialog] = useState(null);
  const [message, setMessage] = useState('');

  const shown = useMemo(() => {
    const term = q.trim().toLowerCase();
    return (data ?? []).filter(
      (u) =>
        (!term || u.nik.toLowerCase().includes(term) || u.name.toLowerCase().includes(term)) &&
        (!role || u.role === role) &&
        (!status || (status === 'aktif') === u.is_active),
    );
  }, [data, q, role, status]);

  const open = (next) => {
    setMessage('');
    setDialog(next);
  };

  const saved = (msg) => {
    setDialog(null);
    setMessage(msg);
    reload();
  };

  return (
    <>
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
        <button type="button" onClick={() => open({ type: 'add' })} className="btn-primary h-11 shrink-0 px-4 text-body">
          <Plus className="h-5 w-5" aria-hidden="true" />
          Tambah pengguna
        </button>
      </div>
      {message && (
        <Alert tone="success" className="mt-6">
          {message}
        </Alert>
      )}

      {!data ? (
        <div className="mt-6">
          {loading ? <LoadingCard label="Memuat pengguna…" /> : <ErrorCard title="Daftar pengguna belum bisa dimuat." message={error} onRetry={reload} />}
        </div>
      ) : (
        <section aria-labelledby="users-title" className="card mt-6">
          <div className="p-4 sm:p-5">
            <h2 id="users-title" className="sr-only">
              Daftar pengguna
            </h2>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-12" role="search" aria-label="Filter pengguna">
              <div className="sm:col-span-6">
                <label htmlFor="user-q" className="mb-1.5 block text-label font-medium text-gray-800">
                  Cari
                </label>
                <input id="user-q" type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="NIK atau nama" className="field" />
              </div>
              <div className="sm:col-span-3">
                <label htmlFor="user-role-filter" className="mb-1.5 block text-label font-medium text-gray-800">
                  Role
                </label>
                <select id="user-role-filter" value={role} onChange={(e) => setRole(e.target.value)} className="field">
                  <option value="">Semua role</option>
                  {Object.entries(ROLE_LABELS).map(([value, label]) => (
                    <option key={value} value={value}>
                      {label}
                    </option>
                  ))}
                </select>
              </div>
              <div className="sm:col-span-3">
                <label htmlFor="user-status-filter" className="mb-1.5 block text-label font-medium text-gray-800">
                  Status
                </label>
                <select id="user-status-filter" value={status} onChange={(e) => setStatus(e.target.value)} className="field">
                  <option value="">Semua status</option>
                  <option value="aktif">Aktif</option>
                  <option value="nonaktif">Nonaktif</option>
                </select>
              </div>
            </div>
          </div>

          {shown.length ? (
            <Table caption="Daftar pengguna">
              <thead>
                <tr>
                  <Th>NIK</Th>
                  <Th>Nama</Th>
                  <Th>Role</Th>
                  <Th>Status</Th>
                  <Th>Terakhir aktif</Th>
                  <Th>
                    <span className="sr-only">Aksi</span>
                  </Th>
                </tr>
              </thead>
              <tbody>
                {shown.map((u) => (
                  <tr key={u.id}>
                    <Td className="whitespace-nowrap font-mono text-gray-900">{u.nik}</Td>
                    <Td>
                      {u.name}
                      {u.id === me.id && <span className="block text-caption text-gray-600">Akun Anda</span>}
                    </Td>
                    <Td className="whitespace-nowrap">{roleLabel(u.role)}</Td>
                    <Td>
                      <ActiveBadge active={u.is_active} />
                    </Td>
                    <Td className="whitespace-nowrap">{u.last_active_at ? formatDateTime(u.last_active_at) : <span className="text-gray-600">Belum pernah login</span>}</Td>
                    <Td className="text-right">
                      <button type="button" onClick={() => open({ type: 'view', user: u })} aria-label={`Lihat ${u.nik}`} className="btn-secondary h-11 px-3 text-body">
                        <Eye className="h-4 w-4" aria-hidden="true" />
                        Lihat
                      </button>
                    </Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          ) : (
            <EmptyState icon={Users} title={data.length ? 'Tidak ada pengguna yang cocok dengan filter.' : 'Belum ada pengguna.'}>
              {data.length ? 'Ubah kata kunci atau filter.' : 'Tambah pengguna untuk mulai.'}
            </EmptyState>
          )}
        </section>
      )}

      {dialog?.type === 'add' && <AddDialog onClose={() => setDialog(null)} onSaved={saved} />}
      {dialog?.type === 'view' && (
        <DetailDialog
          user={dialog.user}
          isSelf={dialog.user.id === me.id}
          onClose={() => setDialog(null)}
          onAction={(type) => setDialog({ type, user: dialog.user })}
        />
      )}
      {/* Tutup dialog ubah, reset, atau hapus kembali ke kartu detail pengguna */}
      {dialog?.type === 'edit' && (
        <EditDialog user={dialog.user} isSelf={dialog.user.id === me.id} onClose={() => setDialog({ type: 'view', user: dialog.user })} onSaved={saved} />
      )}
      {dialog?.type === 'password' && (
        <PasswordDialog user={dialog.user} onClose={() => setDialog({ type: 'view', user: dialog.user })} onSaved={saved} />
      )}
      {dialog?.type === 'delete' && (
        <DeleteDialog user={dialog.user} onClose={() => setDialog({ type: 'view', user: dialog.user })} onSaved={saved} />
      )}
    </>
  );
}

export default function UserPage() {
  const [tab, setTab] = useState('users');
  const tabs = [
    { key: 'users', label: 'Daftar pengguna' },
    { key: 'access', label: 'Hak akses role' },
  ];

  return (
    <>
      <PageHeader title="Pengguna" />
      <div role="tablist" aria-label="Bagian pengguna" className="my-6 inline-flex rounded-lg border border-gray-200 bg-white p-1">
        {tabs.map((t) => (
          <button
            key={t.key}
            type="button"
            role="tab"
            aria-selected={tab === t.key}
            onClick={() => setTab(t.key)}
            className={`focus-ring h-11 rounded-md px-4 text-body font-semibold transition-colors ${
              tab === t.key ? 'bg-brand-blue-600 text-white' : 'text-gray-800 hover:bg-gray-25'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>
      {tab === 'users' ? <UserList /> : <RoleAccessPanel />}
    </>
  );
}
