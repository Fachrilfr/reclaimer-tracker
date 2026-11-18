import { useState } from 'react';
import { Truck } from 'lucide-react';
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
  toInput,
  useCorrectionList,
  useSubmit,
} from './parts.jsx';

const KNOWN = ['surat_jalan_number', 'vendor_id', 'handover_date', 'weight_kg', 'vendor_officer_name', 'notes', 'pallet_ids', 'file', 'reason'];
const ACCEPT = '.pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png';
const MAX_FILE_MB = 10;

function SuratJalanDialog({ suratJalan, meta, onClose, onDone }) {
  const [form, setForm] = useState({
    surat_jalan_number: suratJalan.number,
    vendor_id: String(suratJalan.vendor_id),
    handover_date: suratJalan.handover_date,
    weight_kg: toInput(suratJalan.weight_kg),
    vendor_officer_name: suratJalan.vendor_officer_name,
    notes: suratJalan.notes ?? '',
    pallet_ids: suratJalan.pallets.map((p) => p.id),
    reason: '',
  });
  const [file, setFile] = useState(null);
  const { errors, formError, saving, submit, clear } = useSubmit(onDone, KNOWN);
  const set = (key) => (e) => {
    setForm((f) => ({ ...f, [key]: e.target.value }));
    clear(key);
  };
  const toggle = (id) => {
    setForm((f) => ({ ...f, pallet_ids: f.pallet_ids.includes(id) ? f.pallet_ids.filter((x) => x !== id) : [...f.pallet_ids, id] }));
    clear('pallet_ids');
  };

  // Pallet di surat jalan ini, lalu pallet lain yang sedang di TPS
  const own = suratJalan.pallets.map((p) => ({ id: p.id, pallet_number: p.pallet_number, note: 'Di surat jalan ini', tps: suratJalan.pallet_tps_dates[p.id] }));
  const others = meta.available_pallets
    .filter((p) => !own.some((o) => o.id === p.id))
    .map((p) => ({ id: p.id, pallet_number: p.pallet_number, note: `Di TPS sejak ${formatDate(p.sent_to_tps_at)}`, tps: p.sent_to_tps_at }));
  const options = [...own, ...others];
  const vendors = meta.vendors.filter((v) => v.selectable || v.id === suratJalan.vendor_id);
  const vendorName = (id) => meta.vendors.find((v) => String(v.id) === String(id))?.name ?? '–';
  const palletNames = (ids) =>
    options
      .filter((p) => ids.includes(p.id))
      .map((p) => p.pallet_number)
      .sort()
      .join(', ') || '–';
  const removed = own.filter((p) => !form.pallet_ids.includes(p.id));

  const changes = changeList(
    [
      { key: 'surat_jalan_number', label: 'No. surat jalan' },
      { key: 'vendor_id', label: 'Vendor', show: vendorName },
      { key: 'handover_date', label: 'Tanggal serah terima', show: formatDate },
      { key: 'weight_kg', label: 'Berat timbangan vendor', show: kg },
      { key: 'vendor_officer_name', label: 'Petugas vendor' },
      { key: 'notes', label: 'Catatan' },
      { key: 'pallets', label: 'Pallet' },
      { key: 'file', label: 'Berkas bukti' },
    ],
    {
      ...suratJalan,
      surat_jalan_number: suratJalan.number,
      vendor_id: String(suratJalan.vendor_id),
      pallets: palletNames(suratJalan.pallets.map((p) => p.id)),
      file: suratJalan.file_name,
    },
    { ...form, weight_kg: parseDecimal(form.weight_kg), pallets: palletNames(form.pallet_ids), file: file ? file.name : suratJalan.file_name },
  );

  const handleSubmit = (e) => {
    e.preventDefault();
    const local = {};
    if (!form.surat_jalan_number.trim()) local.surat_jalan_number = 'Isi nomor surat jalan.';
    if (!form.handover_date) local.handover_date = 'Isi tanggal serah terima.';
    const weightError = numberError(form.weight_kg) ?? (parseDecimal(form.weight_kg) <= 0 ? 'Berat harus lebih dari 0 kg.' : null);
    if (weightError) local.weight_kg = weightError;
    if (!form.vendor_officer_name.trim()) local.vendor_officer_name = 'Isi nama petugas vendor yang menerima pallet.';
    if (form.pallet_ids.length < meta.min_pallets) local.pallet_ids = `Pilih minimal ${meta.min_pallets} pallet yang diangkut.`;
    if (file && file.size > MAX_FILE_MB * 1024 * 1024) local.file = `Ukuran bukti paling besar ${MAX_FILE_MB} MB.`;
    if (!form.reason.trim()) local.reason = REASON_REQUIRED;

    const body = new FormData();
    body.append('surat_jalan_number', form.surat_jalan_number.trim());
    body.append('vendor_id', form.vendor_id);
    body.append('handover_date', form.handover_date);
    body.append('weight_kg', String(parseDecimal(form.weight_kg)));
    body.append('vendor_officer_name', form.vendor_officer_name.trim());
    if (form.notes.trim()) body.append('notes', form.notes.trim());
    form.pallet_ids.forEach((id) => body.append('pallet_ids[]', id));
    if (file) body.append('file', file);
    body.append('reason', form.reason.trim());
    submit(`/koreksi/surat-jalan/${suratJalan.id}`, { method: 'POST', body }, local);
  };

  return (
    <Modal wide title={`Koreksi surat jalan ${suratJalan.number}`} onClose={() => !saving && onClose()}>
      <form onSubmit={handleSubmit} noValidate className="space-y-4">
        {formError && <Alert tone="danger">{formError}</Alert>}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field id="koreksi-nomor" label="Nomor surat jalan" required error={errors.surat_jalan_number}>
            {(props) => (
              <input {...props} type="text" maxLength={100} value={form.surat_jalan_number} onChange={set('surat_jalan_number')} className="field font-mono" />
            )}
          </Field>
          <Field id="koreksi-vendor" label="Vendor penerima" required error={errors.vendor_id}>
            {(props) => (
              <select {...props} value={form.vendor_id} onChange={set('vendor_id')} className="field">
                {vendors.map((v) => (
                  <option key={v.id} value={v.id}>
                    {v.name}
                    {v.selectable ? '' : ' (nonaktif)'}
                  </option>
                ))}
              </select>
            )}
          </Field>
          <Field id="koreksi-serah" label="Tanggal serah terima" required error={errors.handover_date}>
            {(props) => <input {...props} type="date" max={meta.today} value={form.handover_date} onChange={set('handover_date')} className="field" />}
          </Field>
          <Field id="koreksi-berat" label="Berat timbangan vendor" required error={errors.weight_kg}>
            {(props) => <UnitInput {...props} unit="kg" value={form.weight_kg} onChange={set('weight_kg')} />}
          </Field>
          <Field id="koreksi-petugas" label="Petugas vendor" required error={errors.vendor_officer_name}>
            {(props) => (
              <input {...props} type="text" maxLength={100} value={form.vendor_officer_name} onChange={set('vendor_officer_name')} className="field" />
            )}
          </Field>
          <Field
            id="koreksi-berkas"
            label="Ganti berkas bukti"
            hint={`Opsional. Kosongkan bila bukti tidak diganti. PDF, JPG, atau PNG, paling besar ${MAX_FILE_MB} MB.`}
            error={errors.file}
          >
            {(props) => (
              <input
                {...props}
                type="file"
                accept={ACCEPT}
                onChange={(e) => {
                  setFile(e.target.files?.[0] ?? null);
                  clear('file');
                }}
                className="block w-full text-body text-gray-800 file:mr-3 file:h-11 file:rounded-lg file:border file:border-gray-200 file:bg-white file:px-4 file:text-body file:font-medium file:text-gray-800 hover:file:bg-gray-25"
              />
            )}
          </Field>
        </div>

        <fieldset aria-describedby={errors.pallet_ids ? 'koreksi-pallet-error' : undefined}>
          <legend className="mb-1.5 text-label font-medium text-gray-800">
            Pallet yang diangkut <span className="text-danger-fg" aria-hidden="true">*</span>
          </legend>
          <div className="max-h-56 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2">
            {options.map((p) => (
              <label key={p.id} className="flex min-h-11 items-center gap-3 rounded-md px-2 hover:bg-gray-25">
                <input type="checkbox" checked={form.pallet_ids.includes(p.id)} onChange={() => toggle(p.id)} className="h-5 w-5 accent-brand-blue-600" />
                <span className="font-mono text-gray-900">{p.pallet_number}</span>
                <span className="text-caption text-gray-600">{p.note}</span>
              </label>
            ))}
          </div>
          {errors.pallet_ids && (
            <p id="koreksi-pallet-error" className="mt-1.5 text-caption text-danger-fg">
              {errors.pallet_ids}
            </p>
          )}
        </fieldset>

        <Field id="koreksi-catatan" label="Catatan" hint="Opsional." error={errors.notes}>
          {(props) => <textarea {...props} rows={2} maxLength={500} value={form.notes} onChange={set('notes')} className="field h-auto py-2" />}
        </Field>
        <ChangePreview
          items={changes}
          note={
            [
              removed.length ? `${removed.map((p) => p.pallet_number).join(', ')} kembali ke TPS dan masa simpannya berjalan lagi.` : '',
              file && suratJalan.file_name ? 'Berkas bukti lama tetap disimpan di server dan tercatat di Riwayat koreksi.' : '',
            ]
              .filter(Boolean)
              .join(' ') || null
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

export default function SuratJalanTab({ onMessage }) {
  const state = useCorrectionList('/koreksi/surat-jalan');
  const [dialog, setDialog] = useState(null);

  const done = (message) => {
    setDialog(null);
    onMessage(message);
    state.list.reload();
  };

  return (
    <section aria-label="Surat jalan" className="card">
      <FilterBar state={state} placeholder="No. surat jalan, vendor, pallet, petugas" />
      <ListBody state={state} icon={Truck} emptyTitle="Belum ada surat jalan.">
        {(rows) => (
          <Table caption="Surat jalan">
            <thead>
              <tr>
                <Th>No. surat jalan</Th>
                <Th>Serah terima</Th>
                <Th>Pallet</Th>
                <Th numeric>Berat vendor</Th>
                <Th>Catatan</Th>
                <Th className="text-right">Tindakan</Th>
              </tr>
            </thead>
            <tbody>
              {rows.map((s) => (
                <tr key={s.id}>
                  <Td>
                    <span className="whitespace-nowrap font-mono text-gray-900">{s.number}</span>
                    <span className="block text-caption text-gray-600">{s.vendor}</span>
                  </Td>
                  <Td className="whitespace-nowrap">
                    {formatDate(s.handover_date)}
                    <span className="block text-caption text-gray-600">{s.vendor_officer_name}</span>
                  </Td>
                  <Td className="min-w-[9rem] font-mono text-caption text-gray-800">{s.pallets.map((p) => p.pallet_number).join(', ')}</Td>
                  <Td numeric className="whitespace-nowrap">
                    {kg(s.weight_kg)}
                  </Td>
                  <Td className="min-w-[12rem] text-caption">
                    {s.notes && <span className="block text-gray-800">{s.notes}</span>}
                    <CorrectedBadge correction={s.correction} className={s.notes ? 'mt-1' : ''} />
                  </Td>
                  <Td>
                    <RowActions label={s.number} onEdit={() => setDialog({ type: 'edit', row: s })} onDelete={() => setDialog({ type: 'delete', row: s })} />
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </ListBody>

      {dialog?.type === 'edit' && <SuratJalanDialog suratJalan={dialog.row} meta={state.list.meta} onClose={() => setDialog(null)} onDone={done} />}
      {dialog?.type === 'delete' && (
        <DeleteDialog
          title={`Hapus surat jalan ${dialog.row.number}?`}
          path={`/koreksi/surat-jalan/${dialog.row.id}`}
          consequence={`${dialog.row.pallets.length} pallet kembali ke TPS dan masa simpannya berjalan lagi. Nomor surat jalan ini bisa dipakai lagi. Isinya tetap tercatat di Riwayat koreksi.`}
          onClose={() => setDialog(null)}
          onDone={done}
        >
          <Summary
            items={[
              ['Vendor', dialog.row.vendor],
              ['Serah terima', formatDate(dialog.row.handover_date)],
              ['Pallet', dialog.row.pallets.map((p) => p.pallet_number).join(', ')],
              ['Berat vendor', kg(dialog.row.weight_kg)],
            ]}
          />
        </DeleteDialog>
      )}
    </section>
  );
}
