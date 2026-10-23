import { useState } from 'react';
import { ChevronLeft, ChevronRight, Droplets, LoaderCircle } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { fieldErrorsFrom, useResource } from '../lib/useResource.js';
import { formatDate, formatNumber, parseDecimal } from '../lib/format.js';
import PageHeader from '../components/PageHeader.jsx';
import Field from '../components/Field.jsx';
import Alert from '../components/Alert.jsx';
import EmptyState from '../components/EmptyState.jsx';
import { ErrorCard, LoadingCard } from '../components/LoadState.jsx';
import { Table, Td, Th } from '../components/Table.jsx';

function emptyForm(meta, keep = {}) {
  return {
    date: keep.date ?? meta.today,
    shift_id: keep.shift_id ?? String(meta.suggested_shift_id ?? meta.shifts[0]?.id ?? ''),
    weight: '',
    purpose: '',
    notes: '',
  };
}

// Form pengambilan solvent dari saldo. Tidak terkait batch dan tidak masuk ke mesin.
function UsageForm({ meta, onSaved }) {
  const { request } = useAuth();
  const [form, setForm] = useState(() => emptyForm(meta));
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [saved, setSaved] = useState(null);
  const [isSaving, setIsSaving] = useState(false);

  const set = (field) => (e) => {
    setForm((f) => ({ ...f, [field]: e.target.value }));
    setErrors((errs) => ({ ...errs, [field]: undefined }));
    setSaved(null);
  };

  const weight = parseDecimal(form.weight);
  const validWeight = weight !== null && !Number.isNaN(weight) ? weight : 0;
  const balanceAfter = Math.round((meta.balance - validWeight) * 100) / 100;

  const validate = () => {
    const errs = {};
    if (!form.date) errs.date = 'Isi tanggal pemakaian.';
    if (!form.shift_id) errs.shift_id = 'Pilih shift.';
    if (weight === null) errs.weight = 'Isi berat solvent yang diambil dalam kg.';
    else if (Number.isNaN(weight)) errs.weight = 'Isi dengan angka, contoh 125,5.';
    else if (weight <= 0) errs.weight = 'Berat solvent harus lebih dari 0 kg.';
    else if (weight > meta.balance) {
      errs.weight = `Solvent yang diambil melebihi saldo (${formatNumber(meta.balance)} kg). Periksa kembali angkanya.`;
    }
    if (!form.purpose.trim()) errs.purpose = 'Isi keperluan pemakaian solvent, contoh: pencucian mesin cetak.';
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
      const response = await request('/solvent-usages', {
        method: 'POST',
        body: {
          date: form.date,
          shift_id: Number(form.shift_id),
          weight,
          purpose: form.purpose.trim(),
          notes: form.notes.trim() || null,
        },
      });
      setSaved(response.data);
      setForm((f) => emptyForm(meta, { date: f.date, shift_id: f.shift_id }));
      onSaved();
    } catch (err) {
      const serverErrors = fieldErrorsFrom(err);
      setErrors(serverErrors);
      setFormError(Object.keys(serverErrors).length ? 'Periksa kembali isian yang ditandai.' : err.message);
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} noValidate className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-12">
      <section aria-labelledby="usage-title" className="card p-4 sm:p-5 lg:col-span-8">
        <h2 id="usage-title" className="text-h2 font-semibold">
          Ambil solvent dari saldo
        </h2>
        <p className="mt-1 text-body text-gray-600">
          Solvent ini tidak masuk ke mesin reclaimer dan tidak dihitung sebagai bahan masuk batch. Saldo langsung
          berkurang.
        </p>

        <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
          <Field id="date" label="Tanggal" required error={errors.date}>
            {(props) => <input {...props} type="date" max={meta.today} value={form.date} onChange={set('date')} className="field" />}
          </Field>
          <Field id="shift_id" label="Shift" required error={errors.shift_id}>
            {(props) => (
              <select {...props} value={form.shift_id} onChange={set('shift_id')} className="field">
                {meta.shifts.map((shift) => (
                  <option key={shift.id} value={shift.id}>
                    {shift.name}
                  </option>
                ))}
              </select>
            )}
          </Field>
          <Field id="weight" label="Berat diambil" required hint={`Saldo saat ini ${formatNumber(meta.balance)} kg.`} error={errors.weight}>
            {(props) => (
              <div className="relative">
                <input
                  {...props}
                  type="text"
                  inputMode="decimal"
                  autoComplete="off"
                  value={form.weight}
                  onChange={set('weight')}
                  className="field pr-11 text-right tabular-nums"
                />
                <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-body text-gray-600">kg</span>
              </div>
            )}
          </Field>
        </div>

        <div className="mt-4 grid grid-cols-1 gap-4">
          <Field id="purpose" label="Keperluan" required hint="Untuk apa solvent ini dipakai." error={errors.purpose}>
            {(props) => <input {...props} type="text" maxLength={150} value={form.purpose} onChange={set('purpose')} className="field" />}
          </Field>
          <Field id="notes" label="Catatan" hint="Opsional." error={errors.notes}>
            {(props) => (
              <textarea {...props} rows={2} maxLength={500} value={form.notes} onChange={set('notes')} className="field h-auto py-2" />
            )}
          </Field>
        </div>
      </section>

      <aside aria-labelledby="balance-title" className="lg:col-span-4">
        <div className="card p-4 sm:p-5 lg:sticky lg:top-24">
          <h2 id="balance-title" className="flex items-center gap-2 text-h2 font-semibold">
            <Droplets className="h-5 w-5 text-gray-600" aria-hidden="true" />
            Saldo solvent
          </h2>
          <dl className="mt-4 space-y-2 text-body tabular-nums" aria-live="polite">
            <div className="flex justify-between gap-4">
              <dt className="text-gray-600">Saldo saat ini</dt>
              <dd className="whitespace-nowrap font-medium text-gray-900">{formatNumber(meta.balance)} kg</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-gray-600">Diambil</dt>
              <dd className="whitespace-nowrap font-medium text-gray-900">
                {validWeight > 0 ? '−' : ''}
                {formatNumber(validWeight)} kg
              </dd>
            </div>
            <div className="flex justify-between gap-4 border-t border-gray-100 pt-2">
              <dt className="text-gray-600">Saldo sesudah</dt>
              <dd className={`whitespace-nowrap font-semibold ${balanceAfter < 0 ? 'text-danger-fg' : 'text-gray-900'}`}>
                {formatNumber(balanceAfter)} kg
              </dd>
            </div>
          </dl>
          <p className="mt-3 text-caption text-gray-600">Saldo bertambah dari hasil solvent setiap batch.</p>

          {/* Pesan umum hilang setelah semua isian yang ditandai diperbaiki */}
          {formError && (Object.values(errors).some(Boolean) || !Object.keys(errors).length) && (
            <Alert tone="danger" className="mt-4">
              {formError}
            </Alert>
          )}
          {saved && (
            <Alert tone="success" className="mt-4" title="Pemakaian solvent tersimpan.">
              {formatNumber(saved.weight)} kg diambil. Saldo sekarang {formatNumber(saved.balance_after)} kg.
            </Alert>
          )}

          <button type="submit" disabled={isSaving} className="btn-primary mt-4 h-12 w-full text-body-lg">
            {isSaving ? (
              <>
                <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />
                Menyimpan…
              </>
            ) : (
              'Simpan pemakaian'
            )}
          </button>
        </div>
      </aside>
    </form>
  );
}

