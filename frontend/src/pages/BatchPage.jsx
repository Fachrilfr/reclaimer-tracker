import { useMemo, useRef, useState } from 'react';
import { Calculator, CircleCheck, LoaderCircle, Recycle, TriangleAlert } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { fieldErrorsFrom, useResource } from '../lib/useResource.js';
import { formatDate, formatNumber, formatPercent, parseDecimal } from '../lib/format.js';
import PageHeader from '../components/PageHeader.jsx';
import Field from '../components/Field.jsx';
import Alert from '../components/Alert.jsx';
import EmptyState from '../components/EmptyState.jsx';
import { ErrorCard, LoadingCard } from '../components/LoadState.jsx';
import { Table, Td, Th } from '../components/Table.jsx';

// Tombol isi cepat keterangan (FR-07)
const QUICK_NOTES = ['Start Pkl', 'Stop Pkl', 'Lanjutan', 'Kuras residu'];

const round2 = (n) => Math.round(n * 100) / 100;
const numberOrZero = (raw) => {
  const n = parseDecimal(raw);
  return n === null || Number.isNaN(n) ? 0 : n;
};

function emptyForm(formData, keep = {}) {
  return {
    date: keep.date ?? formData.today,
    shift_id: keep.shift_id ?? String(formData.suggested_shift_id ?? formData.shifts[0]?.id ?? ''),
    regu: keep.regu ?? '',
    is_continuation: false,
    parent_batch_id: '',
    ink_weight: '',
    solvent_used_weight: '',
    recovery_weight: '',
    oil_temp: '',
    vapour_temp: '',
    notes: '',
  };
}

// Angka rata kanan, tabular, satuan di dalam field (design.md §6.2)
function NumberInput({ suffix, ...props }) {
  return (
    <div className="relative">
      <input
        {...props}
        type="text"
        inputMode="decimal"
        autoComplete="off"
        className={`field text-right tabular-nums ${suffix.length > 2 ? 'pr-14' : 'pr-11'}`}
      />
      <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-body text-gray-600">
        {suffix}
      </span>
    </div>
  );
}

function outOfRange(value, range) {
  const n = parseDecimal(value);
  return n !== null && !Number.isNaN(n) && (n < range.min || n > range.max);
}

