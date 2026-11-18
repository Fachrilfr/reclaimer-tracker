import { useState } from 'react';
import { LoaderCircle, Pencil, Plus, Trash2 } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { fieldErrorsFrom, useResource } from '../lib/useResource.js';
import { formatDate, formatNumber, parseDecimal, toIso } from '../lib/format.js';
import PageHeader from '../components/PageHeader.jsx';
import Field from '../components/Field.jsx';
import Alert from '../components/Alert.jsx';
import Modal from '../components/Modal.jsx';
import { ErrorCard, LoadingCard } from '../components/LoadState.jsx';
import { Table, Td, Th } from '../components/Table.jsx';

// Isian tiap jenis data master. `fields` dipakai form tambah dan ubah; `path` alamat API.
const KINDS = {
  shift: {
    noun: 'shift',
    path: '/master/shifts',
    canCreate: true,
    canToggle: true,
    fields: [
      { key: 'code', label: 'Kode', required: true, hint: 'Contoh SHIFT-4.' },
      { key: 'name', label: 'Nama', required: true },
      { key: 'start_time', label: 'Jam mulai', required: true, type: 'time' },
      { key: 'end_time', label: 'Jam selesai', required: true, type: 'time', hint: 'Shift malam boleh selesai keesokan hari.' },
    ],
    empty: { code: '', name: '', start_time: '', end_time: '' },
    deleteNote: 'Shift yang sudah dipakai di input atau pemakaian solvent tidak bisa dihapus. Untuk shift itu, nonaktifkan saja lewat Ubah.',
  },
  vendor: {
    noun: 'vendor',
    path: '/master/vendors',
    canCreate: true,
    canToggle: true,
    fields: [
      { key: 'name', label: 'Nama vendor', required: true },
      { key: 'pic_name', label: 'PIC', hint: 'Opsional.' },
      { key: 'contact', label: 'Kontak', hint: 'Opsional. Nomor telepon atau email.' },
      { key: 'address', label: 'Alamat', hint: 'Opsional.', multiline: true },
    ],
    empty: { name: '', pic_name: '', contact: '', address: '' },
    deleteNote: 'Vendor hilang dari daftar dan tidak bisa dipilih lagi. Surat jalan lama tetap menampilkan nama vendor ini.',
  },
};

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

