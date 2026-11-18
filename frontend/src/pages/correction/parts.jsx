import { useEffect, useState } from 'react';
import { ChevronLeft, ChevronRight, LoaderCircle, Pencil, Search, Trash2 } from 'lucide-react';
import { useAuth } from '../../auth/AuthContext.jsx';
import { fieldErrorsFrom, useResource } from '../../lib/useResource.js';
import { formatNumber, parseDecimal } from '../../lib/format.js';
import Alert from '../../components/Alert.jsx';
import EmptyState from '../../components/EmptyState.jsx';
import Field from '../../components/Field.jsx';
import Modal from '../../components/Modal.jsx';

// Bagian bersama halaman Koreksi data: filter daftar, halaman, tombol aksi, alasan wajib,
// dialog hapus, dan ringkasan perubahan sebelum disimpan.

const EMPTY_FILTERS = { q: '', from: '', to: '', jenis: '', page: 1 };

/** Daftar dengan filter cari (jeda 0,3 detik), rentang tanggal, dan halaman. */
export function useCorrectionList(basePath) {
  const [filters, setFilters] = useState(EMPTY_FILTERS);
  const [searchText, setSearchText] = useState('');

  useEffect(() => {
    if (searchText === filters.q) return undefined;
    const timer = setTimeout(() => setFilters((f) => ({ ...f, q: searchText, page: 1 })), 300);
    return () => clearTimeout(timer);
  }, [searchText, filters.q]);

  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value && !(key === 'page' && value === 1)) params.set(key, String(value));
  });
  const query = params.toString();
  const list = useResource(`${basePath}${query ? `?${query}` : ''}`);

  return {
    filters,
    list,
    searchText,
    setSearchText,
    update: (changes) => setFilters((f) => ({ ...f, page: 1, ...changes })),
    reset: () => {
      setSearchText('');
      setFilters(EMPTY_FILTERS);
    },
    hasFilter: Boolean(filters.q || filters.from || filters.to || filters.jenis),
  };
}

export function FilterBar({ state, placeholder, children }) {
  const { filters, update, searchText, setSearchText, reset, hasFilter, list } = state;

  return (
    <div className="p-4 sm:p-5">
      <div role="search" aria-label="Filter data" className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div className={`relative ${children ? '' : 'lg:col-span-2'}`}>
          <label htmlFor="koreksi-cari" className="mb-1.5 block text-label font-medium text-gray-800">
            Cari
          </label>
          <Search className="pointer-events-none absolute bottom-3 left-3 h-5 w-5 text-gray-600" aria-hidden="true" />
          <input
            id="koreksi-cari"
            type="search"
            value={searchText}
            onChange={(e) => setSearchText(e.target.value)}
            placeholder={placeholder}
            className="field pl-10"
          />
        </div>
        {children}
        <div>
          <label htmlFor="koreksi-dari" className="mb-1.5 block text-label font-medium text-gray-800">
            Dari tanggal
          </label>
          <input
            id="koreksi-dari"
            type="date"
            max={filters.to || undefined}
            value={filters.from}
            onChange={(e) => update({ from: e.target.value })}
            className="field"
          />
        </div>
        <div>
          <label htmlFor="koreksi-sampai" className="mb-1.5 block text-label font-medium text-gray-800">
            Sampai tanggal
          </label>
          <input
            id="koreksi-sampai"
            type="date"
            min={filters.from || undefined}
            value={filters.to}
            onChange={(e) => update({ to: e.target.value })}
            className="field"
          />
        </div>
      </div>
      <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-body text-gray-600" aria-live="polite">
        <span>{list.meta ? (list.meta.total ? `${list.meta.total} data` : 'Tidak ada data') : ''}</span>
        {hasFilter && (
          <button type="button" onClick={reset} className="focus-ring min-h-11 rounded px-1 font-semibold text-brand-blue-700 hover:underline">
            Hapus filter
          </button>
        )}
      </div>
    </div>
  );
}

