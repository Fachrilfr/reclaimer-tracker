import { useState } from 'react';
import { LoaderCircle, Pencil, Plus } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { fieldErrorsFrom, useResource } from '../lib/useResource.js';
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
  },
  material: {
    noun: 'bahan',
    path: '/master/materials',
    canCreate: false,
    canToggle: false,
    fields: [
      { key: 'name', label: 'Nama bahan', required: true },
      { key: 'unit', label: 'Satuan', required: true, hint: 'Contoh kg.' },
    ],
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
  const master = saved ?? data;

  const open = (kind, item) => {
    setMessage('');
    setEditing({ kind, item: item ?? { ...KINDS[kind].empty } });
  };

  return (
    <>
      <PageHeader
        title="Data master"
        description="Shift, bahan, dan vendor yang dipilih di form. Data yang sudah dipakai tidak dihapus, cukup dinonaktifkan."
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
          <section aria-labelledby="master-shift" className="card">
            <SectionHeader
              id="master-shift"
              title="Shift"
              description="Shift nonaktif tidak muncul di Input reclaimer dan Pemakaian solvent."
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
                        <EditButton label={`Ubah ${s.name}`} onClick={() => open('shift', s)} />
                      </Td>
                    )}
                  </tr>
                ))}
              </tbody>
            </Table>
          </section>

          <section aria-labelledby="master-bahan" className="card">
            <SectionHeader
              id="master-bahan"
              title="Bahan"
              description="Kode dan jenis bahan dipakai perhitungan material balance, jadi hanya nama dan satuan yang bisa diubah."
            />
            <Table caption="Daftar bahan">
              <thead>
                <tr>
                  <Th>Kode</Th>
                  <Th>Nama</Th>
                  <Th>Jenis</Th>
                  <Th>Satuan</Th>
                  {canEdit && <Th><span className="sr-only">Aksi</span></Th>}
                </tr>
              </thead>
              <tbody>
                {master.materials.map((m) => (
                  <tr key={m.id}>
                    <Td className="whitespace-nowrap font-mono text-gray-900">{m.code}</Td>
                    <Td>{m.name}</Td>
                    <Td>{m.type}</Td>
                    <Td>{m.unit}</Td>
                    {canEdit && (
                      <Td className="text-right">
                        <EditButton label={`Ubah ${m.name}`} onClick={() => open('material', m)} />
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
              description="Penerima residu di surat jalan. Vendor nonaktif tidak bisa dipilih di surat jalan baru."
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
                        <EditButton label={`Ubah ${v.name}`} onClick={() => open('vendor', v)} />
                      </Td>
                    )}
                  </tr>
                ))}
              </tbody>
            </Table>
          </section>
        </div>
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