function BatchForm({ formData, onSaved }) {
  const { request } = useAuth();
  const [form, setForm] = useState(() => emptyForm(formData));
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [saved, setSaved] = useState(null);
  const [isSaving, setIsSaving] = useState(false);
  const notesRef = useRef(null);

  const set = (field) => (e) => {
    setForm((f) => ({ ...f, [field]: e.target.value }));
    setErrors((errs) => ({ ...errs, [field]: undefined }));
    setSaved(null);
  };

  const { temperature, target_recovery_rate: target, solvent_balance: balance } = formData;
  const candidates = formData.continuation_candidates;
  const parent = candidates.find((c) => String(c.id) === form.parent_batch_id);

  // Ringkasan material balance langsung saat angka diisi (FR-04), rumus PRD §8
  const summary = useMemo(() => {
    const ink = form.is_continuation ? 0 : numberOrZero(form.ink_weight);
    const solvent = numberOrZero(form.solvent_used_weight);
    const recovery = numberOrZero(form.recovery_weight);
    const input = round2(ink + solvent);
    const chainInput = round2((parent?.chain_input ?? 0) + input);
    const chainRecovery = round2((parent?.chain_recovery ?? 0) + recovery);

    return {
      input,
      recovery,
      residue: round2(input - recovery),
      rate: chainInput > 0 ? round2((chainRecovery / chainInput) * 100) : null,
      residueRate: chainInput > 0 ? round2(((chainInput - chainRecovery) / chainInput) * 100) : null,
      balanceAfter: round2(balance - solvent + recovery),
      recoveryExceeds: chainRecovery > chainInput,
      solventExceeds: solvent > balance,
      chainInput,
    };
  }, [form, parent, balance]);

  const validate = () => {
    const errs = {};
    const numberField = (field, label, { required = true, positive = false } = {}) => {
      const n = parseDecimal(form[field]);
      if (n === null) {
        if (required) errs[field] = label;
      } else if (Number.isNaN(n)) {
        errs[field] = 'Isi dengan angka, contoh 125,5.';
      } else if (positive && n <= 0) {
        errs[field] = label;
      }
    };

    if (!form.date) errs.date = 'Isi tanggal proses.';
    if (!form.shift_id) errs.shift_id = 'Pilih shift.';
    if (form.is_continuation && !form.parent_batch_id) errs.parent_batch_id = 'Pilih batch awal yang dilanjutkan.';
    if (!form.is_continuation) {
      numberField('ink_weight', 'Isi berat Tinta X yang masuk ke mesin.', { positive: true });
    }
    numberField('solvent_used_weight', 'Isi berat solvent dipakai dalam kg. Isi 0 bila tidak memakai solvent.');
    numberField('recovery_weight', 'Isi berat hasil recovery dalam kg. Isi 0 bila belum ada hasil.');
    numberField('oil_temp', 'Isi suhu oli (Oil Temperature).');
    numberField('vapour_temp', 'Isi suhu uap (Vapour Temperature).');

    if (!errs.recovery_weight && summary.recoveryExceeds) {
      errs.recovery_weight = form.is_continuation
        ? `Hasil recovery melebihi bahan masuk rangkaian batch (${formatNumber(summary.chainInput)} kg). Periksa kembali angka recovery.`
        : 'Hasil recovery melebihi bahan masuk (Tinta X + solvent). Periksa kembali angka recovery.';
    }
    if (!errs.solvent_used_weight && summary.solventExceeds) {
      errs.solvent_used_weight = `Solvent dipakai melebihi saldo penyimpanan (${formatNumber(balance)} kg). Periksa kembali angka solvent.`;
    }
    return errs;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setFormError('');
    setSaved(null);

    const errs = validate();
    setErrors(errs);
    if (Object.keys(errs).length) {
      setFormError('Periksa kembali isian yang ditandai.');
      return;
    }

    setIsSaving(true);
    try {
      const response = await request('/batches', {
        method: 'POST',
        body: {
          date: form.date,
          shift_id: Number(form.shift_id),
          regu: form.regu.trim() || null,
          is_continuation: form.is_continuation,
          parent_batch_id: form.is_continuation ? Number(form.parent_batch_id) : null,
          ink_weight: form.is_continuation ? 0 : parseDecimal(form.ink_weight),
          solvent_used_weight: parseDecimal(form.solvent_used_weight),
          recovery_weight: parseDecimal(form.recovery_weight),
          oil_temp: parseDecimal(form.oil_temp),
          vapour_temp: parseDecimal(form.vapour_temp),
          notes: form.notes.trim() || null,
        },
      });
      setSaved(response.data);
      setForm((f) => emptyForm(formData, { date: f.date, shift_id: f.shift_id, regu: f.regu }));
      onSaved();
    } catch (err) {
      const serverErrors = fieldErrorsFrom(err);
      setErrors(serverErrors);
      setFormError(Object.keys(serverErrors).length ? 'Periksa kembali isian yang ditandai.' : err.message);
    } finally {
      setIsSaving(false);
    }
  };

  const addQuickNote = (text) => {
    setForm((f) => ({ ...f, notes: f.notes ? `${f.notes.trimEnd()}, ${text} ` : `${text} ` }));
    notesRef.current?.focus();
  };

  const oilWarning = outOfRange(form.oil_temp, temperature.oil);
  const vapourWarning = outOfRange(form.vapour_temp, temperature.vapour);
  const rateBelowTarget = summary.rate !== null && summary.rate < target;

  return (
    <form onSubmit={handleSubmit} noValidate className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-12">
      <div className="space-y-6 lg:col-span-8">
        {formData.machine?.status === 'maintenance' && (
          <Alert tone="warning" title={`${formData.machine.name} sedang maintenance.`}>
            {formData.machine.maintenance_reason
              ? `Alasan: ${formData.machine.maintenance_reason}. `
              : ''}
            Pastikan mesin sudah boleh dipakai sebelum mencatat batch.
          </Alert>
        )}

        <section aria-labelledby="info-title" className="card p-4 sm:p-5">
          <h2 id="info-title" className="text-h2 font-semibold">
            Informasi proses
          </h2>
          <dl className="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-body text-gray-600">
            <div>
              <dt className="inline">Operator: </dt>
              <dd className="inline font-medium text-gray-900">
                {formData.operator.name} (<span className="font-mono">{formData.operator.nik}</span>)
              </dd>
            </div>
            {formData.machine && (
              <div>
                <dt className="inline">Mesin: </dt>
                <dd className="inline font-medium text-gray-900">
                  <span className="font-mono">{formData.machine.code}</span>
                </dd>
              </div>
            )}
            <div>
              <dt className="inline">Bahan: </dt>
              <dd className="inline font-medium text-gray-900">{formData.material}</dd>
            </div>
          </dl>

          <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <Field id="date" label="Tanggal proses" required error={errors.date}>
              {(props) => (
                <input {...props} type="date" max={formData.today} value={form.date} onChange={set('date')} className="field" />
              )}
            </Field>
            <Field id="shift_id" label="Shift" required error={errors.shift_id}>
              {(props) => (
                <select {...props} value={form.shift_id} onChange={set('shift_id')} className="field">
                  {formData.shifts.map((shift) => (
                    <option key={shift.id} value={shift.id}>
                      {shift.name}
                    </option>
                  ))}
                </select>
              )}
            </Field>
            <Field id="regu" label="Regu" hint="Opsional, contoh B2." error={errors.regu}>
              {(props) => (
                <input {...props} type="text" maxLength={20} value={form.regu} onChange={set('regu')} className="field" />
              )}
            </Field>
          </div>

          <fieldset className="mt-4">
            <legend className="mb-1.5 text-label font-medium text-gray-800">Jenis batch</legend>
            <div className="flex flex-col gap-2 sm:flex-row sm:gap-6">
              <label className="flex min-h-11 cursor-pointer items-center gap-3 text-body-lg text-gray-900">
                <input
                  type="radio"
                  name="is_continuation"
                  checked={!form.is_continuation}
                  onChange={() => setForm((f) => ({ ...f, is_continuation: false, parent_batch_id: '' }))}
                  className="h-5 w-5 accent-brand-blue-600"
                />
                Batch baru
              </label>
              <label
                className={`flex min-h-11 items-center gap-3 text-body-lg ${
                  candidates.length ? 'cursor-pointer text-gray-900' : 'cursor-not-allowed text-gray-600'
                }`}
              >
                <input
                  type="radio"
                  name="is_continuation"
                  checked={form.is_continuation}
                  disabled={!candidates.length}
                  onChange={() => setForm((f) => ({ ...f, is_continuation: true, ink_weight: '' }))}
                  aria-describedby="continuation-hint"
                  className="h-5 w-5 accent-brand-blue-600"
                />
                Batch lanjutan dari shift sebelumnya
              </label>
            </div>
            <p id="continuation-hint" className="mt-1 text-caption text-gray-600">
              {candidates.length
                ? 'Batch lanjutan tidak membawa Tinta X baru. Recovery rate dihitung gabungan dengan batch awalnya.'
                : 'Belum ada batch awal dalam 3 hari terakhir yang bisa dilanjutkan.'}
            </p>
          </fieldset>

          {form.is_continuation && (
            <div className="mt-4">
              <Field id="parent_batch_id" label="Lanjutan dari batch" required error={errors.parent_batch_id}>
                {(props) => (
                  <select {...props} value={form.parent_batch_id} onChange={set('parent_batch_id')} className="field">
                    <option value="">Pilih batch awal</option>
                    {candidates.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.batch_number} · {formatDate(c.date)} · {c.shift} · recovery {formatPercent(c.chain_rate)}
                      </option>
                    ))}
                  </select>
                )}
              </Field>
            </div>
          )}
        </section>

        <section aria-labelledby="weight-title" className="card p-4 sm:p-5">
          <h2 id="weight-title" className="text-h2 font-semibold">
            Berat dan suhu
          </h2>
          <p className="mt-1 text-body text-gray-600">Dalam kg, dari timbangan. Gunakan koma untuk desimal.</p>

          <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
            {form.is_continuation ? (
              <div>
                <p className="mb-1.5 text-label font-medium text-gray-800">Tinta X masuk</p>
                <p className="flex h-11 items-center rounded-lg bg-gray-100 px-3 text-body text-gray-600">
                  Tidak ada Tinta X baru
                </p>
              </div>
            ) : (
              <Field id="ink_weight" label="Tinta X masuk" required error={errors.ink_weight}>
                {(props) => <NumberInput {...props} suffix="kg" value={form.ink_weight} onChange={set('ink_weight')} />}
              </Field>
            )}
            <Field
              id="solvent_used_weight"
              label="Solvent dipakai"
              required
              hint={`Saldo saat ini ${formatNumber(balance)} kg.`}
              error={errors.solvent_used_weight}
            >
              {(props) => (
                <NumberInput {...props} suffix="kg" value={form.solvent_used_weight} onChange={set('solvent_used_weight')} />
              )}
            </Field>
            <Field id="recovery_weight" label="Hasil recovery" required error={errors.recovery_weight}>
              {(props) => (
                <NumberInput {...props} suffix="kg" value={form.recovery_weight} onChange={set('recovery_weight')} />
              )}
            </Field>
          </div>

          <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field
              id="oil_temp"
              label="Oil Temperature"
              required
              hint={
                oilWarning
                  ? undefined
                  : `Normal ${temperature.oil.min}–${temperature.oil.max} °C.`
              }
              error={errors.oil_temp}
            >
              {(props) => (
                <NumberInput
                  {...props}
                  aria-describedby={oilWarning && !errors.oil_temp ? 'oil_temp-warning' : props['aria-describedby']}
                  suffix="°C"
                  value={form.oil_temp}
                  onChange={set('oil_temp')}
                />
              )}
            </Field>
            <Field
              id="vapour_temp"
              label="Vapour Temperature"
              required
              hint={vapourWarning ? undefined : `Normal ${temperature.vapour.min}–${temperature.vapour.max} °C.`}
              error={errors.vapour_temp}
            >
              {(props) => (
                <NumberInput
                  {...props}
                  aria-describedby={vapourWarning && !errors.vapour_temp ? 'vapour_temp-warning' : props['aria-describedby']}
                  suffix="°C"
                  value={form.vapour_temp}
                  onChange={set('vapour_temp')}
                />
              )}
            </Field>
          </div>
          {(oilWarning || vapourWarning) && (
            <div className="mt-3 space-y-1">
              {oilWarning && !errors.oil_temp && (
                <p id="oil_temp-warning" className="flex items-start gap-1.5 text-caption text-warning-fg">
                  <TriangleAlert className="mt-px h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                  Suhu oli di luar rentang normal {temperature.oil.min}–{temperature.oil.max} °C. Batch tetap bisa
                  disimpan dan ditandai untuk ditinjau supervisor.
                </p>
              )}
              {vapourWarning && !errors.vapour_temp && (
                <p id="vapour_temp-warning" className="flex items-start gap-1.5 text-caption text-warning-fg">
                  <TriangleAlert className="mt-px h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                  Suhu uap di luar rentang normal {temperature.vapour.min}–{temperature.vapour.max} °C. Batch tetap
                  bisa disimpan dan ditandai untuk ditinjau supervisor.
                </p>
              )}
            </div>
          )}
        </section>

        <section aria-labelledby="notes-title" className="card p-4 sm:p-5">
          <h2 id="notes-title" className="text-h2 font-semibold">
            Keterangan
          </h2>
          <div className="mt-3 flex flex-wrap gap-2" role="group" aria-label="Isi cepat keterangan">
            {QUICK_NOTES.map((text) => (
              <button
                key={text}
                type="button"
                onClick={() => addQuickNote(text)}
                className="btn-secondary h-11 px-3 text-body"
              >
                {text}
              </button>
            ))}
          </div>
          <div className="mt-3">
            <Field id="notes" label="Keterangan" hint="Opsional. Tombol di atas menambahkan teks yang sering dipakai." error={errors.notes}>
              {(props) => (
                <textarea
                  {...props}
                  ref={notesRef}
                  rows={2}
                  maxLength={500}
                  value={form.notes}
                  onChange={set('notes')}
                  className="field h-auto py-2"
                />
              )}
            </Field>
          </div>
        </section>
      </div>

      <aside aria-labelledby="summary-title" className="lg:col-span-4">
        <div className="card p-4 sm:p-5 lg:sticky lg:top-24">
          <h2 id="summary-title" className="flex items-center gap-2 text-h2 font-semibold">
            <Calculator className="h-5 w-5 text-gray-600" aria-hidden="true" />
            Material balance
          </h2>
          <p className="mt-1 text-caption text-gray-600">Dihitung otomatis dari angka yang diisi.</p>

          <dl className="mt-4 space-y-2 text-body tabular-nums" aria-live="polite">
            <div className="flex justify-between gap-4">
              <dt className="text-gray-600">Bahan masuk</dt>
              <dd className="text-right font-medium text-gray-900">{formatNumber(summary.input)} kg</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-gray-600">Hasil recovery</dt>
              <dd className="text-right font-medium text-gray-900">{formatNumber(summary.recovery)} kg</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-gray-600">Selisih bahan (residu estimasi)</dt>
              <dd className="whitespace-nowrap text-right font-medium text-gray-900">{formatNumber(summary.residue)} kg</dd>
            </div>
            <div className="flex justify-between gap-4 border-t border-gray-100 pt-2">
              <dt className="text-gray-600">Recovery rate{form.is_continuation && ' (gabungan)'}</dt>
              <dd className={`text-right font-semibold ${rateBelowTarget ? 'text-danger-fg' : 'text-gray-900'}`}>
                {summary.rate === null ? '–' : formatPercent(summary.rate)}
              </dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-gray-600">Residu rate (estimasi)</dt>
              <dd className="text-right font-medium text-gray-900">
                {summary.residueRate === null ? '–' : formatPercent(summary.residueRate)}
              </dd>
            </div>
            <div className="flex justify-between gap-4 border-t border-gray-100 pt-2">
              <dt className="text-gray-600">Saldo solvent</dt>
              <dd className={`text-right font-medium ${summary.balanceAfter < 0 ? 'text-danger-fg' : 'text-gray-900'}`}>
                {formatNumber(balance)} → {formatNumber(summary.balanceAfter)} kg
              </dd>
            </div>
          </dl>

          {rateBelowTarget && (
            <p className="mt-3 flex items-start gap-1.5 text-caption text-danger-fg">
              <TriangleAlert className="mt-px h-3.5 w-3.5 shrink-0" aria-hidden="true" />
              Di bawah target recovery {formatPercent(target)}. Batch tetap bisa disimpan dan muncul di alert supervisor.
            </p>
          )}

          {formError && (
            <Alert tone="danger" className="mt-4">
              {formError}
            </Alert>
          )}
          {saved && (
            <Alert tone="success" className="mt-4" title={`Batch ${saved.batch_number} tersimpan.`}>
              Recovery {formatPercent(saved.recovery_rate_percent)}, saldo solvent sekarang{' '}
              {formatNumber(saved.solvent_balance_after)} kg.
            </Alert>
          )}

          <button type="submit" disabled={isSaving} className="btn-primary mt-4 h-12 w-full text-body-lg">
            {isSaving ? (
              <>
                <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />
                Menyimpan…
              </>
            ) : (
              'Simpan batch'
            )}
          </button>
        </div>
      </aside>
    </form>
  );
}

