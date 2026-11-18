import { useState } from 'react';
import { Cylinder } from 'lucide-react';
import { formatDate } from '../../lib/format.js';
import Alert from '../../components/Alert.jsx';
import CorrectedBadge from '../../components/CorrectedBadge.jsx';
import Field from '../../components/Field.jsx';
import Modal from '../../components/Modal.jsx';
import { Table, Td, Th } from '../../components/Table.jsx';
import {
  ChangePreview,
  DeleteDialog,
  DialogFooter,
  FilterBar,
  ListBody,
  REASON_REQUIRED,
  ReasonField,
  RowActions,
  Summary,
  changeList,
  useCorrectionList,
  useSubmit,
} from './parts.jsx';

const STATUS_LABEL = { filling: 'Masih diisi', full: 'Penuh, di TPS', transported: 'Diangkut vendor' };
const KNOWN = ['status', 'start_date', 'full_date', 'drum_count', 'notes', 'reason'];

function addDays(iso, days) {
  const [y, m, d] = iso.split('-').map(Number);
  const date = new Date(y, m - 1, d + days);
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function daysBetween(fromIso, toIso) {
  const [a, b] = [fromIso, toIso].map((iso) => {
    const [y, m, d] = iso.split('-').map(Number);
    return Date.UTC(y, m - 1, d);
  });
  return Math.round((b - a) / 86400000);
}

function PalletDialog({ pallet, meta, onClose, onDone }) {
  const transported = pallet.status === 'transported';
  const [form, setForm] = useState({
    status: pallet.status === 'filling' ? 'filling' : 'full',
    start_date: pallet.start_date,
    full_date: pallet.full_date ?? pallet.sent_to_tps_at ?? '',
    drum_count: String(pallet.status === 'filling' ? meta.drums_per_pallet : pallet.drum_count),
    notes: pallet.notes ?? '',
    reason: '',
  });
  const { errors, formError, saving, submit, clear } = useSubmit(onDone, KNOWN);
  const set = (key) => (e) => {
    setForm((f) => ({ ...f, [key]: e.target.value }));
    clear(key);
  };
  const full = transported || form.status === 'full';

  const before = {
    status: pallet.status,
    start_date: pallet.start_date,
    full_date: pallet.full_date ?? pallet.sent_to_tps_at,
    drum_count: pallet.status === 'filling' ? null : pallet.drum_count,
    notes: pallet.notes,
  };
  const after = {
    status: transported ? 'transported' : form.status,
    start_date: form.start_date,
    full_date: full ? form.full_date : null,
    drum_count: full ? Number(form.drum_count) : null,
    notes: form.notes,
  };
  const changes = changeList(
    [
      { key: 'status', label: 'Status', show: (v) => STATUS_LABEL[v] },
      { key: 'start_date', label: 'Mulai diisi', show: formatDate },
      { key: 'full_date', label: 'Penuh dan sampai di TPS', show: formatDate },
      { key: 'drum_count', label: 'Jumlah drum', show: (v) => (v === null ? '–' : `${v} drum`) },
      { key: 'notes', label: 'Keterangan' },
    ],
    before,
    after,
  );
  // Batas simpan pallet ini tetap memakai lama simpan saat pallet dibuat
  const maxDays = pallet.expiry_date && before.full_date ? daysBetween(before.full_date, pallet.expiry_date) : meta.max_storage_days;
  const newExpiry = full && form.full_date && form.full_date !== before.full_date ? addDays(form.full_date, maxDays) : null;

  const handleSubmit = (e) => {
    e.preventDefault();
    const local = {};
    if (!form.start_date) local.start_date = 'Isi tanggal mulai diisi.';
    if (full && !form.full_date) local.full_date = 'Isi tanggal pallet penuh dan sampai di TPS.';
    if (full && !(Number(form.drum_count) >= 1)) local.drum_count = 'Isi jumlah drum di pallet ini, paling sedikit 1.';
    if (!form.reason.trim()) local.reason = REASON_REQUIRED;
    submit(
      `/koreksi/pallet/${pallet.id}`,
      {
        method: 'PUT',
        body: {
          status: transported ? null : form.status,
          start_date: form.start_date,
          full_date: full ? form.full_date : null,
          drum_count: full ? Number(form.drum_count) : 0,
          notes: form.notes.trim() || null,
          reason: form.reason.trim(),
        },
      },
      local,
    );
  };

  return (
    <Modal title={`Koreksi pallet ${pallet.pallet_number}`} onClose={() => !saving && onClose()}>
      {transported && (
        <Summary
          items={[
            ['Diangkut dengan surat jalan', pallet.surat_jalan_number],
            ['Tanggal serah terima', formatDate(pallet.handover_date)],
          ]}
        />
      )}
      <form onSubmit={handleSubmit} noValidate className="mt-4 space-y-4">
        {formError && <Alert tone="danger">{formError}</Alert>}
        {!transported && (
          <Field
            id="koreksi-status"
            label="Status"
            required
            hint="Pilih Masih diisi bila tombol Pallet penuh keliru ditekan."
            error={errors.status}
          >
            {(props) => (
              <select {...props} value={form.status} onChange={set('status')} className="field">
                <option value="filling">Masih diisi di area reclaimer</option>
                <option value="full">Penuh, sudah sampai di TPS</option>
              </select>
            )}
          </Field>
        )}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field id="koreksi-mulai" label="Tanggal mulai diisi" required error={errors.start_date}>
            {(props) => <input {...props} type="date" max={meta.today} value={form.start_date} onChange={set('start_date')} className="field" />}
          </Field>
          {full && (
            <Field id="koreksi-penuh" label="Tanggal penuh dan sampai di TPS" required error={errors.full_date}>
              {(props) => (
                <input
                  {...props}
                  type="date"
                  min={form.start_date}
                  max={transported ? pallet.handover_date : meta.today}
                  value={form.full_date}
                  onChange={set('full_date')}
                  className="field"
                />
              )}
            </Field>
          )}
          {full && (
            <Field
              id="koreksi-drum"
              label="Jumlah drum"
              required
              hint={`Aturan: 1 pallet berisi tepat ${meta.drums_per_pallet} drum. Ubah hanya bila isi pallet sebenarnya berbeda.`}
              error={errors.drum_count}
            >
              {(props) => (
                <input {...props} type="number" min={1} max={20} step={1} value={form.drum_count} onChange={set('drum_count')} className="field tabular-nums" />
              )}
            </Field>
          )}
        </div>
        <Field id="koreksi-keterangan" label="Keterangan" hint="Opsional." error={errors.notes}>
          {(props) => <textarea {...props} rows={2} maxLength={500} value={form.notes} onChange={set('notes')} className="field h-auto py-2" />}
        </Field>
        <ChangePreview items={changes} note={newExpiry ? `Batas simpan dihitung ulang menjadi ${formatDate(newExpiry)}.` : null} />
        <ReasonField
          id="koreksi-alasan"
          value={form.reason}
          onChange={(value) => {
            setForm((f) => ({ ...f, reason: value }));
            clear('reason');
          }}
          error={errors.reason}
        />
        <DialogFooter onClose={onClose} saving={saving} label="Simpan koreksi" />
      </form>
    </Modal>
  );
}

export default function PalletTab({ onMessage }) {
  const state = useCorrectionList('/koreksi/pallet');
  const [dialog, setDialog] = useState(null);

  const done = (message) => {
    setDialog(null);
    onMessage(message);
    state.list.reload();
  };

  return (
    <section aria-label="Pallet residu" className="card">
      <FilterBar state={state} placeholder="No. pallet, no. surat jalan, keterangan" />
      <ListBody state={state} icon={Cylinder} emptyTitle="Belum ada pallet residu.">
        {(rows, meta) => (
          <Table caption="Pallet residu">
            <thead>
              <tr>
                <Th>No. pallet</Th>
                <Th>Status</Th>
                <Th>Mulai diisi</Th>
                <Th>Penuh, sampai di TPS</Th>
                <Th numeric>Drum</Th>
                <Th>Keterangan</Th>
                <Th className="text-right">Tindakan</Th>
              </tr>
            </thead>
            <tbody>
              {rows.map((p) => (
                <tr key={p.id}>
                  <Td className="whitespace-nowrap font-mono text-gray-900">{p.pallet_number}</Td>
                  <Td className="whitespace-nowrap">
                    {STATUS_LABEL[p.status]}
                    {p.surat_jalan_number && <span className="block font-mono text-caption text-gray-600">{p.surat_jalan_number}</span>}
                  </Td>
                  <Td className="whitespace-nowrap">{formatDate(p.start_date)}</Td>
                  <Td className="whitespace-nowrap">{formatDate(p.sent_to_tps_at)}</Td>
                  <Td numeric className={p.status !== 'filling' && p.drum_count !== meta.drums_per_pallet ? 'font-semibold text-warning-fg' : ''}>
                    {p.status === 'filling' ? '–' : p.drum_count}
                  </Td>
                  <Td className="min-w-[12rem] text-caption">
                    {p.notes && <span className="block whitespace-pre-line text-gray-800">{p.notes}</span>}
                    <CorrectedBadge correction={p.correction} className={p.notes ? 'mt-1' : ''} />
                  </Td>
                  <Td>
                    <RowActions label={p.pallet_number} onEdit={() => setDialog({ type: 'edit', row: p })} onDelete={() => setDialog({ type: 'delete', row: p })} />
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </ListBody>

      {dialog?.type === 'edit' && <PalletDialog pallet={dialog.row} meta={state.list.meta} onClose={() => setDialog(null)} onDone={done} />}
      {dialog?.type === 'delete' && (
        <DeleteDialog
          title={`Hapus pallet ${dialog.row.pallet_number}?`}
          path={`/koreksi/pallet/${dialog.row.id}`}
          blocked={
            dialog.row.status === 'transported'
              ? `Pallet ini sudah diangkut dengan surat jalan ${dialog.row.surat_jalan_number}. Keluarkan pallet dari surat jalan itu dulu lewat tab Surat jalan.`
              : null
          }
          consequence="Pallet hilang dari daftar residu, stok TPS, dan laporan."
          onClose={() => setDialog(null)}
          onDone={done}
        >
          <Summary
            items={[
              ['Status', STATUS_LABEL[dialog.row.status]],
              ['Mulai diisi', formatDate(dialog.row.start_date)],
              ['Sampai di TPS', formatDate(dialog.row.sent_to_tps_at)],
            ]}
          />
        </DeleteDialog>
      )}
    </section>
  );
}
