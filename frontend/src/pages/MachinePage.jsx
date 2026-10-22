import { useState } from 'react';
import { CircleCheck, LoaderCircle, Wrench } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { useNotifications } from '../notifications/NotificationContext.jsx';
import { fieldErrorsFrom, useResource } from '../lib/useResource.js';
import { formatDateTime, formatDuration } from '../lib/format.js';
import PageHeader from '../components/PageHeader.jsx';
import Field from '../components/Field.jsx';
import Alert from '../components/Alert.jsx';
import EmptyState from '../components/EmptyState.jsx';
import { ErrorCard, LoadingCard } from '../components/LoadState.jsx';
import { Table, Td, Th } from '../components/Table.jsx';

function StatusPill({ maintenance }) {
  return maintenance ? (
    <span className="inline-flex items-center gap-1 rounded-full border border-warning-border bg-warning-bg px-2 py-0.5 text-caption font-medium text-warning-fg">
      <Wrench className="h-3.5 w-3.5" aria-hidden="true" />
      Maintenance
    </span>
  ) : (
    <span className="inline-flex items-center gap-1 rounded-full border border-success-border bg-success-bg px-2 py-0.5 text-caption font-medium text-success-fg">
      <CircleCheck className="h-3.5 w-3.5" aria-hidden="true" />
      Beroperasi
    </span>
  );
}

// Datetime lokal minimal untuk input "Perkiraan selesai"
function nowLocal() {
  const d = new Date();
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  return d.toISOString().slice(0, 16);
}

function StatusForm({ maintenance, onDone }) {
  const { request } = useAuth();
  const [reason, setReason] = useState('');
  const [expectedEnd, setExpectedEnd] = useState('');
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [isSaving, setIsSaving] = useState(false);

  const field = maintenance ? 'note' : 'reason';

  const handleSubmit = async (e) => {
    e.preventDefault();
    setFormError('');
    if (!reason.trim()) {
      setErrors({
        [field]: maintenance
          ? 'Tulis catatan perbaikan, misalnya "Seal pompa diganti".'
          : 'Tulis alasan maintenance, misalnya "Pompa vakum bocor".',
      });
      return;
    }
    setIsSaving(true);
    try {
      const response = await request(maintenance ? '/machine/operating' : '/machine/maintenance', {
        method: 'POST',
        body: maintenance
          ? { note: reason.trim() }
          : { reason: reason.trim(), expected_end_at: expectedEnd ? new Date(expectedEnd).toISOString() : null },
      });
      setReason('');
      setExpectedEnd('');
      setErrors({});
      onDone(response.message);
    } catch (err) {
      const serverErrors = fieldErrorsFrom(err);
      setErrors(serverErrors);
      if (!Object.keys(serverErrors).length) setFormError(err.message);
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} noValidate className="mt-5 space-y-4 border-t border-gray-100 pt-5">
      <h3 className="text-h3 font-semibold">{maintenance ? 'Mesin sudah beroperasi' : 'Laporkan maintenance'}</h3>
      <p className="-mt-3 text-body text-gray-600">
        {maintenance
          ? 'HSE, supervisor, manajemen, dan super admin akan diberi tahu bahwa mesin kembali beroperasi. Batch bisa dicatat lagi.'
          : 'HSE, supervisor, manajemen, dan super admin langsung diberi tahu. Batch tidak bisa dicatat selama maintenance.'}
      </p>
      {formError && <Alert tone="danger">{formError}</Alert>}
      <div className={`grid grid-cols-1 gap-4 ${maintenance ? '' : 'sm:grid-cols-3'}`}>
        <div className={maintenance ? '' : 'sm:col-span-2'}>
          <Field
            id={field}
            label={maintenance ? 'Catatan perbaikan' : 'Alasan maintenance'}
            required
            error={errors[field]}
          >
            {(props) => (
              <input
                {...props}
                type="text"
                maxLength={255}
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                className="field"
              />
            )}
          </Field>
        </div>
        {!maintenance && (
          <Field id="expected_end_at" label="Perkiraan selesai" hint="Opsional." error={errors.expected_end_at}>
            {(props) => (
              <input
                {...props}
                type="datetime-local"
                min={nowLocal()}
                value={expectedEnd}
                onChange={(e) => setExpectedEnd(e.target.value)}
                className="field"
              />
            )}
          </Field>
        )}
      </div>
      <button type="submit" disabled={isSaving} className="btn-primary h-12 w-full px-5 text-body-lg sm:w-auto">
        {isSaving ? (
          <>
            <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />
            Menyimpan…
          </>
        ) : maintenance ? (
          'Tandai mesin beroperasi'
        ) : (
          'Laporkan maintenance'
        )}
      </button>
    </form>
  );
}

