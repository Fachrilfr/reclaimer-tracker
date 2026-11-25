import { useState } from 'react';
import { Cylinder, LoaderCircle, PackageCheck } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { useNotifications } from '../notifications/NotificationContext.jsx';
import { fieldErrorsFrom, useResource } from '../lib/useResource.js';
import { fullText, startedText } from '../lib/pallet.js';
import PageHeader from '../components/PageHeader.jsx';
import Field from '../components/Field.jsx';
import Alert from '../components/Alert.jsx';
import EmptyState from '../components/EmptyState.jsx';
import FillDays from '../components/FillDays.jsx';
import PalletNotes from '../components/PalletNotes.jsx';
import { SOROT_CLASS, useSorot } from '../lib/useSorot.js';
import { ErrorCard, LoadingCard } from '../components/LoadState.jsx';
import { Table, Td, Th } from '../components/Table.jsx';

function NotesField({ id, value, onChange, error }) {
  return (
    <Field id={id} label="Keterangan" hint="Opsional." error={error}>
      {(props) => (
        <input {...props} type="text" maxLength={500} value={value} onChange={(e) => onChange(e.target.value)} className="field" />
      )}
    </Field>
  );
}

function DispatchPallet({ pallet, drums, today, onSubmit }) {
  const [start, setStart] = useState(pallet?.start_date ?? '');
  const [sent, setSent] = useState(today);
  const [notes, setNotes] = useState('');
  const [checked, setChecked] = useState(false);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function save(e) {
    e.preventDefault();
    if (busy || !checked) return;
    setBusy(true);
    setError('');
    try {
      await onSubmit('/pallets/dispatch', {
        pallet_id: pallet?.id ?? null, start_date: start, sent_to_tps_at: sent, notes: notes.trim() || null,
      });
      setStart('');
      setSent(today);
      setNotes('');
      setChecked(false);
    } catch (err) {
      setError(Object.values(fieldErrorsFrom(err))[0] ?? err.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <form onSubmit={save} className="card space-y-4 p-4 sm:p-5">
      <h2 className="text-h2 font-semibold">Catat pengiriman pallet ke TPS</h2>
      {pallet && <p>Pallet yang sedang diisi: <strong>{pallet.pallet_number}</strong></p>}
      <div className="grid max-w-2xl gap-4 sm:grid-cols-2">
        <Field id="start_date" label="Tanggal mulai pengisian" required>
          {(props) => <input {...props} required type="date" max={sent || today} value={start} onChange={(e) => setStart(e.target.value)} className="field" />}
        </Field>
        <Field id="sent_to_tps_at" label="Tanggal pengiriman ke TPS" required>
          {(props) => <input {...props} required type="date" min={start} max={today} value={sent} onChange={(e) => setSent(e.target.value)} className="field" />}
        </Field>
      </div>
      <NotesField id="dispatch-notes" value={notes} onChange={setNotes} />
      <label className="flex items-center gap-2">
        <input type="checkbox" required checked={checked} onChange={(e) => setChecked(e.target.checked)} />
        Pallet sudah terisi {drums} drum residu.
      </label>
      {error && <Alert tone="danger">{error}</Alert>}
      <button type="submit" disabled={busy || !checked} className="btn-primary h-12 px-5">
        {busy ? <LoaderCircle className="h-5 w-5 animate-spin" /> : <PackageCheck className="h-5 w-5" />}
        {busy ? 'Menyimpan...' : 'Simpan pengiriman'}
      </button>
    </form>
  );
}

export default function DrumPage() {
  const { can, request } = useAuth();
  const notifications = useNotifications();
  const canFill = can('drum_b3', 'isi');
  const resource = useResource('/pallets');
  const [message, setMessage] = useState('');
  const sorot = useSorot(Boolean(resource.data));

  const submit = async (path, body) => {
    setMessage('');
    const response = await request(path, { method: 'POST', body });
    setMessage(response.message);
    // Banner pallet yang sedang diisi di semua halaman ikut berubah
    notifications.reload();
    await resource.reload();
  };

  const data = resource.data;

  return (
    <>
      <PageHeader
        title="Input pallet residu"
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

          {canFill && <DispatchPallet key={data.filling?.id ?? 'baru'} pallet={data.filling}
            today={data.today} drums={data.drums_per_pallet} onSubmit={submit} />}

          <section aria-labelledby="pallets-title" className="card">
            <div className="p-4 sm:p-5">
              <h2 id="pallets-title" className="text-h2 font-semibold">
                Pallet terbaru
              </h2>
            </div>
            {data.recent.length ? (
              <Table caption="Pallet terbaru">
                <thead>
                  <tr>
                    <Th>No. pallet</Th>
                    <Th>Mulai diisi</Th>
                    <Th>Penuh dan terkirim ke TPS</Th>
                    <Th>Total proses pengisian drum</Th>
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
                Pallet muncul setelah pengiriman ke TPS dicatat.
              </EmptyState>
            )}
          </section>
        </div>
      )}
    </>
  );
}
