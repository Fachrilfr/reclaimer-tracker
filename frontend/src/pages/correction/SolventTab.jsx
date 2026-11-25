import { useState } from 'react';
import { Droplets } from 'lucide-react';
import { formatDate, parseDecimal } from '../../lib/format.js';
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

const KNOWN = ['date', 'shift_id', 'weight', 'purpose', 'regu', 'reason'];

function UsageDialog({ usage, meta, onClose, onDone }) {
  const [form, setForm] = useState({
    date: usage.date,
    shift_id: String(usage.shift_id),
    weight: toInput(usage.weight),
    purpose: usage.purpose,
    regu: usage.regu ?? '',
    reason: '',
  });
  const { errors, formError, saving, submit, clear } = useSubmit(onDone, KNOWN);
  const set = (key) => (e) => {
    setForm((f) => ({ ...f, [key]: e.target.value }));
    clear(key);
  };

  const shifts = meta.shifts.filter((s) => s.is_active || s.id === usage.shift_id);
  const shiftName = (id) => meta.shifts.find((s) => String(s.id) === String(id))?.name ?? '–';
  const weight = parseDecimal(form.weight);
  const changes = changeList(
    [
      { key: 'date', label: 'Tanggal', show: formatDate },
      { key: 'shift_id', label: 'Shift', show: shiftName },
      { key: 'weight', label: 'Berat solvent', show: kg },
      { key: 'purpose', label: 'Keperluan' },
      { key: 'regu', label: 'Regu' },
    ],
    { ...usage, shift_id: String(usage.shift_id) },
    { ...form, weight },
  );
  // Pemakaian berkurang: solvent kembali ke saldo; bertambah: saldo berkurang
  const delta = Number.isFinite(weight) ? Math.round((usage.weight - weight) * 100) / 100 : 0;

  const handleSubmit = (e) => {
    e.preventDefault();
    const local = {};
    const weightError = numberError(form.weight) ?? (weight <= 0 ? 'Berat solvent harus lebih dari 0 kg.' : null);
    if (weightError) local.weight = weightError;
    if (!form.date) local.date = 'Isi tanggal pemakaian.';
    if (!form.purpose.trim()) local.purpose = 'Isi keperluan pemakaian solvent, contoh: pencucian mesin cetak.';
    if (!form.reason.trim()) local.reason = REASON_REQUIRED;
    submit(
      `/koreksi/solvent/${usage.id}`,
      {
        method: 'PUT',
        body: {
          date: form.date,
          shift_id: Number(form.shift_id),
          weight,
          purpose: form.purpose.trim(),
          regu: form.regu,
          reason: form.reason.trim(),
        },
      },
      local,
    );
  };

  return (
    <Modal title="Koreksi pemakaian solvent" onClose={() => !saving && onClose()}>
      <Summary
        items={[
          ['Dicatat oleh', usage.used_by],
          ['Saldo solvent sekarang', kg(meta.balance)],
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
        </div>
        <Field id="koreksi-berat" label="Berat solvent yang diambil" required error={errors.weight}>
          {(props) => <UnitInput {...props} unit="kg" value={form.weight} onChange={set('weight')} />}
        </Field>
        <Field id="koreksi-keperluan" label="Keperluan" required error={errors.purpose}>
          {(props) => <input {...props} type="text" maxLength={150} value={form.purpose} onChange={set('purpose')} className="field" />}
        </Field>
        <Field id="regu" label="Regu" required error={errors.regu}>
            {(props) => (<select {...props} value={form.regu} onChange={set('regu')} className="field">
                  <option value="">Pilih regu</option>
                  {['A', 'B', 'C'].map((regu) => <option key={regu}>{regu}</option>)}
                </select>)}
          </Field>
        <ChangePreview
          items={changes}
          note={delta ? `Saldo solvent disesuaikan ${signedKg(delta)} lewat baris penyesuaian. Saldo yang tercatat di pemakaian lama tidak diubah.` : null}
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

export default function SolventTab({ onMessage }) {
  const state = useCorrectionList('/koreksi/solvent');
  const [dialog, setDialog] = useState(null);

  const done = (message) => {
    setDialog(null);
    onMessage(message);
    state.list.reload();
  };

  return (
    <section aria-label="Pemakaian solvent" className="card">
      <FilterBar state={state} placeholder="Keperluan, catatan, pencatat" />
      <ListBody state={state} icon={Droplets} emptyTitle="Belum ada pemakaian solvent.">
        {(rows) => (
          <Table caption="Pemakaian solvent">
            <thead>
              <tr>
                <Th>Tanggal dan shift</Th>
                <Th numeric>Berat</Th>
                <Th>Keperluan</Th>
                <Th>Dicatat oleh</Th>
                <Th>Catatan</Th>
                <Th className="text-right">Tindakan</Th>
              </tr>
            </thead>
            <tbody>
              {rows.map((u) => (
                <tr key={u.id}>
                  <Td className="whitespace-nowrap">
                    {formatDate(u.date)}
                    <span className="block text-caption text-gray-600">{u.shift}{u.regu && ' ? Regu ' + u.regu}</span>
                  </Td>
                  <Td numeric className="whitespace-nowrap">
                    {kg(u.weight)}
                  </Td>
                  <Td className="min-w-[10rem]">{u.purpose}</Td>
                  <Td className="whitespace-nowrap">{u.used_by}</Td>
                  <Td className="min-w-[12rem] text-caption">
                    {u.notes && <span className="block text-gray-800">{u.notes}</span>}
                    <CorrectedBadge correction={u.correction} className={u.notes ? 'mt-1' : ''} />
                  </Td>
                  <Td>
                    <RowActions
                      label={`pemakaian ${formatDate(u.date)}`}
                      onEdit={() => setDialog({ type: 'edit', row: u })}
                      onDelete={() => setDialog({ type: 'delete', row: u })}
                    />
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </ListBody>

      {dialog?.type === 'edit' && <UsageDialog usage={dialog.row} meta={state.list.meta} onClose={() => setDialog(null)} onDone={done} />}
      {dialog?.type === 'delete' && (
        <DeleteDialog
          title="Hapus pemakaian solvent ini?"
          path={`/koreksi/solvent/${dialog.row.id}`}
          consequence={`Solvent ${kg(dialog.row.weight)} kembali ke saldo lewat baris penyesuaian.`}
          onClose={() => setDialog(null)}
          onDone={done}
        >
          <Summary
            items={[
              ['Tanggal dan shift', `${formatDate(dialog.row.date)}, ${dialog.row.shift}`],
              ['Berat', kg(dialog.row.weight)],
              ['Keperluan', dialog.row.purpose],
              ['Dicatat oleh', dialog.row.used_by],
            ]}
          />
        </DeleteDialog>
      )}
    </section>
  );
}