function BatchList({ batches }) {
  if (!batches.length) {
    return (
      <EmptyState icon={Recycle} title="Belum ada batch tercatat.">
        Batch yang disimpan akan muncul di sini.
      </EmptyState>
    );
  }

  return (
    <Table caption="Batch terbaru">
      <thead>
        <tr>
          <Th>No. batch</Th>
          <Th>Tanggal dan shift</Th>
          <Th numeric>Bahan masuk</Th>
          <Th numeric>Recovery</Th>
          <Th numeric>Recovery rate</Th>
          <Th numeric>Saldo solvent</Th>
          <Th>Catatan</Th>
        </tr>
      </thead>
      <tbody>
        {batches.map((b) => (
          <tr key={b.id}>
            <Td>
              <span className="font-mono text-gray-900">{b.batch_number}</span>
              {b.is_continuation && (
                <span className="block text-caption text-gray-600">
                  Lanjutan dari <span className="font-mono">{b.parent_batch_number}</span>
                </span>
              )}
            </Td>
            <Td className="whitespace-nowrap">
              {formatDate(b.date)}
              <span className="block text-caption text-gray-600">{b.shift}</span>
            </Td>
            <Td numeric className="whitespace-nowrap">
              {formatNumber(b.total_input_weight)} <span className="text-gray-600">kg</span>
            </Td>
            <Td numeric className="whitespace-nowrap">
              {formatNumber(b.recovery_weight)} <span className="text-gray-600">kg</span>
            </Td>
            <Td numeric className={`whitespace-nowrap ${b.is_recovery_low ? 'font-semibold text-danger-fg' : ''}`}>
              {formatPercent(b.recovery_rate_percent)}
              {b.is_continuation && <span className="block text-caption font-normal text-gray-600">gabungan</span>}
            </Td>
            <Td numeric className="whitespace-nowrap">
              {formatNumber(b.solvent_balance_after)} <span className="text-gray-600">kg</span>
            </Td>
            <Td className="min-w-[10rem] text-caption">
              {b.is_recovery_low && (
                <span className="flex items-center gap-1 text-danger-fg">
                  <TriangleAlert className="h-3.5 w-3.5 shrink-0" aria-hidden="true" /> Di bawah target
                </span>
              )}
              {b.is_temp_abnormal && (
                <span className="flex items-center gap-1 text-warning-fg">
                  <TriangleAlert className="h-3.5 w-3.5 shrink-0" aria-hidden="true" /> Suhu di luar normal
                </span>
              )}
              {!b.is_recovery_low && !b.is_temp_abnormal && (
                <span className="flex items-center gap-1 text-gray-600">
                  <CircleCheck className="h-3.5 w-3.5 shrink-0" aria-hidden="true" /> Normal
                </span>
              )}
              {b.notes && <span className="mt-0.5 block text-gray-600">{b.notes}</span>}
            </Td>
          </tr>
        ))}
      </tbody>
    </Table>
  );
}

