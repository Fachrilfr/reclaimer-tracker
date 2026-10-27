import { useState } from 'react';
import { Cylinder, LoaderCircle, PackageCheck, PackagePlus } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { fieldErrorsFrom, useResource } from '../lib/useResource.js';
import { formatDate } from '../lib/format.js';
import { fullText, startedText } from '../lib/pallet.js';
import PageHeader from '../components/PageHeader.jsx';
import Field from '../components/Field.jsx';
import Alert from '../components/Alert.jsx';
import Modal from '../components/Modal.jsx';
import EmptyState from '../components/EmptyState.jsx';
import { StorageMeter, TpsBadge, tpsDelayText } from '../components/StorageBadge.jsx';
import FillDays from '../components/FillDays.jsx';
import PalletNotes from '../components/PalletNotes.jsx';
import { SOROT_CLASS, useSorot } from '../lib/useSorot.js';
import { ErrorCard, LoadingCard } from '../components/LoadState.jsx';
import { Table, Td, Th } from '../components/Table.jsx';

// Tahap operator: dari area reclaimer ke TPS. Dua tombol mengambil waktu saat ditekan:
// mulai diisi, lalu penuh. Pallet penuh langsung tercatat terkirim ke TPS dan diserahkan
// ke HSE; masa simpan TPS ke vendor ada di modul Limbah B3.

function NotesField({ id, value, onChange, error }) {
  return (
    <Field id={id} label="Keterangan" hint="Opsional." error={error}>
      {(props) => (
        <input {...props} type="text" maxLength={500} value={value} onChange={(e) => onChange(e.target.value)} className="field" />
      )}
    </Field>
  );
}

