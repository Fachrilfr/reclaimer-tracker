import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { ChevronLeft, ChevronRight, CircleCheck, Recycle, TriangleAlert } from 'lucide-react';
import { useResource } from '../lib/useResource.js';
import {
  formatDate,
  formatDateRange,
  formatMonth,
  formatMonthShort,
  formatNumber,
  formatPercent,
  parseDate,
  toIso,
} from '../lib/format.js';
import PageHeader from '../components/PageHeader.jsx';
import StatCard from '../components/StatCard.jsx';
import Alert from '../components/Alert.jsx';
import TrendChart from '../components/TrendChart.jsx';
import UnfinishedBadge from '../components/UnfinishedBadge.jsx';
import EmptyState from '../components/EmptyState.jsx';
import StorageBadge, { StorageMeter } from '../components/StorageBadge.jsx';
import { ErrorCard, LoadingCard } from '../components/LoadState.jsx';
import { Table, Td, Th } from '../components/Table.jsx';

const PERIODS = [
  { value: 'day', label: 'Harian' },
  { value: 'month', label: 'Bulanan' },
  { value: 'year', label: 'Tahunan' },
];

function localToday() {
  const now = new Date();
  return toIso(now.getFullYear(), now.getMonth() + 1, now.getDate());
}

// Geser periode satu langkah (FR-47); tanggal di luar bulan dibetulkan oleh Date
function shiftAnchor(iso, period, step) {
  const { y, m, d } = parseDate(iso);
  const date =
    period === 'day' ? new Date(y, m - 1, d + step) : period === 'month' ? new Date(y, m - 1 + step, 1) : new Date(y + step, 0, 1);
  return toIso(date.getFullYear(), date.getMonth() + 1, date.getDate());
}

function periodStart(iso, period) {
  const { y, m } = parseDate(iso);
  return period === 'day' ? iso : period === 'month' ? toIso(y, m, 1) : toIso(y, 1, 1);
}

function periodLabel(iso, period) {
  if (period === 'day') return formatDate(iso);
  if (period === 'month') return formatMonth(iso);
  return String(parseDate(iso).y);
}

function Kg({ value }) {
  return (
    <span className="whitespace-nowrap">
      {formatNumber(value)} <span className="text-gray-600">kg</span>
    </span>
  );
}

function PeriodPicker({ period, anchor, today, onChange }) {
  const next = shiftAnchor(anchor, period, 1);
  const canGoNext = periodStart(next, period) <= today;
  const { y } = parseDate(today);

  return (
    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div className="inline-flex rounded-lg border border-gray-200 bg-white p-1" role="group" aria-label="Jenis periode">
        {PERIODS.map((p) => (
          <button
            key={p.value}
            type="button"
            aria-pressed={period === p.value}
            onClick={() => onChange(p.value, anchor)}
            className={`focus-ring h-11 flex-1 rounded-md px-4 text-body font-semibold transition-colors ${
              period === p.value ? 'bg-brand-blue-600 text-white' : 'text-gray-800 hover:bg-gray-25'
            }`}
          >
            {p.label}
          </button>
        ))}
      </div>

      <div className="flex flex-wrap items-center gap-2 sm:flex-nowrap">
        <button
          type="button"
          onClick={() => onChange(period, shiftAnchor(anchor, period, -1))}
          aria-label="Periode sebelumnya"
          className="btn-secondary h-11 w-11 shrink-0"
        >
          <ChevronLeft className="h-5 w-5" aria-hidden="true" />
        </button>

        <label htmlFor="period-anchor" className="sr-only">
          Pilih {PERIODS.find((p) => p.value === period).label.toLowerCase()}
        </label>
        {period === 'day' && (
          <input
            id="period-anchor"
            type="date"
            max={today}
            value={anchor}
            onChange={(e) => e.target.value && onChange(period, e.target.value)}
            className="field min-w-0 flex-1 md:w-44 md:flex-none"
          />
        )}
        {period === 'month' && (
          <input
            id="period-anchor"
            type="month"
            max={today.slice(0, 7)}
            value={anchor.slice(0, 7)}
            onChange={(e) => e.target.value && onChange(period, `${e.target.value}-01`)}
            className="field min-w-0 flex-1 md:w-44 md:flex-none"
          />
        )}
        {period === 'year' && (
          <select
            id="period-anchor"
            value={parseDate(anchor).y}
            onChange={(e) => onChange(period, toIso(Number(e.target.value), 1, 1))}
            className="field min-w-0 flex-1 md:w-32 md:flex-none"
          >
            {Array.from({ length: 6 }, (_, i) => y - i).map((year) => (
              <option key={year} value={year}>
                {year}
              </option>
            ))}
          </select>
        )}

        <button
          type="button"
          onClick={() => onChange(period, next)}
          disabled={!canGoNext}
          aria-label="Periode berikutnya"
          className="btn-secondary h-11 w-11 shrink-0"
        >
          <ChevronRight className="h-5 w-5" aria-hidden="true" />
        </button>
        <button
          type="button"
          onClick={() => onChange(period, today)}
          disabled={periodStart(anchor, period) === periodStart(today, period)}
          className="btn-secondary h-11 w-full shrink-0 px-4 text-body sm:w-auto"
        >
          Sekarang
        </button>
      </div>
    </div>
  );
}

