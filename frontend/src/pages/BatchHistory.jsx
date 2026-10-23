import { forwardRef, useEffect, useImperativeHandle, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { ChevronLeft, ChevronRight, CircleCheck, Recycle, Search, TriangleAlert } from 'lucide-react';
import { useResource } from '../lib/useResource.js';
import { useSorot, SOROT_CLASS } from '../lib/useSorot.js';
import { formatDate, formatDateRange, formatNumber, formatPercent, parseDate, toIso } from '../lib/format.js';
import Alert from '../components/Alert.jsx';
import EmptyState from '../components/EmptyState.jsx';
import UnfinishedBadge from '../components/UnfinishedBadge.jsx';
import { Table, Td, Th } from '../components/Table.jsx';

// Pilihan rentang tanggal; nilai di URL berbahasa Indonesia supaya link mudah dibaca
const RANGES = [
  { value: 'semua', label: 'Semua waktu' },
  { value: 'hari-ini', label: 'Hari ini' },
  { value: '7-hari', label: '7 hari terakhir' },
  { value: '30-hari', label: '30 hari terakhir' },
  { value: 'bulan-ini', label: 'Bulan ini' },
  { value: 'pilih', label: 'Pilih tanggal' },
];

const STATUSES = [
  { value: '', api: '', label: 'Semua status' },
  { value: 'di-bawah-target', api: 'below_target', label: 'Di bawah toleransi' },
  { value: 'belum-selesai', api: 'in_progress', label: 'Belum selesai' },
  { value: 'suhu-tidak-normal', api: 'temp_abnormal', label: 'Suhu tidak normal' },
  { value: 'sudah-ditinjau', api: 'reviewed', label: 'Sudah ditinjau' },
];

function localToday() {
  const d = new Date();
  return toIso(d.getFullYear(), d.getMonth() + 1, d.getDate());
}

function daysAgo(iso, days) {
  const { y, m, d } = parseDate(iso);
  const date = new Date(y, m - 1, d - days);
  return toIso(date.getFullYear(), date.getMonth() + 1, date.getDate());
}

function rangeDates(range, params, today) {
  switch (range) {
    case 'hari-ini':
      return [today, today];
    case '7-hari':
      return [daysAgo(today, 6), today];
    case '30-hari':
      return [daysAgo(today, 29), today];
    case 'bulan-ini':
      return [`${today.slice(0, 8)}01`, today];
    case 'pilih':
      return [params.get('dari') || '', params.get('sampai') || ''];
    default:
      return ['', ''];
  }
}

function BatchRows({ batches, sorot }) {
  return (
    <Table caption="Riwayat batch">
      <thead>
        <tr>
          <Th>No. batch</Th>
          <Th>Tanggal dan shift</Th>
          <Th numeric>Tinta X masuk</Th>
          <Th numeric>Hasil solvent</Th>
          <Th numeric>Persentase solvent</Th>
          <Th numeric>Saldo solvent</Th>
          <Th>Catatan</Th>
        </tr>
      </thead>
      <tbody>
        {batches.map((b) => {
          const isRoot = b.id === b.chain_root_id;
          const highlighted = sorot === `rangkaian-${b.chain_root_id}`;
          return (
            <tr
              key={b.id}
              data-sorot={isRoot ? `rangkaian-${b.chain_root_id}` : undefined}
              tabIndex={isRoot ? -1 : undefined}
              className={highlighted ? SOROT_CLASS : undefined}
            >
              <Td>
                <span className="whitespace-nowrap font-mono text-gray-900">{b.batch_number}</span>
                {b.is_continuation && (
                  <span className="block text-caption text-gray-600">
                    Lanjutan dari <span className="whitespace-nowrap font-mono">{b.parent_batch_number}</span>
                  </span>
                )}
                {!b.chain_complete && (
                  <span className="mt-1 block">
                    <UnfinishedBadge />
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
                {b.chain_complete ? (
                  <>
                    {formatPercent(b.chain_recovery_rate)}
                    {b.chain_batch_count > 1 && (
                      <span className="block text-caption font-normal text-gray-600">gabungan {b.chain_batch_count} batch</span>
                    )}
                  </>
                ) : (
                  <span className="text-caption font-normal text-gray-600">menunggu lanjutan</span>
                )}
              </Td>
              <Td numeric className="whitespace-nowrap">
                {formatNumber(b.solvent_balance_after)} <span className="text-gray-600">kg</span>
              </Td>
              <Td className="min-w-[10rem] text-caption">
                {b.is_recovery_low && (
                  <span className="flex items-center gap-1 text-danger-fg">
                    <TriangleAlert className="h-3.5 w-3.5 shrink-0" aria-hidden="true" /> Di bawah toleransi
                  </span>
                )}
                {b.is_temp_abnormal && (
                  <span className="flex items-center gap-1 text-warning-fg">
                    <TriangleAlert className="h-3.5 w-3.5 shrink-0" aria-hidden="true" /> Suhu di luar normal
                  </span>
                )}
                {b.chain_complete && !b.is_recovery_low && !b.is_temp_abnormal && (
                  <span className="flex items-center gap-1 text-gray-600">
                    <CircleCheck className="h-3.5 w-3.5 shrink-0" aria-hidden="true" /> Normal
                  </span>
                )}
                {b.review && (
                  <span className="mt-0.5 flex items-start gap-1 text-gray-600">
                    <CircleCheck className="mt-px h-3.5 w-3.5 shrink-0 text-success-fg" aria-hidden="true" />
                    <span>
                      Ditinjau {b.review.by}: {b.review.notes}
                    </span>
                  </span>
                )}
                {b.notes && <span className="mt-0.5 block text-gray-600">{b.notes}</span>}
              </Td>
            </tr>
          );
        })}
      </tbody>
    </Table>
  );
}

/**
 * Riwayat batch dengan filter tanggal, cari, status, dan shift. Filter disimpan di URL
 * (?rentang=&dari=&sampai=&cari=&status=&shift=&halaman=), jadi tetap setelah reload
 * dan bisa dibagikan sebagai link. reload() dipanggil form batch setelah menyimpan.
 */
const BatchHistory = forwardRef(function BatchHistory(_, ref) {
  const [params, setParams] = useSearchParams();
  const today = localToday();

  const range = RANGES.some((r) => r.value === params.get('rentang')) ? params.get('rentang') : 'semua';
  const [from, to] = rangeDates(range, params, today);
  const status = STATUSES.find((s) => s.value === params.get('status')) ?? STATUSES[0];
  const shift = params.get('shift') ?? '';
  const query = params.get('cari') ?? '';
  const page = Math.max(1, Number(params.get('halaman')) || 1);

  const apiParams = new URLSearchParams();
  if (from) apiParams.set('from', from);
  if (to) apiParams.set('to', to);
  if (query.trim()) apiParams.set('q', query.trim());
  if (status.api) apiParams.set('status', status.api);
  if (shift) apiParams.set('shift_id', shift);
  apiParams.set('page', String(page));
  const list = useResource(`/batches?${apiParams}`);
  useImperativeHandle(ref, () => ({ reload: list.reload }), [list.reload]);

  const sorot = useSorot(Boolean(list.data) && !list.loading);

  // Kotak cari: URL diperbarui setelah berhenti mengetik 0,3 detik
  const [searchText, setSearchText] = useState(query);
  useEffect(() => setSearchText(query), [query]);
  useEffect(() => {
    if (searchText === query) return undefined;
    const timer = setTimeout(() => update({ cari: searchText }), 300);
    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchText]);

  // Ubah filter: halaman kembali ke 1 dan sorotan dari dashboard dilepas
  function update(changes, { keepPage = false } = {}) {
    const next = new URLSearchParams(params);
    Object.entries(changes).forEach(([key, value]) => (value ? next.set(key, value) : next.delete(key)));
    if (!keepPage) next.delete('halaman');
    next.delete('sorot');
    setParams(next, { replace: true });
  }

  const hasFilter = range !== 'semua' || query || status.value || shift;
  const reset = () => {
    setSearchText('');
    setParams({}, { replace: true });
  };

  const meta = list.meta;
  const filterSummary = [
    range === 'semua' ? null : from && to ? formatDateRange(from, to) : RANGES.find((r) => r.value === range).label,
    status.value ? status.label.toLowerCase() : null,
    shift && meta?.shifts ? meta.shifts.find((s) => String(s.id) === shift)?.name : null,
    query ? `"${query}"` : null,
  ]
    .filter(Boolean)
    .join(', ');

  return (
    <section aria-labelledby="recent-title" className="card mt-6">
      <div className="p-4 sm:p-5">
        <h2 id="recent-title" className="text-h2 font-semibold">
          Riwayat batch
        </h2>
        <p className="mt-1 text-body text-gray-600">
          Terbaru di atas. Batch awal dan lanjutannya selalu tampil bersama; persentase solvent tampil setelah rangkaian selesai.
        </p>

        <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-12" role="search" aria-label="Filter riwayat batch">
          <div className="lg:col-span-3">
            <label htmlFor="filter-rentang" className="mb-1.5 block text-label font-medium text-gray-800">
              Tanggal
            </label>
            <select
              id="filter-rentang"
              value={range}
              onChange={(e) =>
                update(
                  e.target.value === 'pilih'
                    ? { rentang: 'pilih', dari: from || daysAgo(today, 6), sampai: to || today }
                    : { rentang: e.target.value === 'semua' ? '' : e.target.value, dari: '', sampai: '' },
                )
              }
              className="field"
            >
              {RANGES.map((r) => (
                <option key={r.value} value={r.value}>
                  {r.label}
                </option>
              ))}
            </select>
          </div>
          <div className="relative lg:col-span-4">
            <label htmlFor="filter-cari" className="mb-1.5 block text-label font-medium text-gray-800">
              Cari
            </label>
            <Search className="pointer-events-none absolute bottom-3 left-3 h-5 w-5 text-gray-600" aria-hidden="true" />
            <input
              id="filter-cari"
              type="search"
              value={searchText}
              onChange={(e) => setSearchText(e.target.value)}
              placeholder="No. batch, keterangan, operator"
              aria-describedby="filter-cari-hint"
              className="field pl-10"
            />
            <span id="filter-cari-hint" className="sr-only">
              Hasil diperbarui otomatis setelah berhenti mengetik.
            </span>
          </div>
          <div className="lg:col-span-3">
            <label htmlFor="filter-status" className="mb-1.5 block text-label font-medium text-gray-800">
              Status
            </label>
            <select
              id="filter-status"
              value={status.value}
              onChange={(e) => update({ status: e.target.value })}
              className="field"
            >
              {STATUSES.map((s) => (
                <option key={s.value} value={s.value}>
                  {s.label}
                </option>
              ))}
            </select>
          </div>
          <div className="lg:col-span-2">
            <label htmlFor="filter-shift" className="mb-1.5 block text-label font-medium text-gray-800">
              Shift
            </label>
            <select id="filter-shift" value={shift} onChange={(e) => update({ shift: e.target.value })} className="field">
              <option value="">Semua shift</option>
              {meta?.shifts?.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </select>
          </div>

          {range === 'pilih' && (
            <div className="grid grid-cols-2 gap-3 sm:col-span-2 lg:col-span-6">
              <div>
                <label htmlFor="filter-dari" className="mb-1.5 block text-label font-medium text-gray-800">
                  Dari
                </label>
                <input
                  id="filter-dari"
                  type="date"
                  max={to || today}
                  value={from}
                  onChange={(e) => e.target.value && update({ dari: e.target.value })}
                  className="field"
                />
              </div>
              <div>
                <label htmlFor="filter-sampai" className="mb-1.5 block text-label font-medium text-gray-800">
                  Sampai
                </label>
                <input
                  id="filter-sampai"
                  type="date"
                  min={from}
                  max={today}
                  value={to}
                  onChange={(e) => e.target.value && update({ sampai: e.target.value })}
                  className="field"
                />
              </div>
            </div>
          )}
        </div>

        {(hasFilter || meta) && (
          <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-body text-gray-600" aria-live="polite">
            <span>
              {meta
                ? meta.total_chains
                  ? `${meta.total_chains} rangkaian (${meta.total_batches} batch)`
                  : 'Tidak ada hasil'
                : ''}
              {filterSummary && ` · ${filterSummary}`}
            </span>
            {hasFilter && (
              <button
                type="button"
                onClick={reset}
                className="focus-ring min-h-11 rounded px-1 font-semibold text-brand-blue-700 hover:underline"
              >
                Hapus filter
              </button>
            )}
          </div>
        )}
      </div>

      {list.error && !list.data ? (
        <div className="px-4 pb-5 sm:px-5">
          <Alert tone="danger">
            {list.error}{' '}
            <button type="button" onClick={list.reload} className="focus-ring rounded font-semibold underline">
              Coba lagi
            </button>
          </Alert>
        </div>
      ) : !list.data ? (
        <p role="status" className="px-4 pb-6 text-body text-gray-600 sm:px-5">
          Memuat riwayat batch…
        </p>
      ) : list.data.length ? (
        <div className={`transition-opacity ${list.loading ? 'opacity-60' : ''}`} aria-busy={list.loading}>
          <BatchRows batches={list.data} sorot={sorot} />
          {meta.last_page > 1 && (
            <nav
              aria-label="Halaman riwayat batch"
              className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 text-body text-gray-600 sm:px-5"
            >
              <span>
                Rangkaian {meta.from}–{meta.to} dari {meta.total_chains} · halaman {meta.page} dari {meta.last_page}
              </span>
              <span className="flex gap-2">
                <button
                  type="button"
                  disabled={meta.page <= 1}
                  onClick={() => update({ halaman: String(meta.page - 1) }, { keepPage: true })}
                  className="btn-secondary h-11 px-3 text-body"
                >
                  <ChevronLeft className="h-5 w-5" aria-hidden="true" />
                  Sebelumnya
                </button>
                <button
                  type="button"
                  disabled={meta.page >= meta.last_page}
                  onClick={() => update({ halaman: String(meta.page + 1) }, { keepPage: true })}
                  className="btn-secondary h-11 px-3 text-body"
                >
                  Berikutnya
                  <ChevronRight className="h-5 w-5" aria-hidden="true" />
                </button>
              </span>
            </nav>
          )}
        </div>
      ) : hasFilter ? (
        <EmptyState
          icon={Search}
          title="Tidak ada batch yang cocok dengan filter."
          action={
            <button type="button" onClick={reset} className="btn-secondary h-11 px-4 text-body">
              Hapus filter
            </button>
          }
        >
          Coba perluas rentang tanggal atau ubah status dan kata kunci.
        </EmptyState>
      ) : (
        <EmptyState icon={Recycle} title="Belum ada batch tercatat.">
          Batch yang disimpan akan muncul di sini.
        </EmptyState>
      )}
    </section>
  );
});

export default BatchHistory;