export default function BatchPage() {
  const { can } = useAuth();
  const canFill = can('batch', 'isi');
  const formData = useResource(canFill ? '/batches/create' : null);
  const list = useResource('/batches');

  const handleSaved = () => {
    formData.reload();
    list.reload();
  };

  return (
    <>
      <PageHeader
        title="Input batch"
        description={
          canFill
            ? 'Catat satu batch reclaimer. Selisih bahan, recovery rate, dan saldo solvent dihitung otomatis.'
            : 'Batch reclaimer yang dicatat operator, dengan hasil material balance-nya.'
        }
      />

      {canFill &&
        (formData.data ? (
          <BatchForm formData={formData.data} onSaved={handleSaved} />
        ) : (
          <div className="mt-6">
            {formData.loading ? (
              <LoadingCard label="Memuat form batch…" />
            ) : (
              <ErrorCard title="Form batch belum bisa dimuat." message={formData.error} onRetry={formData.reload} />
            )}
          </div>
        ))}

      <section aria-labelledby="recent-title" className="card mt-6">
        <div className="p-4 sm:p-5">
          <h2 id="recent-title" className="text-h2 font-semibold">
            Batch terbaru
          </h2>
          <p className="mt-1 text-body text-gray-600">20 batch terakhir, urut dari tanggal terbaru.</p>
        </div>
        {list.data ? (
          <BatchList batches={list.data} />
        ) : list.loading ? (
          <p role="status" className="px-4 pb-6 text-body text-gray-600 sm:px-5">
            Memuat daftar batch…
          </p>
        ) : (
          <div className="px-4 pb-5 sm:px-5">
            <Alert tone="danger">
              {list.error}{' '}
              <button type="button" onClick={list.reload} className="focus-ring rounded font-semibold underline">
                Coba lagi
              </button>
            </Alert>
          </div>
        )}
      </section>
    </>
  );
}