function chainLabel(chain) {
  return chain.batch_count > 1 ? `${chain.batch_number} + ${chain.batch_count - 1} lanjutan` : chain.batch_number;
}

// Warna seri mengikuti design.md §2.5: output reclaim hijau, losses/limbah oranye
const RECOVERY_COLOR = '#157F3D';
const RESIDUE_COLOR = '#C2410C';

function trendPoints(trend, valueOf) {
  return trend.points.map((p) => ({
    key: p.start,
    label: trend.unit === 'month' ? formatMonthShort(p.start) : String(parseDate(p.start).d),
    longLabel: trend.unit === 'month' ? formatMonth(p.start) : formatDate(p.start),
    value: p.chain_count ? valueOf(p) : null,
    note: p.chain_count ? `${p.chain_count} rangkaian selesai` : 'Belum ada rangkaian selesai',
  }));
}

function TrendCharts({ trend, period, anchor }) {
  const range =
    period === 'day'
      ? `14 hari sampai ${formatDate(anchor)}`
      : period === 'month'
        ? `per hari, ${formatMonth(anchor)}`
        : `per bulan, ${parseDate(anchor).y}`;
  const target = trend.target_recovery_rate;

  return (
    <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
      <section aria-labelledby="trend-recovery-title" className="card">
        <div className="p-4 sm:p-5">
          <h2 id="trend-recovery-title" className="text-h2 font-semibold">
            Tren recovery rate
          </h2>
          <p className="mt-1 text-body text-gray-600">
            {range}. Garis putus-putus adalah target {formatPercent(target)}.
          </p>
        </div>
        <TrendChart
          title={`Tren recovery rate, ${range}`}
          kind="line"
          color={RECOVERY_COLOR}
          points={trendPoints(trend, (p) => p.recovery_rate)}
          format={formatPercent}
          axisFormat={(v) => `${formatNumber(v, 0)}%`}
          reference={{ value: target, label: `Target ${formatNumber(target, 0)}%` }}
          emptyText="Belum ada rangkaian batch yang selesai di rentang ini."
        />
      </section>
      <section aria-labelledby="trend-residue-title" className="card">
        <div className="p-4 sm:p-5">
          <h2 id="trend-residue-title" className="text-h2 font-semibold">
            Tren residu (estimasi)
          </h2>
          <p className="mt-1 text-body text-gray-600">{range}, dalam kg.</p>
        </div>
        <TrendChart
          title={`Tren residu estimasi, ${range}`}
          kind="column"
          color={RESIDUE_COLOR}
          points={trendPoints(trend, (p) => p.residue)}
          format={(v) => `${formatNumber(v)} kg`}
          axisFormat={(v) => formatNumber(v, 0)}
          emptyText="Belum ada rangkaian batch yang selesai di rentang ini."
        />
      </section>
    </div>
  );
}

