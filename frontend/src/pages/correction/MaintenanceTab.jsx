import { useState } from 'react';
import { Wrench } from 'lucide-react';
import { formatDateTime, formatDuration } from '../../lib/format.js';
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

const KNOWN = ['start', 'end', 'maintenance_reason', 'end_note', 'reason'];

// "2026-10-20T07:30" (input datetime-local) menjadi "20 Okt 2026, 07.30"
const showLocal = (value) => (value ? formatDateTime(value) : '–');

function MaintenanceDialog({ window: w, meta, onClose, onDone }) {
  const open = !w.end_local;
  const [form, setForm] = useState({
    start: w.start_local,
    end: w.end_local ?? '',
    maintenance_reason: w.reason,
    end_note: w.end_note ?? '',
    reason: '',
  });
  const { errors, formError, saving, submit, clear } = useSubmit(onDone, KNOWN);
  const set = (key) => (e) => {
    setForm((f) => ({ ...f, [key]: e.target.value }));
    clear(key);
  };

  const changes = changeList(
    [
      { key: 'start', label: 'Mulai', show: showLocal },
      { key: 'end', label: 'Selesai', show: showLocal },
      { key: 'maintenance_reason', label: 'Alasan maintenance' },
      { key: 'end_note', label: 'Catatan perbaikan' },
    ],
    { start: w.start_local, end: w.end_local, maintenance_reason: w.reason, end_note: w.end_note },
    form,
  );

  const handleSubmit = (e) => {
    e.preventDefault();
    const local = {};
    if (!form.start) local.start = 'Isi waktu mulai maintenance.';
    if (!open && !form.end) local.end = 'Isi waktu selesai maintenance.';
    if (!open && form.start && form.end && form.end <= form.start) local.end = 'Waktu selesai harus setelah waktu mulai.';
    if (!form.maintenance_reason.trim()) local.maintenance_reason = 'Tulis alasan maintenance, misalnya "Pompa vakum bocor".';
    if (!open && !form.end_note.trim()) local.end_note = 'Tulis catatan perbaikan, misalnya "Seal pompa diganti".';
    if (!form.reason.trim()) local.reason = REASON_REQUIRED;
    submit(
      `/koreksi/maintenance/${w.id}`,
      {
        method: 'PUT',
        body: {
          start: form.start,
          end: open ? null : form.end,
          maintenance_reason: form.maintenance_reason.trim(),
          end_note: open ? null : form.end_note.trim(),
          reason: form.reason.trim(),
        },
      },
      local,
    );
  };

  return (
    <Modal title="Koreksi maintenance mesin" onClose={() => !saving && onClose()}>
      <Summary
        items={[
          ['Dilaporkan oleh', w.started_by],
          open ? ['Status', 'Masih berlangsung'] : ['Ditandai selesai oleh', w.ended_by],
        ]}
      />
      <form onSubmit={handleSubmit} noValidate className="mt-4 space-y-4">
        {formError && <Alert tone="danger">{formError}</Alert>}
        {open && <Alert tone="warning">Maintenance ini masih berlangsung. Waktu mulai dan alasannya juga mengubah status mesin yang tampil di aplikasi.</Alert>}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field id="koreksi-mulai" label="Mulai" required error={errors.start}>
            {(props) => <input {...props} type="datetime-local" max={meta.now_local} value={form.start} onChange={set('start')} className="field" />}
          </Field>
          {!open && (
            <Field id="koreksi-selesai" label="Selesai" required error={errors.end}>
              {(props) => (
                <input {...props} type="datetime-local" min={form.start} max={meta.now_local} value={form.end} onChange={set('end')} className="field" />
              )}
            </Field>
          )}
        </div>
        <Field id="koreksi-alasan-maintenance" label="Alasan maintenance" required error={errors.maintenance_reason}>
          {(props) => (
            <input {...props} type="text" maxLength={255} value={form.maintenance_reason} onChange={set('maintenance_reason')} className="field" />
          )}
        </Field>
        {!open && (
          <Field id="koreksi-catatan-perbaikan" label="Catatan perbaikan" required error={errors.end_note}>
            {(props) => <input {...props} type="text" maxLength={255} value={form.end_note} onChange={set('end_note')} className="field" />}
          </Field>
        )}
        <ChangePreview items={changes} />
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

export default function MaintenanceTab({ onMessage }) {
  const state = useCorrectionList('/koreksi/maintenance');
  const [dialog, setDialog] = useState(null);

  const done = (message) => {
    setDialog(null);
    onMessage(message);
    state.list.reload();
  };

  return (
    <section aria-label="Maintenance mesin" className="card">
      <FilterBar state={state} placeholder="Alasan, catatan perbaikan, pelapor" />
      <ListBody state={state} icon={Wrench} emptyTitle="Belum ada riwayat maintenance.">
        {(rows) => (
          <Table caption="Maintenance mesin">
            <thead>
              <tr>
                <Th>Mulai</Th>
                <Th>Selesai</Th>
                <Th>Durasi</Th>
                <Th>Alasan dan catatan perbaikan</Th>
                <Th className="text-right">Tindakan</Th>
              </tr>
            </thead>
            <tbody>
              {rows.map((w) => (
                <tr key={w.id}>
                  <Td className="whitespace-nowrap">
                    {formatDateTime(w.start)}
                    <span className="block text-caption text-gray-600">{w.started_by}</span>
                  </Td>
                  <Td className="whitespace-nowrap">
                    {w.end ? formatDateTime(w.end) : 'Masih berlangsung'}
                    {w.ended_by && <span className="block text-caption text-gray-600">{w.ended_by}</span>}
                  </Td>
                  <Td className="whitespace-nowrap">{w.end ? formatDuration(w.duration_minutes) : '–'}</Td>
                  <Td className="min-w-[14rem]">
                    {w.reason}
                    {w.end_note && <span className="block text-caption text-gray-600">Perbaikan: {w.end_note}</span>}
                    <CorrectedBadge correction={w.correction} />
                  </Td>
                  <Td>
                    <RowActions
                      label={`maintenance ${formatDateTime(w.start)}`}
                      onEdit={() => setDialog({ type: 'edit', row: w })}
                      onDelete={() => setDialog({ type: 'delete', row: w })}
                    />
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </ListBody>

      {dialog?.type === 'edit' && <MaintenanceDialog window={dialog.row} meta={state.list.meta} onClose={() => setDialog(null)} onDone={done} />}
      {dialog?.type === 'delete' && (
        <DeleteDialog
          title="Hapus maintenance ini?"
          path={`/koreksi/maintenance/${dialog.row.id}`}
          consequence={
            dialog.row.end
              ? 'Periode ini hilang dari riwayat maintenance dan laporan.'
              : 'Maintenance ini masih berlangsung. Setelah dihapus, mesin kembali berstatus beroperasi dan input bisa dicatat lagi.'
          }
          onClose={() => setDialog(null)}
          onDone={done}
        >
          <Summary
            items={[
              ['Mulai', formatDateTime(dialog.row.start)],
              ['Selesai', dialog.row.end ? formatDateTime(dialog.row.end) : 'Masih berlangsung'],
              ['Alasan', dialog.row.reason],
            ]}
          />
        </DeleteDialog>
      )}
    </section>
  );
}