/** Isi daftar: memuat, error, kosong, atau tabel dari `children(rows)` beserta halamannya. */
export function ListBody({ state, icon, emptyTitle, children }) {
  const { list, hasFilter, reset, update } = state;

  if (list.error && !list.data) {
    return (
      <div className="px-4 pb-5 sm:px-5">
        <Alert tone="danger">
          {list.error}{' '}
          <button type="button" onClick={list.reload} className="focus-ring rounded font-semibold underline">
            Coba lagi
          </button>
        </Alert>
      </div>
    );
  }
  if (!list.data) {
    return (
      <p role="status" className="px-4 pb-6 text-body text-gray-600 sm:px-5">
        Memuat data…
      </p>
    );
  }
  if (!list.data.length) {
    return hasFilter ? (
      <EmptyState
        icon={Search}
        title="Tidak ada data yang cocok dengan filter."
        action={
          <button type="button" onClick={reset} className="btn-secondary h-11 px-4 text-body">
            Hapus filter
          </button>
        }
      />
    ) : (
      <EmptyState icon={icon} title={emptyTitle} />
    );
  }

  const meta = list.meta;
  return (
    <div className={`transition-opacity ${list.loading ? 'opacity-60' : ''}`} aria-busy={list.loading}>
      {list.error && (
        <div className="px-4 pb-4 sm:px-5">
          <Alert tone="danger">{list.error}</Alert>
        </div>
      )}
      {children(list.data, meta)}
      {meta.last_page > 1 && (
        <nav
          aria-label="Halaman daftar"
          className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 text-body text-gray-600 sm:px-5"
        >
          <span>
            Halaman {meta.page} dari {meta.last_page}
          </span>
          <span className="flex gap-2">
            <button type="button" disabled={meta.page <= 1} onClick={() => update({ page: meta.page - 1 })} className="btn-secondary h-11 px-3 text-body">
              <ChevronLeft className="h-5 w-5" aria-hidden="true" />
              Sebelumnya
            </button>
            <button
              type="button"
              disabled={meta.page >= meta.last_page}
              onClick={() => update({ page: meta.page + 1 })}
              className="btn-secondary h-11 px-3 text-body"
            >
              Berikutnya
              <ChevronRight className="h-5 w-5" aria-hidden="true" />
            </button>
          </span>
        </nav>
      )}
    </div>
  );
}

export function RowActions({ label, onEdit, onDelete }) {
  return (
    <div className="flex justify-end gap-2">
      <button type="button" onClick={onEdit} aria-label={`Koreksi ${label}`} className="btn-secondary h-11 px-3 text-body">
        <Pencil className="h-4 w-4" aria-hidden="true" />
        Koreksi
      </button>
      <button type="button" onClick={onDelete} aria-label={`Hapus ${label}`} className="btn-secondary h-11 px-3 text-body text-danger-fg">
        <Trash2 className="h-4 w-4" aria-hidden="true" />
        Hapus
      </button>
    </div>
  );
}

/**
 * Kirim form koreksi atau hapus. Error per isian dari server ditampilkan di isiannya;
 * error untuk isian yang tidak ada di form (misalnya pallet_ids.0) ditampilkan di atas form.
 */
export function useSubmit(onDone, knownFields) {
  const { request } = useAuth();
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);

  const submit = async (path, options, localErrors = {}) => {
    setFormError('');
    if (Object.keys(localErrors).length) {
      setErrors(localErrors);
      return;
    }
    setErrors({});
    setSaving(true);
    try {
      const response = await request(path, options);
      onDone(response.message);
    } catch (err) {
      const fieldErrors = {};
      Object.entries(fieldErrorsFrom(err)).forEach(([key, message]) => {
        const field = key.replace(/\.\d+$/, '');
        fieldErrors[field] ??= message;
      });
      const unknown = Object.entries(fieldErrors).find(([key]) => !knownFields.includes(key));
      setErrors(fieldErrors);
      if (!Object.keys(fieldErrors).length) setFormError(err.message);
      else if (unknown) setFormError(unknown[1]);
      setSaving(false);
    }
  };

  return { errors, formError, saving, submit, clear: (key) => setErrors((e) => ({ ...e, [key]: undefined })) };
}

export function ReasonField({ id, value, onChange, error, deleting = false }) {
  return (
    <Field
      id={id}
      label={deleting ? 'Alasan menghapus' : 'Alasan koreksi'}
      required
      hint={
        deleting
          ? 'Tercatat di Riwayat koreksi bersama isi data yang dihapus.'
          : 'Tercatat di Riwayat koreksi bersama nilai lama dan nilai baru.'
      }
      error={error}
    >
      {(props) => (
        <textarea {...props} rows={2} maxLength={500} value={value} onChange={(e) => onChange(e.target.value)} className="field h-auto py-2" />
      )}
    </Field>
  );
}

export const REASON_REQUIRED = 'Tulis alasan koreksi, misalnya "Salah ketik hasil solvent, seharusnya 150 kg".';

export function DialogFooter({ onClose, saving, label, savingLabel = 'Menyimpan…', danger = false }) {
  return (
    <div className="flex flex-col-reverse gap-3 pt-1 sm:flex-row sm:justify-end">
      <button type="button" onClick={onClose} disabled={saving} className="btn-secondary h-11 px-4 text-body">
        Batal
      </button>
      <button type="submit" disabled={saving} className={`${danger ? 'btn-danger' : 'btn-primary'} h-11 px-4 text-body`}>
        {saving && <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />}
        {saving ? savingLabel : label}
      </button>
    </div>
  );
}