// Alert toleransi recovery: recovery gabungan periode dibanding target
function ToleranceAlert({ tolerance, target, openCount, label }) {
  return (
    <>
      {tolerance.is_below && (
        <Alert
          tone="danger"
          title={`Recovery ${label} ${formatPercent(tolerance.recovery_rate)}, di bawah target ${formatPercent(target)}.`}
        >
          Selisih {formatNumber(Math.abs(tolerance.gap), 1)} poin. {tolerance.below_target_count} dari{' '}
          {tolerance.chain_count} rangkaian batch selesai di bawah target. Tinjau batch di bagian Perlu ditindak dan
          tabel rincian.
        </Alert>
      )}
      {openCount > 0 && (
        <Alert tone="info">
          {openCount} rangkaian batch belum selesai dan belum dihitung. Angka di bawah hanya dari rangkaian yang sudah
          selesai.
        </Alert>
      )}
    </>
  );
}

function ActionItems({ items, target }) {
  const { pallets, low_recovery_chains: batches } = items;

  if (!pallets.length && !batches.length) {
    return (
      <section aria-labelledby="action-title" className="card flex items-start gap-3 p-4 sm:p-5">
        <CircleCheck className="mt-0.5 h-5 w-5 shrink-0 text-success-fg" aria-hidden="true" />
        <div>
          <h2 id="action-title" className="text-h3 font-semibold">
            Tidak ada yang perlu ditindak
          </h2>
          <p className="text-body text-gray-600">
            Tidak ada pallet B3 mendesak atau kritis, dan semua rangkaian batch yang selesai dalam 14 hari terakhir
            memenuhi target recovery.
          </p>
        </div>
      </section>
    );
  }

  return (
    <section aria-labelledby="action-title" className="card border-urgent-border p-4 sm:p-5">
      <h2 id="action-title" className="flex items-center gap-2 text-h2 font-semibold">
        <TriangleAlert className="h-5 w-5 text-urgent-fg" aria-hidden="true" />
        Perlu ditindak
      </h2>
      <div className="mt-4 grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
          <h3 className="text-h3 font-semibold">Pallet B3 mendesak dan kritis ({pallets.length})</h3>
          {pallets.length ? (
            <ul className="mt-2 divide-y divide-gray-100">
              {pallets.map((p) => (
                <li key={p.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                  <span>
                    <span className="font-mono text-gray-900">{p.pallet_number}</span>
                    <span className="block text-caption text-gray-600">Batas simpan {formatDate(p.expiry_date)}</span>
                  </span>
                  <StorageBadge storage={p.storage} />
                </li>
              ))}
            </ul>
          ) : (
            <p className="mt-2 text-body text-gray-600">Tidak ada.</p>
          )}
        </div>
        <div>
          <h3 className="text-h3 font-semibold">
            Batch di bawah target {formatPercent(target)}, 14 hari terakhir ({batches.length})
          </h3>
          {batches.length ? (
            <ul className="mt-2 divide-y divide-gray-100">
              {batches.map((b) => (
                <li key={b.root_id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                  <span>
                    <span className="font-mono text-gray-900">{chainLabel(b)}</span>
                    <span className="block text-caption text-gray-600">
                      Mulai {formatDate(b.date)} · {b.shift}
                      {b.target_recovery_rate !== target && ` · target saat selesai ${formatPercent(b.target_recovery_rate)}`}
                    </span>
                  </span>
                  <span className="font-semibold tabular-nums text-danger-fg">{formatPercent(b.recovery_rate)}</span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="mt-2 text-body text-gray-600">Tidak ada.</p>
          )}
        </div>
      </div>
    </section>
  );
}

function Breakdown({ breakdown, target }) {
  const { unit, rows } = breakdown;
  const visible = unit === 'chain' ? rows : rows.filter((r) => r.chain_count > 0);

  if (!visible.length) {
    return (
      <EmptyState icon={Recycle} title="Belum ada batch di periode ini.">
        {unit === 'chain'
          ? 'Batch yang dicatat operator di periode ini akan muncul di sini.'
          : 'Belum ada rangkaian batch yang selesai di periode ini.'}
      </EmptyState>
    );
  }

  if (unit === 'chain') {
    return (
      <Table caption="Rincian per rangkaian batch">
        <thead>
          <tr>
            <Th>Rangkaian batch</Th>
            <Th>Shift awal</Th>
            <Th numeric>Bahan masuk</Th>
            <Th numeric>Recovery</Th>
            <Th numeric>Residu estimasi</Th>
            <Th numeric>Recovery rate</Th>
          </tr>
        </thead>
        <tbody>
          {rows.map((c) => (
            <tr key={c.root_id}>
              <Td>
                <span className="font-mono text-gray-900">{c.batch_number}</span>
                {c.batch_count > 1 && (
                  <span className="block text-caption text-gray-600">
                    + lanjutan <span className="font-mono">{c.batch_numbers.slice(1).join(', ')}</span>
                  </span>
                )}
                {!c.is_complete && (
                  <span className="mt-1 block">
                    <UnfinishedBadge />
                  </span>
                )}
              </Td>
              <Td className="whitespace-nowrap">
                {c.shift}
                <span className="block text-caption text-gray-600">{c.operator}</span>
              </Td>
              <Td numeric>
                <Kg value={c.input} />
              </Td>
              <Td numeric>
                <Kg value={c.recovery} />
              </Td>
              <Td numeric>{c.is_complete ? <Kg value={c.residue} /> : '–'}</Td>
              <Td numeric className={c.is_below_target ? 'font-semibold text-danger-fg' : ''}>
                {c.is_complete ? (
                  <>
                    {formatPercent(c.recovery_rate)}
                    {c.batch_count > 1 && (
                      <span className="block text-caption font-normal text-gray-600">gabungan {c.batch_count} batch</span>
                    )}
                  </>
                ) : (
                  <span className="text-caption font-normal text-gray-600">menunggu lanjutan</span>
                )}
              </Td>
            </tr>
          ))}
        </tbody>
      </Table>
    );
  }

  return (
    <Table caption={unit === 'week' ? 'Rincian per minggu' : 'Rincian per bulan'}>
      <thead>
        <tr>
          <Th>{unit === 'week' ? 'Minggu' : 'Bulan'}</Th>
          <Th numeric>Rangkaian</Th>
          <Th numeric>Bahan masuk</Th>
          <Th numeric>Recovery</Th>
          <Th numeric>Residu estimasi</Th>
          <Th numeric>Recovery rate</Th>
        </tr>
      </thead>
      <tbody>
        {visible.map((r) => (
          <tr key={r.start}>
            <Td className="whitespace-nowrap">{unit === 'week' ? formatDateRange(r.start, r.end) : formatMonth(r.start)}</Td>
            <Td numeric>{r.chain_count}</Td>
            <Td numeric>
              <Kg value={r.input} />
            </Td>
            <Td numeric>
              <Kg value={r.recovery} />
            </Td>
            <Td numeric>
              <Kg value={r.residue} />
            </Td>
            <Td numeric className={r.recovery_rate < target ? 'font-semibold text-danger-fg' : ''}>
              {formatPercent(r.recovery_rate)}
            </Td>
          </tr>
        ))}
      </tbody>
    </Table>
  );
}

function ActivePallets({ pallets }) {
  const [onlyAction, setOnlyAction] = useState(false);
  const shown = onlyAction ? pallets.filter((p) => p.storage.needs_action) : pallets;

  return (
    <section aria-labelledby="pallets-title" className="card">
      <div className="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between sm:p-5">
        <div>
          <h2 id="pallets-title" className="text-h2 font-semibold">
            Pallet B3 aktif
          </h2>
          <p className="mt-1 text-body text-gray-600">Belum diangkut, urut dari batas simpan terdekat.</p>
        </div>
        <button
          type="button"
          aria-pressed={onlyAction}
          onClick={() => setOnlyAction((v) => !v)}
          className={`focus-ring h-11 shrink-0 rounded-lg border px-4 text-body font-semibold transition-colors ${
            onlyAction
              ? 'border-brand-blue-600 bg-brand-blue-50 text-brand-blue-700'
              : 'border-gray-200 bg-white text-gray-800 hover:bg-gray-25'
          }`}
        >
          Perlu tindakan saja
        </button>
      </div>
      {shown.length ? (
        <ul className="divide-y divide-gray-100 border-t border-gray-100">
          {shown.map((p) => (
            <li key={p.id} className="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-12 sm:items-center sm:px-5">
              <div className="sm:col-span-3">
                <span className="font-mono text-gray-900">{p.pallet_number}</span>
                <span className="block text-caption text-gray-600">
                  {p.drum_count} drum · mulai {formatDate(p.start_date)}
                </span>
              </div>
              <div className="sm:col-span-5">
                <StorageMeter storage={p.storage} label={`Masa simpan pallet ${p.pallet_number}`} />
                <span className="mt-1 block text-caption text-gray-600">
                  {p.storage.days_elapsed} dari {p.storage.max_days} hari · batas {formatDate(p.expiry_date)}
                </span>
              </div>
              <div className="sm:col-span-4 sm:text-right">
                <StorageBadge storage={p.storage} />
              </div>
            </li>
          ))}
        </ul>
      ) : (
        <p className="border-t border-gray-100 px-4 py-6 text-body text-gray-600 sm:px-5">
          {onlyAction ? 'Tidak ada pallet yang mendesak atau kritis.' : 'Tidak ada pallet B3 yang tersimpan.'}
        </p>
      )}
    </section>
  );
}

export default function DashboardPage() {
  const [params, setParams] = useSearchParams();
  const today = localToday();
  const period = PERIODS.some((p) => p.value === params.get('period')) ? params.get('period') : 'day';
  const rawDate = params.get('date');
  const anchor = rawDate && /^\d{4}-\d{2}-\d{2}$/.test(rawDate) && rawDate <= today ? rawDate : today;

  const { data, error, loading, reload } = useResource(`/dashboard?period=${period}&date=${anchor}`);

  const change = (nextPeriod, nextAnchor) => {
    setParams({ period: nextPeriod, date: nextAnchor > today ? today : nextAnchor }, { replace: true });
  };

  const isCurrent = periodStart(anchor, period) === periodStart(today, period);

  return (
    <>
      <PageHeader title="Dashboard" description={`Material balance reclaimer dan limbah B3, ${periodLabel(anchor, period)}.`} />

      <div className="mt-6">
        <PeriodPicker period={period} anchor={anchor} today={today} onChange={change} />
      </div>

      {!data ? (
        <div className="mt-6">
          {loading ? (
            <LoadingCard label="Memuat dashboard…" />
          ) : (
            <ErrorCard title="Dashboard belum bisa dimuat." message={error} onRetry={reload} />
          )}
        </div>
      ) : (
        <div className={`mt-6 space-y-6 transition-opacity ${loading ? 'opacity-60' : ''}`} aria-busy={loading}>
          {loading && (
            <p role="status" className="sr-only">
              Memuat periode baru…
            </p>
          )}
          {error && (
            <p className="flex items-center gap-2 text-body text-danger-fg">
              <TriangleAlert className="h-4 w-4" aria-hidden="true" />
              {error}
              <button type="button" onClick={reload} className="focus-ring rounded font-semibold underline">
                Coba lagi
              </button>
            </p>
          )}

          <ToleranceAlert
            tolerance={data.tolerance}
            target={data.target_recovery_rate}
            openCount={data.totals.open_chain_count}
            label={periodLabel(anchor, period)}
          />

          <ActionItems items={data.action_items} target={data.target_recovery_rate} />

          <section aria-labelledby="totals-title">
            <h2 id="totals-title" className="sr-only">
              Angka utama
            </h2>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <StatCard
                label="Bahan masuk"
                value={formatNumber(data.totals.input)}
                unit="kg"
                note={
                  data.totals.chain_count
                    ? `${data.totals.chain_count} rangkaian (${data.totals.batch_count} batch) · Tinta X ${formatNumber(data.totals.ink)} + solvent ${formatNumber(data.totals.solvent_used)}`
                    : 'Belum ada rangkaian batch selesai di periode ini'
                }
              />
              <StatCard
                label="Residu (estimasi)"
                value={formatNumber(data.totals.residue)}
                unit="kg"
                note={data.totals.chain_count ? `${formatPercent(data.totals.residue_rate)} dari bahan masuk` : undefined}
              />
              <StatCard
                label="Recovery rate"
                value={data.totals.chain_count ? formatPercent(data.totals.recovery_rate) : '–'}
                note={
                  data.totals.chain_count
                    ? `${data.totals.recovery_rate >= data.target_recovery_rate ? 'Memenuhi' : 'Di bawah'} target ${formatPercent(data.target_recovery_rate)}`
                    : `Target ${formatPercent(data.target_recovery_rate)}`
                }
                tone={
                  !data.totals.chain_count ? 'neutral' : data.totals.recovery_rate >= data.target_recovery_rate ? 'good' : 'bad'
                }
              />
              <StatCard
                label="Saldo solvent"
                value={formatNumber(data.totals.solvent_balance)}
                unit="kg"
                note={isCurrent ? 'Saat ini' : `Akhir ${periodLabel(anchor, period)}`}
              />
            </div>
          </section>

          <TrendCharts trend={data.trend} period={period} anchor={anchor} />

          <section aria-labelledby="shift-title" className="card">
            <div className="p-4 sm:p-5">
              <h2 id="shift-title" className="text-h2 font-semibold">
                Perbandingan per shift
              </h2>
              <p className="mt-1 text-body text-gray-600">
                Rangkaian yang dilanjutkan lintas shift dihitung di shift batch awalnya.
              </p>
            </div>
            <Table caption="Perbandingan per shift">
              <thead>
                <tr>
                  <Th>Shift</Th>
                  <Th numeric>Rangkaian</Th>
                  <Th numeric>Bahan masuk</Th>
                  <Th numeric>Recovery</Th>
                  <Th numeric>Recovery rate</Th>
                </tr>
              </thead>
              <tbody>
                {data.by_shift.map((s) => (
                  <tr key={s.shift}>
                    <Td className="whitespace-nowrap font-medium text-gray-900">{s.shift}</Td>
                    <Td numeric>{s.chain_count}</Td>
                    <Td numeric>
                      <Kg value={s.input} />
                    </Td>
                    <Td numeric>
                      <Kg value={s.recovery} />
                    </Td>
                    <Td
                      numeric
                      className={s.chain_count && s.recovery_rate < data.target_recovery_rate ? 'font-semibold text-danger-fg' : ''}
                    >
                      {s.chain_count ? formatPercent(s.recovery_rate) : '–'}
                    </Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </section>

          <section aria-labelledby="breakdown-title" className="card">
            <div className="p-4 sm:p-5">
              <h2 id="breakdown-title" className="text-h2 font-semibold">
                {data.breakdown.unit === 'chain'
                  ? 'Rangkaian batch'
                  : data.breakdown.unit === 'week'
                    ? 'Per minggu'
                    : 'Per bulan'}
              </h2>
              <p className="mt-1 text-body text-gray-600">
                {data.breakdown.unit === 'chain'
                  ? 'Rangkaian dicatat pada tanggal batch awalnya. Recovery rate gabungan muncul setelah rangkaian selesai.'
                  : 'Hanya rangkaian selesai. Recovery rate dari total recovery dibagi total bahan masuk, bukan rata-rata persentase.'}
              </p>
            </div>
            <Breakdown breakdown={data.breakdown} target={data.target_recovery_rate} />
          </section>

          <ActivePallets pallets={data.active_pallets} />
        </div>
      )}
    </>
  );
}