function EditDialog({ kind, item, onClose, onSaved }) {
  const { request } = useAuth();
  const config = KINDS[kind];
  const isNew = !item.id;
  const [form, setForm] = useState(() => ({ ...item, ...Object.fromEntries(config.fields.map((f) => [f.key, item[f.key] ?? ''])) }));
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);

  const submit = async (e) => {
    e.preventDefault();
    const errs = Object.fromEntries(
      config.fields.filter((f) => f.required && !String(form[f.key] ?? '').trim()).map((f) => [f.key, `Isi ${f.label.toLowerCase()}.`]),
    );
    setErrors(errs);
    setFormError('');
    if (Object.keys(errs).length) return;

    setSaving(true);
    try {
      const body = Object.fromEntries(config.fields.map((f) => [f.key, form[f.key]]));
      if (config.canToggle && !isNew) body.is_active = form.is_active;
      const response = await request(isNew ? config.path : `${config.path}/${item.id}`, { method: isNew ? 'POST' : 'PUT', body });
      onSaved(response.message, response.data);
    } catch (err) {
      const server = fieldErrorsFrom(err);
      setErrors(server);
      if (!Object.keys(server).length) setFormError(err.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal title={isNew ? `Tambah ${config.noun}` : `Ubah ${config.noun} ${item.name}`} onClose={() => !saving && onClose()}>
      <form onSubmit={submit} noValidate className="space-y-4">
        {formError && <Alert tone="danger">{formError}</Alert>}
        {config.fields.map((f) => (
          <Field key={f.key} id={`${kind}-${f.key}`} label={f.label} required={f.required} hint={f.hint} error={errors[f.key]}>
            {(props) =>
              f.multiline ? (
                <textarea
                  {...props}
                  rows={2}
                  value={form[f.key]}
                  onChange={(e) => setForm((s) => ({ ...s, [f.key]: e.target.value }))}
                  className="field min-h-[4.5rem] py-2"
                />
              ) : (
                <input
                  {...props}
                  type={f.type ?? 'text'}
                  value={form[f.key]}
                  onChange={(e) => setForm((s) => ({ ...s, [f.key]: e.target.value }))}
                  className="field"
                />
              )
            }
          </Field>
        ))}
        {config.canToggle && !isNew && (
          <div>
            <label className="flex min-h-11 items-center gap-3 text-body text-gray-800">
              <input
                type="checkbox"
                checked={form.is_active}
                onChange={(e) => setForm((s) => ({ ...s, is_active: e.target.checked }))}
                className="h-5 w-5 accent-brand-blue-600"
              />
              Aktif, bisa dipilih di form
            </label>
            {errors.is_active && <p className="mt-1 text-caption text-danger-fg">{errors.is_active}</p>}
          </div>
        )}
        <div className="flex flex-col-reverse gap-3 pt-1 sm:flex-row sm:justify-end">
          <button type="button" onClick={onClose} disabled={saving} className="btn-secondary h-11 px-4 text-body">
            Batal
          </button>
          <button type="submit" disabled={saving} className="btn-primary h-11 px-4 text-body">
            {saving && <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />}
            {saving ? 'Menyimpan…' : isNew ? `Tambah ${config.noun}` : 'Simpan'}
          </button>
        </div>
      </form>
    </Modal>
  );
}

function EditButton({ label, onClick }) {
  return (
    <button type="button" onClick={onClick} aria-label={label} className="btn-secondary h-11 px-3 text-body">
      <Pencil className="h-4 w-4" aria-hidden="true" />
      Ubah
    </button>
  );
}

function DeleteButton({ label, onClick }) {
  return (
    <button type="button" onClick={onClick} aria-label={label} className="btn-secondary h-11 px-3 text-body text-danger-fg">
      <Trash2 className="h-4 w-4" aria-hidden="true" />
      Hapus
    </button>
  );
}

// Konfirmasi hapus. Shift yang sudah dipakai ditolak server dengan pesan untuk menonaktifkannya.
function DeleteDialog({ kind, item, onClose, onDeleted }) {
  const { request } = useAuth();
  const config = KINDS[kind];
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const remove = async () => {
    setBusy(true);
    setError('');
    try {
      const response = await request(`${config.path}/${item.id}`, { method: 'DELETE' });
      onDeleted(response.message, response.data);
    } catch (err) {
      setError(Object.values(fieldErrorsFrom(err))[0] ?? err.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal title={`Hapus ${config.noun} ${item.name}?`} onClose={() => !busy && onClose()}>
      <p className="text-body text-gray-800">{config.deleteNote}</p>
      {error && (
        <Alert tone="danger" className="mt-4">
          {error}
        </Alert>
      )}
      <div className="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <button type="button" onClick={onClose} disabled={busy} className="btn-secondary h-11 px-4 text-body">
          Batal
        </button>
        <button type="button" onClick={remove} disabled={busy} className="btn-danger h-11 px-4 text-body">
          {busy && <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />}
          {busy ? 'Menghapus…' : `Ya, hapus ${config.noun}`}
        </button>
      </div>
    </Modal>
  );
}

function localToday() {
  const d = new Date();
  return toIso(d.getFullYear(), d.getMonth() + 1, d.getDate());
}

// Saldo awal solvent dari hitung fisik. Bila sudah ada mutasi, semua saldo sesudahnya ikut
// bergeser dengan selisihnya, jadi alasan wajib diisi.
function OpeningDialog({ opening, onClose, onSaved }) {
  const { request } = useAuth();
  const [form, setForm] = useState({
    amount: opening.amount === null ? '' : String(opening.amount).replace('.', ','),
    date: opening.date ?? localToday(),
    reason: '',
  });
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);
  const used = opening.mutation_count > 0;
  const amount = parseDecimal(form.amount);
  const delta = Number.isFinite(amount) && opening.amount !== null ? Math.round((amount - opening.amount) * 100) / 100 : 0;
  const today = localToday();
  const maxDate = opening.first_mutation_date && opening.first_mutation_date < today ? opening.first_mutation_date : today;

  const set = (key) => (e) => {
    setForm((f) => ({ ...f, [key]: e.target.value }));
    setErrors((errs) => ({ ...errs, [key]: undefined }));
  };

  const submit = async (e) => {
    e.preventDefault();
    const errs = {};
    if (amount === null || Number.isNaN(amount)) errs.amount = 'Isi saldo awal dengan angka, contoh 1.250,5.';
    if (!form.date) errs.date = 'Isi tanggal saldo awal.';
    if (used && !form.reason.trim()) errs.reason = 'Tulis alasan mengubah saldo awal, misalnya "Hasil hitung fisik ulang".';
    setErrors(errs);
    setFormError('');
    if (Object.keys(errs).length) return;

    setSaving(true);
    try {
      const response = await request('/master/solvent-opening', {
        method: 'PUT',
        body: { amount, date: form.date, reason: form.reason.trim() || null },
      });
      onSaved(response.message, response.data);
    } catch (err) {
      const server = fieldErrorsFrom(err);
      setErrors(server);
      if (!Object.keys(server).length) setFormError(err.message);
      setSaving(false);
    }
  };

  return (
    <Modal title="Saldo awal solvent" onClose={() => !saving && onClose()}>
      <form onSubmit={submit} noValidate className="space-y-4">
        {formError && <Alert tone="danger">{formError}</Alert>}
        {used && (
          <Alert tone="warning">
            Sudah ada {opening.mutation_count} mutasi solvent sejak saldo awal. Bila saldo awal diubah, semua saldo sesudahnya ikut bergeser
            {delta ? ` ${delta > 0 ? '+' : '−'}${formatNumber(Math.abs(delta))} kg` : ' dengan selisihnya'}, termasuk saldo yang tercatat di input
            dan pemakaian solvent.
          </Alert>
        )}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field id="saldo-awal" label="Saldo awal" required hint="Hasil hitung fisik solvent saat aplikasi mulai dipakai." error={errors.amount}>
            {(props) => (
              <div className="relative">
                <input
                  {...props}
                  type="text"
                  inputMode="decimal"
                  autoComplete="off"
                  value={form.amount}
                  onChange={set('amount')}
                  className="field pr-11 text-right tabular-nums"
                />
                <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-body text-gray-600">kg</span>
              </div>
            )}
          </Field>
          <Field
            id="saldo-tanggal"
            label="Tanggal saldo awal"
            required
            hint={opening.first_mutation_date ? `Paling lambat ${formatDate(opening.first_mutation_date)}, tanggal mutasi pertama.` : undefined}
            error={errors.date}
          >
            {(props) => <input {...props} type="date" max={maxDate} value={form.date} onChange={set('date')} className="field" />}
          </Field>
        </div>
        {used && (
          <Field id="saldo-alasan" label="Alasan mengubah" required hint="Tercatat di jejak audit." error={errors.reason}>
            {(props) => <textarea {...props} rows={2} maxLength={255} value={form.reason} onChange={set('reason')} className="field h-auto py-2" />}
          </Field>
        )}
        <div className="flex flex-col-reverse gap-3 pt-1 sm:flex-row sm:justify-end">
          <button type="button" onClick={onClose} disabled={saving} className="btn-secondary h-11 px-4 text-body">
            Batal
          </button>
          <button type="submit" disabled={saving} className="btn-primary h-11 px-4 text-body">
            {saving && <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />}
            {saving ? 'Menyimpan…' : 'Simpan saldo awal'}
          </button>
        </div>
      </form>
    </Modal>
  );
}

function AddButton({ noun, onClick }) {
  return (
    <button type="button" onClick={onClick} className="btn-primary h-11 shrink-0 px-4 text-body">
      <Plus className="h-5 w-5" aria-hidden="true" />
      Tambah {noun}
    </button>
  );
}

function SectionHeader({ id, title, description, action }) {
  return (
    <div className="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between sm:p-5">
      <div>
        <h2 id={id} className="text-h2 font-semibold">
          {title}
        </h2>
        {description && <p className="mt-1 text-body text-gray-600">{description}</p>}
      </div>
      {action}
    </div>
  );
}

export default function MasterDataPage() {
  const { can } = useAuth();
  const canEdit = can('data_master', 'kelola');
  const { data, error, loading, reload } = useResource('/master');
  const [saved, setSaved] = useState(null);
  const [message, setMessage] = useState('');
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [openingOpen, setOpeningOpen] = useState(false);
  const master = saved ?? data;

  const open = (kind, item) => {
    setMessage('');
    setEditing({ kind, item: item ?? { ...KINDS[kind].empty } });
  };

  return (
    <>
      <PageHeader
        title="Data master"
      />
      {message && (
        <Alert tone="success" className="mt-6">
          {message}
        </Alert>
      )}

      {!master ? (
        <div className="mt-6">
          {loading ? <LoadingCard label="Memuat data master…" /> : <ErrorCard title="Data master belum bisa dimuat." message={error} onRetry={reload} />}
        </div>
      ) : (
        <div className="mt-6 space-y-6">
          <section aria-labelledby="master-solvent" className="card">
            <SectionHeader
              id="master-solvent"
              title="Saldo awal solvent"
              description="Titik awal saldo solvent: hasil hitung fisik saat aplikasi mulai dipakai. Hasil solvent dari input menambah saldo, pemakaian solvent menguranginya."
              action={
                canEdit && (
                  <button type="button" onClick={() => setOpeningOpen(true)} className="btn-secondary h-11 shrink-0 px-4 text-body">
                    <Pencil className="h-4 w-4" aria-hidden="true" />
                    Ubah saldo awal
                  </button>
                )
              }
            />
            <dl className="grid grid-cols-1 gap-x-6 gap-y-3 px-4 pb-5 text-body sm:grid-cols-3 sm:px-5">
              <div>
                <dt className="text-label font-medium text-gray-600">Saldo awal</dt>
                <dd className="text-h2 font-semibold tabular-nums text-gray-900">
                  {master.solvent_opening.amount === null ? '–' : `${formatNumber(master.solvent_opening.amount)} kg`}
                </dd>
                {master.solvent_opening.date && (
                  <dd className="text-caption text-gray-600">
                    per {formatDate(master.solvent_opening.date)}
                    {master.solvent_opening.set_by && ` · ${master.solvent_opening.set_by}`}
                  </dd>
                )}
              </div>
              <div>
                <dt className="text-label font-medium text-gray-600">Saldo sekarang</dt>
                <dd className="text-h2 font-semibold tabular-nums text-gray-900">{formatNumber(master.solvent_opening.current_balance)} kg</dd>
              </div>
              <div>
                <dt className="text-label font-medium text-gray-600">Mutasi sejak saldo awal</dt>
                <dd className="text-h2 font-semibold tabular-nums text-gray-900">{master.solvent_opening.mutation_count}</dd>
                {master.solvent_opening.first_mutation_date && (
                  <dd className="text-caption text-gray-600">pertama {formatDate(master.solvent_opening.first_mutation_date)}</dd>
                )}
              </div>
            </dl>
          </section>

          <section aria-labelledby="master-shift" className="card">
            <SectionHeader
              id="master-shift"
              title="Shift"
              action={canEdit && <AddButton noun="shift" onClick={() => open('shift')} />}
            />
            <Table caption="Daftar shift">
              <thead>
                <tr>
                  <Th>Kode</Th>
                  <Th>Nama</Th>
                  <Th>Jam</Th>
                  <Th>Status</Th>
                  {canEdit && <Th><span className="sr-only">Aksi</span></Th>}
                </tr>
              </thead>
              <tbody>
                {master.shifts.map((s) => (
                  <tr key={s.id}>
                    <Td className="whitespace-nowrap font-mono text-gray-900">{s.code}</Td>
                    <Td>{s.name}</Td>
                    <Td className="whitespace-nowrap tabular-nums">
                      {s.start_time.replace(':', '.')}–{s.end_time.replace(':', '.')}
                    </Td>
                    <Td>
                      <ActiveBadge active={s.is_active} />
                    </Td>
                    {canEdit && (
                      <Td className="text-right">
                        <div className="flex justify-end gap-2">
                          <EditButton label={`Ubah ${s.name}`} onClick={() => open('shift', s)} />
                          <DeleteButton label={`Hapus ${s.name}`} onClick={() => setDeleting({ kind: 'shift', item: s })} />
                        </div>
                      </Td>
                    )}
                  </tr>
                ))}
              </tbody>
            </Table>
          </section>

          <section aria-labelledby="master-vendor" className="card">
            <SectionHeader
              id="master-vendor"
              title="Vendor"
              action={canEdit && <AddButton noun="vendor" onClick={() => open('vendor')} />}
            />
            <Table caption="Daftar vendor">
              <thead>
                <tr>
                  <Th>Nama</Th>
                  <Th>PIC dan kontak</Th>
                  <Th>Alamat</Th>
                  <Th>Status</Th>
                  {canEdit && <Th><span className="sr-only">Aksi</span></Th>}
                </tr>
              </thead>
              <tbody>
                {master.vendors.map((v) => (
                  <tr key={v.id}>
                    <Td className="font-medium text-gray-900">{v.name}</Td>
                    <Td>
                      {v.pic_name ?? <span className="text-gray-600">–</span>}
                      {v.contact && <span className="block text-caption text-gray-600">{v.contact}</span>}
                    </Td>
                    <Td className="min-w-[12rem] text-body">{v.address ?? <span className="text-gray-600">–</span>}</Td>
                    <Td>
                      <ActiveBadge active={v.is_active} />
                    </Td>
                    {canEdit && (
                      <Td className="text-right">
                        <div className="flex justify-end gap-2">
                          <EditButton label={`Ubah ${v.name}`} onClick={() => open('vendor', v)} />
                          <DeleteButton label={`Hapus ${v.name}`} onClick={() => setDeleting({ kind: 'vendor', item: v })} />
                        </div>
                      </Td>
                    )}
                  </tr>
                ))}
              </tbody>
            </Table>
          </section>
        </div>
      )}

      {openingOpen && (
        <OpeningDialog
          opening={master.solvent_opening}
          onClose={() => setOpeningOpen(false)}
          onSaved={(msg, next) => {
            setOpeningOpen(false);
            setMessage(msg);
            setSaved(next);
          }}
        />
      )}
      {deleting && (
        <DeleteDialog
          kind={deleting.kind}
          item={deleting.item}
          onClose={() => setDeleting(null)}
          onDeleted={(msg, next) => {
            setDeleting(null);
            setMessage(msg);
            setSaved(next);
          }}
        />
      )}
      {editing && (
        <EditDialog
          kind={editing.kind}
          item={editing.item}
          onClose={() => setEditing(null)}
          onSaved={(msg, next) => {
            setEditing(null);
            setMessage(msg);
            setSaved(next);
          }}
        />
      )}
    </>
  );
}