function SubmitButton({ busy, icon: Icon, children, ...props }) {
  return (
    <button type="button" disabled={busy} className="btn-primary h-12 w-full px-5 text-body-lg sm:w-auto" {...props}>
      {busy ? <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" /> : <Icon className="h-5 w-5" aria-hidden="true" />}
      {busy ? 'Menyimpan…' : children}
    </button>
  );
}

function StartPallet({ deliveryDays, onSubmit }) {
  const [notes, setNotes] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const start = async () => {
    setBusy(true);
    setError('');
    try {
      await onSubmit('/pallets', { notes: notes.trim() || null });
    } catch (err) {
      setError(fieldErrorsFrom(err).pallet ?? fieldErrorsFrom(err).notes ?? err.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <section aria-labelledby="start-title" className="card p-4 sm:p-5">
      <h2 id="start-title" className="text-h2 font-semibold">
        Mulai isi pallet baru
      </h2>
      <p className="mt-1 text-body text-gray-600">
        Tekan saat pallet mulai diisi residu. Tanggal dan jam dicatat otomatis. Batas kirim ke TPS {deliveryDays} hari sejak
        saat itu.
      </p>
      <div className="mt-4 max-w-md">
        <NotesField id="start-notes" value={notes} onChange={setNotes} />
      </div>
      {error && (
        <Alert tone="danger" className="mt-4">
          {error}
        </Alert>
      )}
      <div className="mt-4">
        <SubmitButton busy={busy} icon={PackagePlus} onClick={start}>
          Mulai isi pallet
        </SubmitButton>
      </div>
    </section>
  );
}

function FillingPallet({ pallet, canFill, onSubmit }) {
  const [notes, setNotes] = useState('');
  const [confirming, setConfirming] = useState(false);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const markFull = async () => {
    setBusy(true);
    setError('');
    try {
      await onSubmit(`/pallets/${pallet.id}/full`, { notes: notes.trim() || null });
    } catch (err) {
      setError(fieldErrorsFrom(err).pallet ?? fieldErrorsFrom(err).notes ?? err.message);
      setConfirming(false);
    } finally {
      setBusy(false);
    }
  };

  return (
    <section aria-labelledby="filling-title" className="card p-4 sm:p-5">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 id="filling-title" className="text-h2 font-semibold">
            Pallet <span className="font-mono">{pallet.pallet_number}</span> sedang diisi
          </h2>
          <p className="mt-1 text-body text-gray-600">
            Mulai diisi {startedText(pallet)}. Batas kirim ke TPS {formatDate(pallet.tps_due_date)}.
          </p>
          {pallet.notes && <p className="mt-1 text-body text-gray-800">Keterangan: {pallet.notes}</p>}
        </div>
        <TpsBadge tps={pallet.tps} />
      </div>

      <div className="mt-3">
        <StorageMeter storage={pallet.tps} label={`Batas kirim ke TPS pallet ${pallet.pallet_number}`} />
        <p className="mt-1 text-caption text-gray-600">
          {pallet.tps.days_elapsed} dari {pallet.tps.max_days} hari batas kirim ke TPS terpakai.
        </p>
        {pallet.tps.level === 'kritis' && (
          <Alert tone="danger" className="mt-3">
            Pallet ini sudah lewat batas kirim ke TPS. Tandai penuh dan serahkan ke HSE secepatnya.
            {tpsDelayText(pallet.tps) && <span className="mt-1 block">{tpsDelayText(pallet.tps)}</span>}
          </Alert>
        )}
      </div>

      {canFill && (
        <div className="mt-5 border-t border-gray-100 pt-5">
          <h3 className="text-h3 font-semibold">Pallet sudah penuh?</h3>
          <p className="mt-1 text-body text-gray-600">
            Tekan saat pallet penuh. Tanggal dan jam dicatat otomatis, dan pallet langsung tercatat terkirim ke TPS.
          </p>
          <div className="mt-4 max-w-md">
            <NotesField id="full-notes" value={notes} onChange={setNotes} />
          </div>
          {error && (
            <Alert tone="danger" className="mt-4">
              {error}
            </Alert>
          )}
          <div className="mt-4">
            <SubmitButton busy={busy} icon={PackageCheck} onClick={() => setConfirming(true)}>
              Pallet penuh
            </SubmitButton>
          </div>
        </div>
      )}

      {confirming && (
        <Modal title={`Tandai pallet ${pallet.pallet_number} penuh?`} onClose={() => !busy && setConfirming(false)}>
          <p className="text-body text-gray-800">
            Waktu sekarang dicatat sebagai waktu penuh, dan pallet langsung tercatat terkirim ke TPS.
          </p>
          <div className="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button type="button" onClick={() => setConfirming(false)} disabled={busy} className="btn-secondary h-11 px-4 text-body">
              Batal
            </button>
            <button type="button" onClick={markFull} disabled={busy} className="btn-primary h-11 px-4 text-body">
              {busy ? <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" /> : null}
              {busy ? 'Menyimpan…' : 'Ya, pallet penuh'}
            </button>
          </div>
        </Modal>
      )}
    </section>
  );
}

export default function DrumPage() {
  const { can, request } = useAuth();
  const canFill = can('drum_b3', 'isi');
  const resource = useResource('/pallets');
  const [message, setMessage] = useState('');
  const sorot = useSorot(Boolean(resource.data));

  const submit = async (path, body) => {
    setMessage('');
    const response = await request(path, { method: 'POST', body });
    setMessage(response.message);
    await resource.reload();
  };

  const data = resource.data;

  return (
    <>
      <PageHeader
        title="Input pallet B3"
        description={
          data
            ? `Catat pallet residu di area reclaimer dengan dua tombol: mulai diisi dan penuh. Pallet penuh langsung tercatat terkirim ke TPS, paling lambat ${data.tps_delivery_days} hari sejak mulai diisi.`
            : 'Catat pallet residu di area reclaimer sampai terkirim ke TPS.'
        }
      />

      {!data ? (
        <div className="mt-6">
          {resource.loading ? (
            <LoadingCard label="Memuat data pallet…" />
          ) : (
            <ErrorCard title="Data pallet belum bisa dimuat." message={resource.error} onRetry={resource.reload} />
          )}
        </div>
      ) : (
        <div className="mt-6 space-y-6">
          {message && <Alert tone="success">{message}</Alert>}

          {data.filling ? (
            <FillingPallet key={data.filling.id} pallet={data.filling} canFill={canFill} onSubmit={submit} />
          ) : canFill ? (
            <StartPallet deliveryDays={data.tps_delivery_days} onSubmit={submit} />
          ) : (
            <div className="card">
              <EmptyState icon={Cylinder} title="Tidak ada pallet yang sedang diisi.">
                Operator menekan Mulai isi pallet saat pallet baru mulai diisi residu.
              </EmptyState>
            </div>
          )}

          <section aria-labelledby="pallets-title" className="card">
            <div className="p-4 sm:p-5">
              <h2 id="pallets-title" className="text-h2 font-semibold">
                Pallet terbaru
              </h2>
              <p className="mt-1 text-body text-gray-600">
                10 pallet terakhir. Pallet penuh sudah diserahkan ke HSE untuk disimpan di TPS.
              </p>
            </div>
            {data.recent.length ? (
              <Table caption="Pallet terbaru">
                <thead>
                  <tr>
                    <Th>No. pallet</Th>
                    <Th>Mulai diisi</Th>
                    <Th>Penuh dan terkirim ke TPS</Th>
                    <Th>Batas kirim ke TPS</Th>
                    <Th>Lama sampai terkirim ke TPS</Th>
                    <Th>Keterangan</Th>
                  </tr>
                </thead>
                <tbody>
                  {data.recent.map((p) => (
                    <tr
                      key={p.id}
                      data-sorot={`pallet-${p.id}`}
                      tabIndex={-1}
                      className={sorot === `pallet-${p.id}` ? SOROT_CLASS : undefined}
                    >
                      <Td className="whitespace-nowrap font-mono text-gray-900">{p.pallet_number}</Td>
                      <Td className="whitespace-nowrap">{startedText(p)}</Td>
                      <Td className="whitespace-nowrap">{fullText(p) ?? <span className="text-gray-600">Sedang diisi</span>}</Td>
                      <Td className="whitespace-nowrap">{formatDate(p.tps_due_date)}</Td>
                      <Td>
                        <FillDays pallet={p} />
                      </Td>
                      <Td className="min-w-[12rem] text-body">
                        <PalletNotes pallet={p} />
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            ) : (
              <EmptyState icon={Cylinder} title="Belum ada pallet tercatat.">
                Pallet yang dimulai akan muncul di sini dengan hitung mundur batas kirim ke TPS.
              </EmptyState>
            )}
          </section>
        </div>
      )}
    </>
  );
}
