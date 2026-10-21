import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Biohazard, ChevronRight, CircleCheck, Recycle, TriangleAlert, Wrench } from 'lucide-react';
import { useResource } from '../lib/useResource.js';
import { SOROT_CLASS, useSorot } from '../lib/useSorot.js';
import {
  formatDate,
  formatDateRange,
  formatMonth,
  formatMonthShort,
  formatNumber,
  formatPercent,
  parseDate,
} from '../lib/format.js';
import PageHeader from '../components/PageHeader.jsx';
import StatCard from '../components/StatCard.jsx';
import PeriodPicker, { localToday, periodLabel, periodStart } from '../components/PeriodPicker.jsx';
import TrendChart, { FLAG_COLOR, FLAG_TRIANGLE } from '../components/TrendChart.jsx';
import UnfinishedBadge from '../components/UnfinishedBadge.jsx';
import EmptyState from '../components/EmptyState.jsx';
import StorageBadge, { StorageMeter, TpsBadge } from '../components/StorageBadge.jsx';
import { useAuth } from '../auth/AuthContext.jsx';
import Alert from '../components/Alert.jsx';
import ReviewDialog from '../components/ReviewDialog.jsx';
import { ErrorCard, LoadingCard } from '../components/LoadState.jsx';
import { Table, Td, Th } from '../components/Table.jsx';

const PERIODS = ['day', 'month', 'year'];

function Kg({ value }) {
  return (
    <span className="whitespace-nowrap">
      {formatNumber(value)} <span className="text-gray-600">kg</span>
    </span>
  );
}

function chainLabel(chain) {
  return chain.batch_count > 1 ? `${chain.batch_number} + ${chain.batch_count - 1} lanjutan` : chain.batch_number;
}

// Warna seri mengikuti design.md §2.5: output reclaim hijau, losses/limbah oranye
const RECOVERY_COLOR = '#157F3D';
const RESIDUE_COLOR = '#C2410C';

