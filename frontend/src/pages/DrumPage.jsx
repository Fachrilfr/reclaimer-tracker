import { useState } from 'react';
import { Cylinder, LoaderCircle } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { fieldErrorsFrom, useResource } from '../lib/useResource.js';
import { formatDate } from '../lib/format.js';
import PageHeader from '../components/PageHeader.jsx';
import Field from '../components/Field.jsx';
import Alert from '../components/Alert.jsx';
import EmptyState from '../components/EmptyState.jsx';
import { StorageMeter, TpsBadge, tpsDelayText } from '../components/StorageBadge.jsx';
import { SOROT_CLASS, useSorot } from '../lib/useSorot.js';
import { ErrorCard, LoadingCard } from '../components/LoadState.jsx';
import { Table, Td, Th } from '../components/Table.jsx';

// Halaman ini tahap operator: dari area reclaimer ke TPS. Pallet penuh dianggap
// dikirim ke TPS dan diserahkan ke HSE; masa simpan TPS ke vendor ada di modul Limbah B3.

// Form satu tanggal + keterangan, dipakai untuk mulai pallet dan catat drum penuh
function DateForm({ id, dateField, label, hint, submitLabel, today, onSubmit }) {
  const [date, setDate] = useState(today);
  const [notes, setNotes] = useState('');
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [isSaving, setIsSaving] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setFormError('');
    if (!date) {
      setErrors({ [dateField]: `Isi ${label.toLowerCase()}.` });
      return;
    }
    setIsSaving(true);
    try {
      await onSubmit({ [dateField]: date, notes: notes.trim() || null });
      setNotes('');
      setErrors({});
    } catch (err) {
      const serverErrors = fieldErrorsFrom(err);
      setErrors(serverErrors);
      if (!Object.keys(serverErrors).length) setFormError(err.message);
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-4">
      {formError && <Alert tone="danger">{formError}</Alert>}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Field id={`${id}-date`} label={label} required hint={hint} error={errors[dateField]}>
          {(props) => (
            <input
              {...props}
              type="date"
              max={today}
              value={date}
              onChange={(e) => setDate(e.target.value)}
              className="field"
            />
          )}
        </Field>
        <Field id={`${id}-notes`} label="Keterangan" hint="Opsional." error={errors.notes}>
          {(props) => (
            <input
              {...props}
              type="text"
              maxLength={500}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              className="field"
            />
          )}
        </Field>
      </div>
      <button type="submit" disabled={isSaving} className="btn-primary h-12 w-full px-5 text-body-lg sm:w-auto">
        {isSaving ? (
          <>
            <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />
            Menyimpan…
          </>
        ) : (
          submitLabel
        )}
      </button>
    </form>
  );
}

function FillingPallet({ pallet, capacity, today, canFill, onDrum }) {
  const nextDrum = pallet.drum_count + 1;
  const slots = Array.from({ length: capacity }, (_, i) => pallet.drums.find((d) => d.sequence === i + 1));

  return (
    <section aria-labelledby="filling-title" className="card p-4 sm:p-5">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 id="filling-title" className="text-h2 font-semibold">
            Pallet <span className="font-mono">{pallet.pallet_number}</span>
          </h2>
          <p className="mt-1 text-body text-gray-600">
            Mulai terisi {formatDate(pallet.start_date)}. Batas kirim ke TPS {formatDate(pallet.tps_due_date)}.
          </p>
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
            Pallet ini sudah lewat batas kirim ke TPS. Selesaikan drum terakhir dan serahkan ke HSE secepatnya.
            {tpsDelayText(pallet.tps) && <span className="mt-1 block">{tpsDelayText(pallet.tps)}</span>}
          </Alert>
        )}
      </div>

      <ol className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3" aria-label="Drum di pallet ini">
        {slots.map((drum, i) => (
          <li
            key={i}
            className={`rounded-lg border p-3 ${drum ? 'border-gray-200 bg-white' : 'border-dashed border-gray-400 bg-gray-25'}`}
          >
            <p className="flex items-center gap-2 text-label font-semibold text-gray-900">
              <Cylinder className="h-5 w-5 text-gray-600" aria-hidden="true" />
              Drum ke-{i + 1}
            </p>
            {drum ? (
              <p className="mt-1 text-body text-gray-800">
                Penuh {formatDate(drum.filled_date)}
                <span className="block text-caption text-gray-600">
                  {drum.operator}
                  {drum.notes && ` · ${drum.notes}`}
                </span>
              </p>
            ) : (
              <p className="mt-1 text-body text-gray-600">Belum penuh</p>
            )}
          </li>
        ))}
      </ol>

      {canFill && (
        <div className="mt-5 border-t border-gray-100 pt-5">
          <h3 className="text-h3 font-semibold">Catat drum ke-{nextDrum} penuh</h3>
          <p className="mb-4 mt-1 text-body text-gray-600">
            {nextDrum === capacity
              ? `Ini drum terakhir. Setelah dicatat, pallet ${pallet.pallet_number} penuh dan tercatat sudah dikirim ke TPS.`
              : 'Catat saat drum residu sudah penuh. Berat tidak perlu diisi.'}
          </p>
          <DateForm
            key={pallet.drum_count}
            id="drum"
            dateField="filled_date"
            label="Tanggal drum penuh"
            submitLabel={`Catat drum ke-${nextDrum}`}
            today={today}
            onSubmit={onDrum}
          />
        </div>
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
        title="Input drum B3"
        description={
          data
            ? `Catat drum residu di area reclaimer. Pallet yang sudah penuh (${data.drums_per_pallet} drum) dikirim ke TPS dan diserahkan ke HSE, paling lambat ${data.tps_delivery_days} hari sejak pallet mulai terisi.`
            : 'Catat drum residu di area reclaimer sampai dikirim ke TPS.'
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
            <FillingPallet
              pallet={data.filling}
              capacity={data.drums_per_pallet}
              today={data.today}
              canFill={canFill}
              onDrum={(body) => submit(`/pallets/${data.filling.id}/drums`, body)}
            />
          ) : canFill ? (
            <section aria-labelledby="start-title" className="card p-4 sm:p-5">
              <h2 id="start-title" className="text-h2 font-semibold">
                Mulai pallet baru
              </h2>
              <p className="mb-4 mt-1 text-body text-gray-600">
                Tidak ada pallet yang sedang diisi. Batas kirim ke TPS {data.tps_delivery_days} hari dihitung sejak
                tanggal pallet mulai terisi.
              </p>
              <DateForm
                id="pallet"
                dateField="start_date"
                label="Tanggal mulai terisi"
                submitLabel="Mulai pallet"
                today={data.today}
                onSubmit={(body) => submit('/pallets', body)}
              />
            </section>
          ) : (
            <div className="card">
              <EmptyState icon={Cylinder} title="Tidak ada pallet yang sedang diisi.">
                Operator memulai pallet baru saat drum pertama mulai terisi.
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
                    <Th numeric>Drum</Th>
                    <Th>Mulai terisi</Th>
                    <Th>Batas kirim ke TPS</Th>
                    <Th>Status kirim ke TPS</Th>
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
                      <Td numeric>
                        {p.drum_count}/{data.drums_per_pallet}
                      </Td>
                      <Td className="whitespace-nowrap">{formatDate(p.start_date)}</Td>
                      <Td className="whitespace-nowrap">{formatDate(p.tps_due_date)}</Td>
                      <Td className="min-w-[14rem]">
                        <TpsBadge tps={p.tps} />
                        {tpsDelayText(p.tps) && (
                          <span className="mt-1 block text-caption text-gray-600">{tpsDelayText(p.tps)}</span>
                        )}
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
