import { useState } from 'react';
import { Recycle } from 'lucide-react';
import { formatDate, formatNumber, parseDecimal } from '../../lib/format.js';
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
  UnitInput,
  changeList,
  kg,
  numberError,
  signedKg,
  toInput,
  useCorrectionList,
  useSubmit,
} from './parts.jsx';

const STATUS_LABEL = { completed: 'Selesai', in_progress: 'Belum selesai' };
const KNOWN = ['date', 'shift_id', 'regu', 'process_status', 'recovery_weight', 'oil_temp', 'vapour_temp', 'notes', 'reason'];

function BatchDialog({ batch, meta, onClose, onDone }) {
  const [form, setForm] = useState({
    date: batch.date,
    shift_id: String(batch.shift_id),
    regu: batch.regu ?? '',
    process_status: batch.process_status,
    recovery_weight: toInput(batch.recovery_weight),
    oil_temp: toInput(batch.oil_temp),
    vapour_temp: toInput(batch.vapour_temp),
    notes: batch.notes ?? '',
    reason: '',
  });
  const { errors, formError, saving, submit, clear } = useSubmit(onDone, KNOWN);
  const set = (key) => (e) => {
    setForm((f) => ({ ...f, [key]: e.target.value }));
    clear(key);
  };

  const shifts = meta.shifts.filter((s) => s.is_active || s.id === batch.shift_id);
  const shiftName = (id) => meta.shifts.find((s) => String(s.id) === String(id))?.name ?? '–';
  const after = {
    ...form,
    recovery_weight: parseDecimal(form.recovery_weight),
    oil_temp: parseDecimal(form.oil_temp),
    vapour_temp: parseDecimal(form.vapour_temp),
  };
  const before = { ...batch, shift_id: String(batch.shift_id) };
  const changes = changeList(
    [
      { key: 'date', label: 'Tanggal', show: formatDate },
      { key: 'shift_id', label: 'Shift', show: shiftName },
      { key: 'regu', label: 'Regu' },
      { key: 'process_status', label: 'Status proses', show: (v) => STATUS_LABEL[v] },
      { key: 'recovery_weight', label: 'Hasil solvent', show: kg },
      { key: 'oil_temp', label: 'Suhu oli', show: (v) => `${formatNumber(v, 1)} °C` },
      { key: 'vapour_temp', label: 'Suhu uap', show: (v) => `${formatNumber(v, 1)} °C` },
      { key: 'notes', label: 'Keterangan' },
    ],
    before,
    after,
  );
  const delta = Number.isFinite(after.recovery_weight) ? Math.round((after.recovery_weight - batch.recovery_weight) * 100) / 100 : 0;

  const handleSubmit = (e) => {
    e.preventDefault();
    const local = {};
    ['recovery_weight', 'oil_temp', 'vapour_temp'].forEach((key) => {
      const message = numberError(form[key]);
      if (message) local[key] = message;
    });
    if (!form.date) local.date = 'Isi tanggal proses.';
    if (!form.reason.trim()) local.reason = REASON_REQUIRED;
    submit(
      `/koreksi/input/${batch.id}`,
      {
        method: 'PUT',
        body: {
          date: form.date,
          shift_id: Number(form.shift_id),
          regu: form.regu.trim() || null,
          process_status: form.process_status,
          recovery_weight: after.recovery_weight,
          oil_temp: after.oil_temp,
          vapour_temp: after.vapour_temp,
          notes: form.notes.trim() || null,
          reason: form.reason.trim(),
        },
      },
      local,
    );
  };

  return (
    <Modal wide title={`Koreksi input ${batch.batch_number}`} onClose={() => !saving && onClose()}>
      <Summary
        items={[
          ['Operator', batch.operator],
          ['Jenis input', batch.is_continuation ? `Lanjutan dari ${batch.parent_batch_number}` : 'Input baru'],
          ['Tinta X masuk', `${kg(batch.ink_weight)}, tetap dan tidak bisa dikoreksi`],
        ]}
      />
      <form onSubmit={handleSubmit} noValidate className="mt-4 space-y-4">
        {formError && <Alert tone="danger">{formError}</Alert>}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field id="koreksi-tanggal" label="Tanggal" required error={errors.date}>
            {(props) => <input {...props} type="date" max={meta.today} value={form.date} onChange={set('date')} className="field" />}
          </Field>
          <Field id="koreksi-shift" label="Shift" required error={errors.shift_id}>
            {(props) => (
              <select {...props} value={form.shift_id} onChange={set('shift_id')} className="field">
                {shifts.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name}
                  </option>
                ))}
              </select>
            )}
          </Field>
          <Field id="koreksi-regu" label="Regu" hint="Opsional." error={errors.regu}>
            {(props) => <input {...props} type="text" maxLength={20} value={form.regu} onChange={set('regu')} className="field" />}
          </Field>
          <Field
            id="koreksi-status"
            label="Status proses"
            required
            hint={batch.is_last ? undefined : `Hanya bisa diubah di input terakhir proses ini (${batch.last_batch_number}).`}
            error={errors.process_status}
          >
            {(props) => (
              <select {...props} value={form.process_status} onChange={set('process_status')} disabled={!batch.is_last} className="field">
                <option value="completed">Selesai</option>
                <option value="in_progress">Belum selesai, dilanjutkan shift berikutnya</option>
              </select>
            )}
          </Field>
          <Field id="koreksi-hasil" label="Hasil solvent" required error={errors.recovery_weight}>
            {(props) => <UnitInput {...props} unit="kg" value={form.recovery_weight} onChange={set('recovery_weight')} />}
          </Field>
          <div className="grid grid-cols-2 gap-4">
            <Field id="koreksi-oli" label="Suhu oli" required error={errors.oil_temp}>
              {(props) => <UnitInput {...props} unit="°C" value={form.oil_temp} onChange={set('oil_temp')} />}
            </Field>
            <Field id="koreksi-uap" label="Suhu uap" required error={errors.vapour_temp}>
              {(props) => <UnitInput {...props} unit="°C" value={form.vapour_temp} onChange={set('vapour_temp')} />}
            </Field>
          </div>
        </div>
        <Field id="koreksi-keterangan" label="Keterangan" hint="Opsional." error={errors.notes}>
          {(props) => <textarea {...props} rows={2} maxLength={500} value={form.notes} onChange={set('notes')} className="field h-auto py-2" />}
        </Field>
        <ChangePreview
          items={changes}
          note={
            delta
              ? `Saldo solvent disesuaikan ${signedKg(delta)} lewat baris penyesuaian. Saldo yang tercatat di input lama tidak diubah. Residu dan persentase solvent proses ini dihitung ulang.`
              : null
          }
        />
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