export default function MachinePage() {
  const { can } = useAuth();
  const notifications = useNotifications();
  const resource = useResource('/machine');
  const [message, setMessage] = useState('');

  const handleDone = async (text) => {
    setMessage(text);
    await Promise.all([resource.reload(), notifications.reload()]);
  };

  const data = resource.data;
  const machine = data?.machine;
  const maintenance = machine?.status === 'maintenance';

  return (
    <>
      <PageHeader
        title="Status mesin"
        description="Laporkan saat mesin reclaimer masuk maintenance atau perbaikan, dan saat mesin kembali beroperasi."
      />

      {!data ? (
        <div className="mt-6">
          {resource.loading ? (
            <LoadingCard label="Memuat status mesin…" />
          ) : (
            <ErrorCard title="Status mesin belum bisa dimuat." message={resource.error} onRetry={resource.reload} />
          )}
        </div>
      ) : (
        <div className="mt-6 space-y-6">
          {message && <Alert tone="success">{message}</Alert>}

          <section aria-labelledby="machine-title" className="card p-4 sm:p-5">
            <div className="flex flex-wrap items-start justify-between gap-2">
              <div>
                <h2 id="machine-title" className="text-h2 font-semibold">
                  {machine.name}
                </h2>
                <p className="font-mono text-body text-gray-600">{machine.code}</p>
              </div>
              <StatusPill maintenance={maintenance} />
            </div>

            {maintenance ? (
              <dl className="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 text-body sm:grid-cols-2">
                <div>
                  <dt className="text-label font-medium text-gray-600">Mulai maintenance</dt>
                  <dd className="text-gray-900">{formatDateTime(machine.maintenance_started_at)}</dd>
                </div>
                <div>
                  <dt className="text-label font-medium text-gray-600">Perkiraan selesai</dt>
                  <dd className="text-gray-900">
                    {machine.maintenance_expected_end_at ? formatDateTime(machine.maintenance_expected_end_at) : 'Belum diisi'}
                  </dd>
                </div>
                <div>
                  <dt className="text-label font-medium text-gray-600">Alasan</dt>
                  <dd className="text-gray-900">{machine.maintenance_reason}</dd>
                </div>
                <div>
                  <dt className="text-label font-medium text-gray-600">Dilaporkan oleh</dt>
                  <dd className="text-gray-900">{machine.maintenance_reported_by ?? '–'}</dd>
                </div>
              </dl>
            ) : (
              <p className="mt-3 text-body text-gray-600">Mesin beroperasi normal. Batch reclaimer bisa dicatat.</p>
            )}

            {can('mesin', 'isi') && <StatusForm key={machine.status} maintenance={maintenance} onDone={handleDone} />}
          </section>

          <section aria-labelledby="history-title" className="card">
            <div className="p-4 sm:p-5">
              <h2 id="history-title" className="text-h2 font-semibold">
                Riwayat maintenance
              </h2>
              <p className="mt-1 text-body text-gray-600">Terbaru di atas.</p>
            </div>
            {data.history.length ? (
              <Table caption="Riwayat maintenance mesin">
                <thead>
                  <tr>
                    <Th>Mulai</Th>
                    <Th>Selesai</Th>
                    <Th>Durasi</Th>
                    <Th>Alasan</Th>
                    <Th>Catatan perbaikan</Th>
                  </tr>
                </thead>
                <tbody>
                  {data.history.map((w) => (
                    <tr key={w.start}>
                      <Td className="whitespace-nowrap">
                        {formatDateTime(w.start)}
                        <span className="block text-caption text-gray-600">{w.started_by}</span>
                      </Td>
                      <Td className="whitespace-nowrap">
                        {w.end ? (
                          <>
                            {formatDateTime(w.end)}
                            <span className="block text-caption text-gray-600">{w.ended_by}</span>
                          </>
                        ) : (
                          <StatusPill maintenance />
                        )}
                      </Td>
                      <Td className="whitespace-nowrap">{w.end ? formatDuration(w.duration_minutes) : 'Masih berlangsung'}</Td>
                      <Td className="min-w-[12rem]">{w.reason}</Td>
                      <Td className="min-w-[12rem]">{w.end_note ?? '–'}</Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            ) : (
              <EmptyState icon={Wrench} title="Belum ada riwayat maintenance.">
                Laporan maintenance akan tercatat di sini beserta durasinya.
              </EmptyState>
            )}
          </section>
        </div>
      )}
    </>
  );
}