function trendPoints(trend, valueOf, flagOf = () => false) {
  return trend.points.map((p) => ({
    key: p.start,
    label: trend.unit === 'month' ? formatMonthShort(p.start) : String(parseDate(p.start).d),
    longLabel: trend.unit === 'month' ? formatMonth(p.start) : formatDate(p.start),
    value: p.chain_count ? valueOf(p) : null,
    flagged: Boolean(p.chain_count) && flagOf(p),
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
          <p className="mt-1 text-body text-gray-600">{range}.</p>
          <ul className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-caption text-gray-600" aria-label="Keterangan grafik">
            <li className="flex items-center gap-1.5">
              <svg width="12" height="12" aria-hidden="true">
                <circle cx="6" cy="6" r="4.5" fill={RECOVERY_COLOR} />
              </svg>
              Memenuhi target
            </li>
            <li className="flex items-center gap-1.5">
              <svg width="12" height="12" aria-hidden="true">
                <path d={FLAG_TRIANGLE(6, 6, 5.5)} fill={FLAG_COLOR} />
              </svg>
              Di bawah target
            </li>
            <li className="flex items-center gap-1.5">
              <svg width="18" height="12" aria-hidden="true">
                <line x1="0" x2="18" y1="6" y2="6" stroke="#2B333D" strokeWidth="1.5" strokeDasharray="4 3" />
              </svg>
              Target {formatPercent(target)}
            </li>
          </ul>
        </div>
        <TrendChart
          title={`Tren recovery rate, ${range}`}
          kind="line"
          color={RECOVERY_COLOR}
          points={trendPoints(trend, (p) => p.recovery_rate, (p) => p.recovery_rate < target)}
          flagLabel="di bawah target"
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

const PREVIEW_LIMIT = 3;

const STATUS_PILL = {
  ok: 'border-success-border bg-success-bg text-success-fg',
  warn: 'border-urgent-border bg-urgent-bg text-urgent-fg',
  bad: 'border-danger-border bg-danger-bg text-danger-fg',
  neutral: 'border-gray-200 bg-gray-25 text-gray-600',
};

// Kartu peringatan: judul + status singkat, paling banyak 3 item, lalu tautan ke daftar lengkap
function AlertCard({ id, icon: Icon, title, status, tone, needsAction, children, more, link }) {
  return (
    <section
      aria-labelledby={id}
      className={`card flex flex-col p-4 sm:p-5 ${needsAction ? (tone === 'bad' ? 'border-danger-border' : 'border-urgent-border') : ''}`}
    >
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 id={id} className="flex items-center gap-2 text-h3 font-semibold">
          <Icon className="h-5 w-5 shrink-0 text-gray-600" aria-hidden="true" />
          {title}
        </h2>
        <span className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-caption font-medium ${STATUS_PILL[tone]}`}>
          {needsAction ? (
            <TriangleAlert className="h-3.5 w-3.5" aria-hidden="true" />
          ) : (
            <CircleCheck className="h-3.5 w-3.5" aria-hidden="true" />
          )}
          {status}
        </span>
      </div>
      <div className="mt-3 flex-1">{children}</div>
      {(more > 0 || link) && (
        <div className="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3 text-body">
          <span className="text-gray-600">{more > 0 ? `${more} lainnya tidak ditampilkan.` : ''}</span>
          {link && (
            <a href={link.href} className="focus-ring inline-flex min-h-11 items-center rounded font-semibold text-brand-blue-700 hover:underline">
              {link.label}
            </a>
          )}
        </div>
      )}
    </section>
  );
}

// FR-45 bagian limbah B3: pallet mendesak dan kritis
// Dua tahap limbah B3. Operator: dari area reclaimer ke TPS (batas kirim). HSE dan role
// dengan akses Limbah B3: masa simpan di TPS sampai diserahkan ke vendor.
const B3_VIEWS = {
  tps: {
    title: 'Kirim ke TPS',
    status: (p) => p.tps,
    badge: (p) => <TpsBadge tps={p.tps} />,
    due: (p) => `Batas kirim ${formatDate(p.tps_due_date)}`,
    actionNoun: 'perlu dikirim',
    noAction: 'Tidak ada pallet yang mendesak atau lewat batas kirim ke TPS.',
    listTitle: 'Pallet belum dikirim ke TPS',
    listDescription: 'Masih di area reclaimer, urut dari batas kirim terdekat.',
    meterLabel: 'Batas kirim ke TPS',
    empty: 'Semua pallet sudah dikirim ke TPS.',
  },
  storage: {
    title: 'Limbah B3 di TPS',
    status: (p) => p.storage,
    badge: (p) => <StorageBadge storage={p.storage} />,
    due: (p) => `Batas simpan ${formatDate(p.expiry_date)}`,
    actionNoun: 'perlu ditindak',
    noAction: 'Tidak ada pallet yang mendesak atau kritis.',
    listTitle: 'Pallet B3 aktif',
    listDescription: 'Belum diangkut vendor, urut dari batas simpan terdekat.',
    meterLabel: 'Masa simpan',
    empty: 'Tidak ada pallet B3 yang tersimpan.',
  },
};

// Item peringatan yang bisa diklik: membuka baris masalahnya di halaman tujuan (?sorot=...)
function AlertItemLink({ to, children }) {
  return (
    <Link
      to={to}
      className="focus-ring -mx-2 flex items-center gap-2 rounded-lg px-2 py-2 transition-colors hover:bg-gray-25"
    >
      <span className="flex min-w-0 flex-1 flex-wrap items-center justify-between gap-2">{children}</span>
      <ChevronRight className="h-4 w-4 shrink-0 text-gray-600" aria-hidden="true" />
    </Link>
  );
}

function B3AlertCard({ pallets, view, linkFor, machine }) {
  const urgent = pallets.length > 0;
  const hasCritical = pallets.some((p) => view.status(p).level === 'kritis');

  return (
    <AlertCard
      id="alert-b3-title"
      icon={Biohazard}
      title={view.title}
      status={urgent ? `${pallets.length} pallet ${view.actionNoun}` : 'Aman'}
      tone={!urgent ? 'ok' : hasCritical ? 'bad' : 'warn'}
      needsAction={urgent}
      more={pallets.length - PREVIEW_LIMIT}
      link={{ href: '#pallets-title', label: 'Lihat semua pallet' }}
    >
      {machine?.status === 'maintenance' && (
        <p className="mb-3 flex items-start gap-1.5 rounded-lg bg-warning-bg px-3 py-2 text-caption text-warning-fg">
          <Wrench className="mt-px h-3.5 w-3.5 shrink-0" aria-hidden="true" />
          Mesin {machine.code} sedang maintenance: pengiriman residu ke TPS ikut terhenti. Batas kirim pallet tetap
          berjalan.
        </p>
      )}
      {urgent ? (
        <ul className="divide-y divide-gray-100">
          {pallets.slice(0, PREVIEW_LIMIT).map((p) => (
            <li key={p.id}>
              <AlertItemLink to={linkFor(p)}>
                <span>
                  <span className="font-mono text-gray-900">{p.pallet_number}</span>
                  <span className="block text-caption text-gray-600">{view.due(p)}</span>
                </span>
                {view.badge(p)}
              </AlertItemLink>
            </li>
          ))}
        </ul>
      ) : (
        <p className="text-body text-gray-600">{view.noAction}</p>
      )}
    </AlertCard>
  );
}

// Alert toleransi recovery: recovery gabungan periode dibanding target, dan rangkaian
// di bawah target dalam 14 hari terakhir (FR-45)
function RecoveryAlertCard({ tolerance, target, chains, openCount, label, onReviewed }) {
  const { can } = useAuth();
  const canReview = can('dashboard', 'tinjau');
  const [reviewing, setReviewing] = useState(null);
  const below = tolerance.is_below;
  const needsAction = below || chains.length > 0;

  return (
    <AlertCard
      id="alert-recovery-title"
      icon={Recycle}
      title="Target recovery"
      status={
        below
          ? 'Di bawah target'
          : chains.length
            ? `${chains.length} rangkaian perlu ditinjau`
            : tolerance.has_data
              ? 'Memenuhi target'
              : 'Belum ada data'
      }
      tone={below ? 'bad' : chains.length ? 'warn' : tolerance.has_data ? 'ok' : 'neutral'}
      needsAction={needsAction}
      more={chains.length - PREVIEW_LIMIT}
      link={{ href: '#breakdown-title', label: 'Lihat rincian' }}
    >
      <p className="text-body text-gray-800">
        {tolerance.has_data ? (
          <>
            Recovery {label}{' '}
            <span className={`font-semibold tabular-nums ${below ? 'text-danger-fg' : 'text-gray-900'}`}>
              {formatPercent(tolerance.recovery_rate)}
            </span>
            , target {formatPercent(target)}
            {below && <> (kurang {formatNumber(Math.abs(tolerance.gap), 1)} poin)</>}.
          </>
        ) : (
          <>Belum ada rangkaian batch selesai di {label}.</>
        )}
        {openCount > 0 && (
          <span className="block text-caption text-gray-600">
            {openCount} rangkaian belum selesai, belum dihitung.
          </span>
        )}
      </p>

      <h3 className="mt-3 text-label font-medium text-gray-600">Rangkaian di bawah target, 14 hari terakhir</h3>
      {chains.length ? (
        <ul className="mt-1 divide-y divide-gray-100">
          {chains.slice(0, PREVIEW_LIMIT).map((c) => (
            <li key={c.root_id} className="flex flex-wrap items-center gap-x-3">
              <div className="min-w-0 flex-1">
                <AlertItemLink
                  to={
                    can('batch')
                      ? `/batch?rentang=pilih&dari=${c.date}&sampai=${c.date}&status=di-bawah-target&sorot=rangkaian-${c.root_id}`
                      : `/dashboard?period=day&date=${c.date}&sorot=rangkaian-${c.root_id}`
                  }
                >
                  <span>
                    <span className="font-mono text-gray-900">{chainLabel(c)}</span>
                    <span className="block text-caption text-gray-600">
                      Mulai {formatDate(c.date)} · {c.shift}
                      {c.target_recovery_rate !== target && ` · target saat itu ${formatPercent(c.target_recovery_rate)}`}
                      {!canReview && ' · menunggu tinjauan supervisor'}
                    </span>
                  </span>
                  <span className="font-semibold tabular-nums text-danger-fg">{formatPercent(c.recovery_rate)}</span>
                </AlertItemLink>
              </div>
              {canReview && (
                <button
                  type="button"
                  onClick={() => setReviewing(c)}
                  aria-label={`Tinjau ${c.batch_number}`}
                  className="btn-secondary h-11 shrink-0 px-4 text-body"
                >
                  Tinjau
                </button>
              )}
            </li>
          ))}
        </ul>
      ) : (
        <p className="mt-1 text-body text-gray-600">Tidak ada.</p>
      )}
      {reviewing && (
        <ReviewDialog
          chain={reviewing}
          label={chainLabel(reviewing)}
          onClose={() => setReviewing(null)}
          onReviewed={(message) => {
            setReviewing(null);
            onReviewed(message);
          }}
        />
      )}
    </AlertCard>
  );
}

// Catatan tinjauan supervisor, di rincian rangkaian dan daftar batch
function ReviewNote({ review }) {
  if (!review) return null;
  return (
    <span className="mt-1 flex items-start gap-1 text-caption font-normal text-gray-600">
      <CircleCheck className="mt-px h-3.5 w-3.5 shrink-0 text-success-fg" aria-hidden="true" />
      <span>
        Ditinjau {review.by}: {review.notes}
      </span>
    </span>
  );
}

function Breakdown({ breakdown, target, sorot }) {
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
            <Th numeric>Tinta X masuk</Th>
            <Th numeric>Recovery</Th>
            <Th numeric>Residu estimasi</Th>
            <Th numeric>Recovery rate</Th>
          </tr>
        </thead>
        <tbody>
          {rows.map((c) => (
            <tr
              key={c.root_id}
              data-sorot={`rangkaian-${c.root_id}`}
              tabIndex={-1}
              className={sorot === `rangkaian-${c.root_id}` ? SOROT_CLASS : undefined}
            >
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
                    <ReviewNote review={c.review} />
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
          <Th numeric>Tinta X masuk</Th>
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

function ActivePallets({ pallets, view, sorot }) {
  const [onlyAction, setOnlyAction] = useState(false);
  const shown = onlyAction ? pallets.filter((p) => view.status(p).needs_action) : pallets;

  return (
    <section aria-labelledby="pallets-title" className="card">
      <div className="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between sm:p-5">
        <div>
          <h2 id="pallets-title" className="text-h2 font-semibold">
            {view.listTitle}
          </h2>
          <p className="mt-1 text-body text-gray-600">{view.listDescription}</p>
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
            <li
              key={p.id}
              data-sorot={`pallet-${p.id}`}
              tabIndex={-1}
              className={`grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-12 sm:items-center sm:px-5 ${
                sorot === `pallet-${p.id}` ? SOROT_CLASS : ''
              }`}
            >
              <div className="sm:col-span-3">
                <span className="font-mono text-gray-900">{p.pallet_number}</span>
                <span className="block text-caption text-gray-600">
                  {p.drum_count} drum · mulai {formatDate(p.start_date)}
                </span>
              </div>
              <div className="sm:col-span-5">
                <StorageMeter storage={view.status(p)} label={`${view.meterLabel} pallet ${p.pallet_number}`} />
                <span className="mt-1 block text-caption text-gray-600">
                  {view.status(p).days_elapsed} dari {view.status(p).max_days} hari · {view.due(p).replace(/^./, (c) => c.toLowerCase())}
                </span>
              </div>
              <div className="sm:col-span-4 sm:text-right">
                {view.badge(p)}
              </div>
            </li>
          ))}
        </ul>
      ) : (
        <p className="border-t border-gray-100 px-4 py-6 text-body text-gray-600 sm:px-5">
          {onlyAction ? view.noAction : view.empty}
        </p>
      )}
    </section>
  );
}

export default function DashboardPage() {
  const { can } = useAuth();
  const b3View = can('limbah_b3') ? B3_VIEWS.storage : B3_VIEWS.tps;
  const [params, setParams] = useSearchParams();
  const today = localToday();
  const period = PERIODS.includes(params.get('period')) ? params.get('period') : 'day';
  const rawDate = params.get('date');
  const anchor = rawDate && /^\d{4}-\d{2}-\d{2}$/.test(rawDate) && rawDate <= today ? rawDate : today;

  const { data, error, loading, reload } = useResource(`/dashboard?period=${period}&date=${anchor}`);
  const sorot = useSorot(Boolean(data) && !loading);
  const [reviewMessage, setReviewMessage] = useState('');

  const change = (nextPeriod, nextAnchor) => {
    setParams({ period: nextPeriod, date: nextAnchor > today ? today : nextAnchor }, { replace: true });
  };

  const isCurrent = periodStart(anchor, period) === periodStart(today, period);

  return (
    <>
      <PageHeader title="Dashboard" description={`Material balance reclaimer dan limbah B3, ${periodLabel(anchor, period)}.`} />

      <div className="mt-6">
        <PeriodPicker periods={PERIODS} period={period} anchor={anchor} today={today} onChange={change} />
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
          {reviewMessage && <Alert tone="success">{reviewMessage}</Alert>}
          {error && (
            <p className="flex items-center gap-2 text-body text-danger-fg">
              <TriangleAlert className="h-4 w-4" aria-hidden="true" />
              {error}
              <button type="button" onClick={reload} className="focus-ring rounded font-semibold underline">
                Coba lagi
              </button>
            </p>
          )}

          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <B3AlertCard
              view={b3View}
              machine={data.machine}
              pallets={b3View === B3_VIEWS.tps ? data.action_items.tps_pallets : data.action_items.pallets}
              linkFor={(p) =>
                can('limbah_b3')
                  ? `/limbah-b3?lokasi=semua&sorot=pallet-${p.id}`
                  : b3View === B3_VIEWS.tps && can('drum_b3')
                    ? `/drum-b3?sorot=pallet-${p.id}`
                    : `/dashboard?period=${period}&date=${anchor}&sorot=pallet-${p.id}`
              }
            />
            <RecoveryAlertCard
              tolerance={data.tolerance}
              target={data.target_recovery_rate}
              chains={data.action_items.low_recovery_chains}
              openCount={data.totals.open_chain_count}
              label={periodLabel(anchor, period)}
              onReviewed={(message) => {
                setReviewMessage(message);
                reload();
              }}
            />
          </div>

          <section aria-labelledby="totals-title">
            <h2 id="totals-title" className="sr-only">
              Angka utama
            </h2>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <StatCard
                label="Tinta X masuk"
                value={formatNumber(data.totals.input)}
                unit="kg"
                note={
                  data.totals.chain_count
                    ? `${data.totals.chain_count} rangkaian (${data.totals.batch_count} batch)`
                    : 'Belum ada rangkaian batch selesai di periode ini'
                }
              />
              <StatCard
                label="Residu (estimasi)"
                value={formatNumber(data.totals.residue)}
                unit="kg"
                note={data.totals.chain_count ? `${formatPercent(data.totals.residue_rate)} dari Tinta X masuk` : undefined}
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
                  <Th numeric>Tinta X masuk</Th>
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
                  : 'Hanya rangkaian selesai. Recovery rate dari total recovery dibagi total Tinta X masuk, bukan rata-rata persentase.'}
              </p>
            </div>
            <Breakdown breakdown={data.breakdown} target={data.target_recovery_rate} sorot={sorot} />
          </section>

          <ActivePallets
            view={b3View}
            sorot={sorot}
            pallets={b3View === B3_VIEWS.tps ? data.tps_pallets : data.active_pallets}
          />
        </div>
      )}
    </>
  );
}