function UsageHistory({ list, page, onPage }) {
  const { data, meta } = list;

  return (
    <section aria-labelledby="history-title" className="card mt-6">
      <div className="px-4 pt-4 sm:px-5 sm:pt-5">
        <h2 id="history-title" className="text-h2 font-semibold">
          Riwayat pemakaian solvent
        </h2>
        {meta && <p className="mt-1 text-body text-gray-600">{meta.total} catatan, terbaru di atas.</p>}
      </div>

      {!data.length ? (
        <EmptyState icon={Droplets} title="Belum ada solvent yang diambil dari saldo.">
          Catatan muncul di sini setelah operator menyimpan pemakaian solvent.
        </EmptyState>
      ) : (
        <div className={`mt-4 transition-opacity ${list.loading ? 'opacity-60' : ''}`} aria-busy={list.loading}>
          <Table caption="Riwayat pemakaian solvent">
            <thead>
              <tr>
                <Th>Tanggal</Th>
                <Th>Keperluan</Th>
                <Th numeric>Diambil</Th>
                <Th numeric>Saldo sesudah</Th>
                <Th>Dicatat oleh</Th>
              </tr>
            </thead>
            <tbody>
              {data.map((u) => (
                <tr key={u.id}>
                  <Td className="whitespace-nowrap">
                    {formatDate(u.date)}
                    <span className="block text-caption text-gray-600">{u.shift}</span>
                  </Td>
                  <Td>
                    {u.purpose}
                    {u.notes && <span className="block text-caption text-gray-600">{u.notes}</span>}
                  </Td>
                  <Td numeric className="whitespace-nowrap">
                    {formatNumber(u.weight)} <span className="text-gray-600">kg</span>
                  </Td>
                  <Td numeric className="whitespace-nowrap">
                    {formatNumber(u.balance_after)} <span className="text-gray-600">kg</span>
                  </Td>
                  <Td className="whitespace-nowrap">{u.used_by}</Td>
                </tr>
              ))}
            </tbody>
          </Table>
          {meta.last_page > 1 && (
            <nav
              aria-label="Halaman riwayat pemakaian solvent"
              className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 text-body text-gray-600 sm:px-5"
            >
              <span>
                Halaman {meta.page} dari {meta.last_page}
              </span>
              <span className="flex gap-2">
                <button type="button" disabled={page <= 1} onClick={() => onPage(page - 1)} className="btn-secondary h-11 px-3 text-body">
                  <ChevronLeft className="h-5 w-5" aria-hidden="true" />
                  Sebelumnya
                </button>
                <button
                  type="button"
                  disabled={page >= meta.last_page}
                  onClick={() => onPage(page + 1)}
                  className="btn-secondary h-11 px-3 text-body"
                >
                  Berikutnya
                  <ChevronRight className="h-5 w-5" aria-hidden="true" />
                </button>
              </span>
            </nav>
          )}
        </div>
      )}
    </section>
  );
}

export default function SolventPage() {
  const { can } = useAuth();
  const canFill = can('solvent', 'isi');
  const [page, setPage] = useState(1);
  const list = useResource(`/solvent-usages?page=${page}`);

  const handleSaved = () => {
    if (page === 1) list.reload();
    else setPage(1);
  };

  return (
    <>
      <PageHeader
        title="Pemakaian solvent"
        description={
          canFill
            ? 'Catat solvent yang diambil dari saldo hasil solvent. Tidak terkait batch dan tidak dihitung sebagai bahan masuk.'
            : 'Solvent yang diambil operator dari saldo hasil solvent.'
        }
      />

      {!list.data ? (
        <div className="mt-6">
          {list.loading ? (
            <LoadingCard label="Memuat pemakaian solvent…" />
          ) : (
            <ErrorCard title="Pemakaian solvent belum bisa dimuat." message={list.error} onRetry={list.reload} />
          )}
        </div>
      ) : (
        <>
          {canFill && <UsageForm meta={list.meta} onSaved={handleSaved} />}
          <UsageHistory list={list} page={page} onPage={setPage} />
        </>
      )}
    </>
  );
}