function deleteConsequence(batch) {
  const parts = [];
  if (batch.recovery_weight > 0) parts.push(`Hasil solvent ${kg(batch.recovery_weight)} dikeluarkan dari saldo lewat baris penyesuaian.`);
  if (batch.is_continuation && batch.process_status === 'completed') {
    parts.push(`Proses dari ${batch.parent_batch_number} akan dibuka lagi dan menunggu input lanjutan.`);
  }
  if (!batch.is_continuation) parts.push('Proses ini hilang dari dashboard dan laporan.');
  return parts.join(' ');
}

export default function BatchTab({ onMessage }) {
  const state = useCorrectionList('/koreksi/input');
  const [dialog, setDialog] = useState(null);

  const done = (message) => {
    setDialog(null);
    onMessage(message);
    state.list.reload();
  };

  return (
    <section aria-label="Input reclaimer" className="card">
      <FilterBar state={state} placeholder="No. input, keterangan, regu, operator" />
      <ListBody state={state} icon={Recycle} emptyTitle="Belum ada input reclaimer.">
        {(rows) => (
          <Table caption="Input reclaimer">
            <thead>
              <tr>
                <Th>No. input</Th>
                <Th>Tanggal dan shift</Th>
                <Th numeric>Tinta X</Th>
                <Th numeric>Hasil solvent</Th>
                <Th numeric>Suhu oli / uap</Th>
                <Th>Status</Th>
                <Th>Keterangan</Th>
                <Th className="text-right">Tindakan</Th>
              </tr>
            </thead>
            <tbody>
              {rows.map((b) => (
                <tr key={b.id}>
                  <Td>
                    <span className="whitespace-nowrap font-mono text-gray-900">{b.batch_number}</span>
                    {b.is_continuation && (
                      <span className="block text-caption text-gray-600">
                        Lanjutan dari <span className="whitespace-nowrap font-mono">{b.parent_batch_number}</span>
                      </span>
                    )}
                    <span className="block text-caption text-gray-600">{b.operator}</span>
                  </Td>
                  <Td className="whitespace-nowrap">
                    {formatDate(b.date)}
                    <span className="block text-caption text-gray-600">
                      {b.shift}
                      {b.regu && ` · Regu ${b.regu}`}
                    </span>
                  </Td>
                  <Td numeric className="whitespace-nowrap">
                    {kg(b.ink_weight)}
                  </Td>
                  <Td numeric className="whitespace-nowrap">
                    {kg(b.recovery_weight)}
                  </Td>
                  <Td numeric className="whitespace-nowrap">
                    {formatNumber(b.oil_temp, 1)} / {formatNumber(b.vapour_temp, 1)} °C
                  </Td>
                  <Td className="whitespace-nowrap">{STATUS_LABEL[b.process_status]}</Td>
                  <Td className="min-w-[12rem] text-caption">
                    {b.notes && <span className="block text-gray-800">{b.notes}</span>}
                    <CorrectedBadge correction={b.correction} className={b.notes ? 'mt-1' : ''} />
                  </Td>
                  <Td>
                    <RowActions label={b.batch_number} onEdit={() => setDialog({ type: 'edit', row: b })} onDelete={() => setDialog({ type: 'delete', row: b })} />
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </ListBody>

      {dialog?.type === 'edit' && <BatchDialog batch={dialog.row} meta={state.list.meta} onClose={() => setDialog(null)} onDone={done} />}
      {dialog?.type === 'delete' && (
        <DeleteDialog
          title={`Hapus input ${dialog.row.batch_number}?`}
          path={`/koreksi/input/${dialog.row.id}`}
          blocked={
            dialog.row.is_last
              ? null
              : `Input ini masih punya input lanjutan. Hapus dulu input terakhir proses ini (${dialog.row.last_batch_number}).`
          }
          consequence={deleteConsequence(dialog.row)}
          onClose={() => setDialog(null)}
          onDone={done}
        >
          <Summary
            items={[
              ['Tanggal dan shift', `${formatDate(dialog.row.date)}, ${dialog.row.shift}`],
              ['Operator', dialog.row.operator],
              ['Tinta X', kg(dialog.row.ink_weight)],
              ['Hasil solvent', kg(dialog.row.recovery_weight)],
            ]}
          />
        </DeleteDialog>
      )}
    </section>
  );
}
