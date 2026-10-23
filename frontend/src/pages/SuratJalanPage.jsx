import { useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { LoaderCircle, Truck } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { fieldErrorsFrom, useResource } from '../lib/useResource.js';
import { formatDate, formatNumber, parseDecimal } from '../lib/format.js';
import PageHeader from '../components/PageHeader.jsx';
import Field from '../components/Field.jsx';
import Alert from '../components/Alert.jsx';
import EmptyState from '../components/EmptyState.jsx';
import StorageBadge from '../components/StorageBadge.jsx';
import { ErrorCard, LoadingCard } from '../components/LoadState.jsx';

const ACCEPT = '.pdf,.jpg,.jpeg,.png';
const MAX_FILE_MB = 10;

function emptyForm(data, preselected = []) {
  const available = new Set(data.pallets.map((p) => p.id));
  return {
    pallet_ids: preselected.filter((id) => available.has(id)),
    surat_jalan_number: '',
    vendor_id: data.vendors.length === 1 ? String(data.vendors[0].id) : '',
    handover_date: data.today,
    weight_kg: '',
    vendor_officer_name: '',
    notes: '',
  };
}

// Daftar pallet di TPS yang bisa dipilih, paling dekat batas simpan di atas
function PalletPicker({ pallets, drumsPerPallet, selected, onToggle, error, readOnly }) {
  return (
    <fieldset aria-describedby={error ? 'pallet_ids-error' : undefined}>
      <legend className="mb-1.5 text-label font-medium text-gray-800">
        Pallet yang diserahkan {!readOnly && <span className="text-danger-fg" aria-hidden="true">*</span>}
      </legend>
      <ul className="divide-y divide-gray-100 rounded-lg border border-gray-200">
        {pallets.map((p) => {
          const checked = selected.includes(p.id);
          const body = (
            <>
              <span className="min-w-0 flex-1">
                <span className="block font-mono font-medium text-gray-900">{p.pallet_number}</span>
                <span className="block text-caption text-gray-600">
                  {p.drum_count}/{drumsPerPallet} drum · sampai di TPS {formatDate(p.tps.sent_at)} · batas simpan{' '}
                  {formatDate(p.expiry_date)}
                </span>
              </span>
              <StorageBadge storage={p.storage} />
            </>
          );
          return (
            <li key={p.id}>
              {readOnly ? (
                <div className="flex flex-wrap items-center gap-3 px-3 py-2.5">{body}</div>
              ) : (
                <label
                  className={`flex min-h-14 cursor-pointer flex-wrap items-center gap-3 px-3 py-2.5 ${checked ? 'bg-brand-blue-50' : 'hover:bg-gray-25'}`}
                >
                  <input
                    type="checkbox"
                    checked={checked}
                    onChange={() => onToggle(p.id)}
                    className="h-5 w-5 shrink-0 accent-brand-blue-600"
                  />
                  {body}
                </label>
              )}
            </li>
          );
        })}
      </ul>
      {error && (
        <p id="pallet_ids-error" className="mt-1.5 text-caption text-danger-fg">
          {error}
        </p>
      )}
    </fieldset>
  );
}

function SuratJalanForm({ data, preselected, onSaved }) {
  const { request } = useAuth();
  const [form, setForm] = useState(() => emptyForm(data, preselected));
  const [file, setFile] = useState(null);
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [isSaving, setIsSaving] = useState(false);
  const fileRef = useRef(null);

  const set = (field) => (e) => {
    setForm((f) => ({ ...f, [field]: e.target.value }));
    setErrors((errs) => ({ ...errs, [field]: undefined }));
  };

  const toggle = (id) => {
    setForm((f) => ({ ...f, pallet_ids: f.pallet_ids.includes(id) ? f.pallet_ids.filter((x) => x !== id) : [...f.pallet_ids, id] }));
    setErrors((errs) => ({ ...errs, pallet_ids: undefined }));
  };

  const validate = () => {
    const errs = {};
    if (form.pallet_ids.length < data.min_pallets) errs.pallet_ids = `Pilih minimal ${data.min_pallets} pallet yang diserahkan.`;
    if (!form.surat_jalan_number.trim()) errs.surat_jalan_number = 'Isi nomor surat jalan.';
    if (!form.vendor_id) errs.vendor_id = 'Pilih vendor penerima.';
    if (!form.handover_date) errs.handover_date = 'Isi tanggal serah terima.';
    const weight = parseDecimal(form.weight_kg);
    if (weight === null) errs.weight_kg = 'Isi berat dari timbangan vendor dalam kg.';
    else if (Number.isNaN(weight)) errs.weight_kg = 'Isi dengan angka, contoh 450,5.';
    else if (weight <= 0) errs.weight_kg = 'Berat harus lebih dari 0 kg.';
    if (!file) errs.file = 'Unggah bukti surat jalan (PDF, JPG, atau PNG).';
    else if (file.size > MAX_FILE_MB * 1024 * 1024) errs.file = `Ukuran bukti paling besar ${MAX_FILE_MB} MB.`;
    return errs;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setFormError('');
    const errs = validate();
    setErrors(errs);
    if (Object.keys(errs).length) {
      setFormError('Periksa kembali isian yang ditandai.');
      return;
    }

    const body = new FormData();
    form.pallet_ids.forEach((id) => body.append('pallet_ids[]', id));
    body.append('surat_jalan_number', form.surat_jalan_number.trim());
    body.append('vendor_id', form.vendor_id);
    body.append('handover_date', form.handover_date);
    body.append('weight_kg', String(parseDecimal(form.weight_kg)));
    if (form.vendor_officer_name.trim()) body.append('vendor_officer_name', form.vendor_officer_name.trim());
    if (form.notes.trim()) body.append('notes', form.notes.trim());
    body.append('file', file);

    setIsSaving(true);
    try {
      const response = await request('/surat-jalan', { method: 'POST', body });
      onSaved(response.data);
    } catch (err) {
      const serverErrors = fieldErrorsFrom(err);
      // Pesan untuk tiap pallet (pallet_ids.0) ditampilkan di daftar pallet
      const palletError = Object.entries(serverErrors).find(([k]) => k.startsWith('pallet_ids'))?.[1];
      setErrors({ ...serverErrors, pallet_ids: palletError });
      setFormError(Object.keys(serverErrors).length ? 'Periksa kembali isian yang ditandai.' : err.message);
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} noValidate className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-12">
      <section aria-labelledby="pallet-title" className="card p-4 sm:p-5 lg:col-span-7">
        <h2 id="pallet-title" className="text-h2 font-semibold">
          Pallet di TPS
        </h2>
        <p className="mt-1 text-body text-gray-600">
          Minimal {data.min_pallets} pallet per pengangkutan. Setelah surat jalan tersimpan, pallet berstatus diserahkan.
        </p>
        <div className="mt-4">
          <PalletPicker
            pallets={data.pallets}
            drumsPerPallet={data.drums_per_pallet}
            selected={form.pallet_ids}
            onToggle={toggle}
            error={errors.pallet_ids}
          />
        </div>
      </section>

      <section aria-labelledby="sj-title" className="card p-4 sm:p-5 lg:col-span-5">
        <h2 id="sj-title" className="text-h2 font-semibold">
          Data surat jalan
        </h2>
        <div className="mt-4 space-y-4">
          <Field id="surat_jalan_number" label="Nomor surat jalan" required error={errors.surat_jalan_number}>
            {(props) => (
              <input {...props} type="text" maxLength={100} value={form.surat_jalan_number} onChange={set('surat_jalan_number')} className="field font-mono" />
            )}
          </Field>
          <Field id="vendor_id" label="Vendor penerima" required error={errors.vendor_id}>
            {(props) => (
              <select {...props} value={form.vendor_id} onChange={set('vendor_id')} className="field">
                <option value="">Pilih vendor</option>
                {data.vendors.map((v) => (
                  <option key={v.id} value={v.id}>
                    {v.name}
                  </option>
                ))}
              </select>
            )}
          </Field>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field id="handover_date" label="Tanggal serah terima" required error={errors.handover_date}>
              {(props) => (
                <input {...props} type="date" max={data.today} value={form.handover_date} onChange={set('handover_date')} className="field" />
              )}
            </Field>
            <Field id="weight_kg" label="Berat timbangan vendor" required error={errors.weight_kg}>
              {(props) => (
                <div className="relative">
                  <input
                    {...props}
                    type="text"
                    inputMode="decimal"
                    autoComplete="off"
                    value={form.weight_kg}
                    onChange={set('weight_kg')}
                    className="field pr-11 text-right tabular-nums"
                  />
                  <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-body text-gray-600">kg</span>
                </div>
              )}
            </Field>
          </div>
          <Field id="vendor_officer_name" label="Petugas vendor" hint="Opsional." error={errors.vendor_officer_name}>
            {(props) => (
              <input {...props} type="text" maxLength={100} value={form.vendor_officer_name} onChange={set('vendor_officer_name')} className="field" />
            )}
          </Field>
          <Field id="file" label="Bukti surat jalan" required hint={`PDF, JPG, atau PNG, paling besar ${MAX_FILE_MB} MB.`} error={errors.file}>
            {(props) => (
              <input
                {...props}
                ref={fileRef}
                type="file"
                accept={ACCEPT}
                onChange={(e) => {
                  setFile(e.target.files?.[0] ?? null);
                  setErrors((errs) => ({ ...errs, file: undefined }));
                }}
                className="block w-full text-body text-gray-800 file:mr-3 file:h-11 file:rounded-lg file:border file:border-gray-200 file:bg-white file:px-4 file:text-body file:font-medium file:text-gray-800 hover:file:bg-gray-25"
              />
            )}
          </Field>
          <Field id="notes" label="Catatan" hint="Opsional." error={errors.notes}>
            {(props) => (
              <textarea {...props} rows={2} maxLength={500} value={form.notes} onChange={set('notes')} className="field h-auto py-2" />
            )}
          </Field>

          {/* Pesan umum hilang setelah semua isian yang ditandai diperbaiki */}
          {formError && (Object.values(errors).some(Boolean) || !Object.keys(errors).length) && <Alert tone="danger">{formError}</Alert>}
          <button type="submit" disabled={isSaving} className="btn-primary h-12 w-full text-body-lg">
            {isSaving ? (
              <>
                <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />
                Menyimpan…
              </>
            ) : (
              `Simpan surat jalan${form.pallet_ids.length ? ` (${form.pallet_ids.length} pallet)` : ''}`
            )}
          </button>
        </div>
      </section>
    </form>
  );
}

export default function SuratJalanPage() {
  const { can } = useAuth();
  const canFill = can('surat_jalan', 'isi');
  const [params] = useSearchParams();
  const preselected = (params.get('pallet') ?? '').split(',').map(Number).filter(Boolean);
  const { data, error, loading, reload } = useResource('/surat-jalan/create');
  // Disimpan di halaman, bukan di form: form dibuat ulang setelah daftar pallet dimuat ulang
  const [saved, setSaved] = useState(null);

  return (
    <>
      <PageHeader
        title="Surat jalan"
        description={
          canFill
            ? 'Serahkan pallet dari TPS ke vendor. Surat jalan menjadi penutup dokumen limbah B3 dan tersimpan di arsip.'
            : 'Pallet di TPS yang menunggu diserahkan ke vendor. Surat jalan yang sudah tersimpan ada di arsip.'
        }
      />

      {saved ? (
        <div className="mt-6 space-y-4">
          <Alert tone="success" title={`Surat jalan ${saved.number} tersimpan.`}>
            {saved.total_pallets} pallet ({saved.pallets.map((p) => p.pallet_number).join(', ')}) diserahkan ke {saved.vendor}{' '}
            pada {formatDate(saved.handover_date)}, berat {formatNumber(saved.weight_kg)} kg. Pallet ini tidak lagi menerima
            pengingat masa simpan.
          </Alert>
          <div className="flex flex-wrap gap-3">
            <Link to={`/arsip?q=${encodeURIComponent(saved.number)}`} className="btn-secondary h-11 px-4 text-body">
              Lihat di arsip
            </Link>
            <button type="button" onClick={() => setSaved(null)} className="btn-primary h-11 px-4 text-body">
              Buat surat jalan lain
            </button>
          </div>
        </div>
      ) : !data ? (
        <div className="mt-6">
          {loading ? (
            <LoadingCard label="Memuat pallet di TPS…" />
          ) : (
            <ErrorCard title="Data surat jalan belum bisa dimuat." message={error} onRetry={reload} />
          )}
        </div>
      ) : !data.pallets.length && canFill ? (
        <div className="card mt-6">
          <EmptyState
            icon={Truck}
            title="Belum ada pallet di TPS yang bisa diserahkan."
            action={
              <Link to="/limbah-b3?lokasi=semua" className="btn-secondary h-11 px-4 text-body">
                Lihat limbah B3
              </Link>
            }
          >
            Pallet masuk ke TPS saat operator menandai pallet penuh.
          </EmptyState>
        </div>
      ) : canFill ? (
        <SuratJalanForm
          key={data.pallets.map((p) => p.id).join(',')}
          data={data}
          preselected={preselected}
          onSaved={(result) => {
            setSaved(result);
            reload();
          }}
        />
      ) : (
        <section className="card mt-6 p-4 sm:p-5">
          {data.pallets.length ? (
            <PalletPicker pallets={data.pallets} drumsPerPallet={data.drums_per_pallet} selected={[]} readOnly />
          ) : (
            <p className="text-body text-gray-600">Belum ada pallet di TPS yang menunggu diserahkan.</p>
          )}
        </section>
      )}
    </>
  );
}