/** Ringkasan data yang sedang dikoreksi atau dihapus. `items`: [[label, nilai], …] */
export function Summary({ items }) {
  return (
    <dl className="grid grid-cols-1 gap-x-4 gap-y-2 rounded-lg bg-gray-25 p-3 text-body sm:grid-cols-2">
      {items.filter(Boolean).map(([label, value]) => (
        <div key={label}>
          <dt className="text-caption text-gray-600">{label}</dt>
          <dd className="text-gray-900">{value || '–'}</dd>
        </div>
      ))}
    </dl>
  );
}

/**
 * Konfirmasi hapus dengan alasan wajib. `blocked` berisi alasan data ini belum bisa
 * dihapus; dialog lalu hanya menjelaskan dan menutup.
 */
export function DeleteDialog({ title, path, blocked, consequence, children, onClose, onDone }) {
  const [reason, setReason] = useState('');
  const { errors, formError, saving, submit, clear } = useSubmit(onDone, ['reason']);

  const handleSubmit = (e) => {
    e.preventDefault();
    const text = reason.trim();
    submit(path, { method: 'DELETE', body: { reason: text } }, text ? {} : { reason: 'Tulis alasan menghapus data ini, misalnya "Input tercatat dua kali".' });
  };

  return (
    <Modal title={title} onClose={() => !saving && onClose()}>
      {children}
      {blocked ? (
        <>
          <Alert tone="warning" className="mt-4">
            {blocked}
          </Alert>
          <div className="mt-5 flex justify-end">
            <button type="button" onClick={onClose} className="btn-secondary h-11 px-4 text-body">
              Tutup
            </button>
          </div>
        </>
      ) : (
        <form onSubmit={handleSubmit} noValidate className="mt-4 space-y-4">
          {consequence && <Alert tone="warning">{consequence}</Alert>}
          {formError && <Alert tone="danger">{formError}</Alert>}
          <ReasonField
            id="hapus-alasan"
            deleting
            value={reason}
            onChange={(value) => {
              setReason(value);
              clear('reason');
            }}
            error={errors.reason}
          />
          <DialogFooter onClose={onClose} saving={saving} label="Ya, hapus" savingLabel="Menghapus…" danger />
        </form>
      )}
    </Modal>
  );
}

const norm = (v) => {
  if (v === null || v === undefined || v === '') return '';
  if (typeof v === 'number') return Number.isNaN(v) ? 'NaN' : String(Math.round(v * 10000) / 10000);
  return String(v).trim();
};

/**
 * Daftar isian yang berubah, untuk ditampilkan sebelum disimpan.
 * `fields`: [{ key, label, show(value) }]; `before` nilai sekarang, `after` nilai form.
 */
export function changeList(fields, before, after) {
  return fields.flatMap(({ key, label, show = (v) => (v === null || v === undefined || v === '' ? '–' : String(v)) }) =>
    norm(before[key]) === norm(after[key]) ? [] : [{ label, from: show(before[key]), to: show(after[key]) }],
  );
}

export function ChangePreview({ items, note }) {
  if (!items.length) return null;
  return (
    <div className="rounded-lg border border-gray-200 bg-gray-25 p-3 text-body">
      <p className="font-semibold text-gray-900">Yang akan diubah</p>
      <ul className="mt-1 space-y-1">
        {items.map((c) => (
          <li key={c.label} className="break-words">
            <span className="text-gray-600">{c.label}:</span> {c.from} <span aria-hidden="true">→</span>
            <span className="sr-only">menjadi</span> <span className="font-semibold text-gray-900">{c.to}</span>
          </li>
        ))}
      </ul>
      {note && <p className="mt-2 text-caption text-gray-600">{note}</p>}
    </div>
  );
}

/** Input angka dengan satuan di kanan (kg, °C). Koma sebagai desimal. */
export function UnitInput({ unit, ...props }) {
  return (
    <div className="relative">
      <input {...props} type="text" inputMode="decimal" autoComplete="off" className="field pr-12 text-right tabular-nums" />
      <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-body text-gray-600">{unit}</span>
    </div>
  );
}

/** Angka dari API ke isian form: 150.5 menjadi "150,5". */
export function toInput(value) {
  return value === null || value === undefined ? '' : String(value).replace('.', ',');
}

export function kg(value) {
  return value === null || value === undefined || Number.isNaN(value) ? '–' : `${formatNumber(value)} kg`;
}

export function signedKg(value) {
  return `${value > 0 ? '+' : '−'}${formatNumber(Math.abs(value))} kg`;
}

/** Cek isian angka; mengembalikan pesan error atau null. */
export function numberError(raw, { required = true, min = 0 } = {}) {
  const value = parseDecimal(raw);
  if (value === null) return required ? 'Isi dengan angka, contoh 125,5.' : null;
  if (Number.isNaN(value)) return 'Isi dengan angka, contoh 125,5.';
  if (value < min) return min > 0 ? `Angka harus lebih dari ${min}.` : 'Angka tidak boleh negatif.';
  return null;
}
