import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { FileSpreadsheet, FileText, LoaderCircle, Recycle } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { useResource } from '../lib/useResource.js';
import { formatDate, formatDateRange, formatDateTime, formatDuration, formatMonth, formatNumber, formatPercent } from '../lib/format.js';
import PageHeader from '../components/PageHeader.jsx';
import PeriodPicker, { localToday, periodLabel } from '../components/PeriodPicker.jsx';
import StatCard from '../components/StatCard.jsx';
import Alert from '../components/Alert.jsx';
import EmptyState from '../components/EmptyState.jsx';
import { ErrorCard, LoadingCard } from '../components/LoadState.jsx';
import { Table, Td, Th } from '../components/Table.jsx';

const PERIODS = ['day', 'week', 'month', 'year'];
const BREAKDOWN_LABEL = { day: 'Hari', week: 'Minggu', month: 'Bulan' };

function Kg({ value }) {
  return (
    <span className="whitespace-nowrap">
      {formatNumber(value)} <span className="text-gray-600">kg</span>
    </span>
  );
}

function bucketLabel(row, unit) {
  if (unit === 'month') return formatMonth(row.start);
  return row.start === row.end ? formatDate(row.start) : formatDateRange(row.start, row.end);
}

function Section({ id, title, description, children, padded = false }) {
  return (
    <section aria-labelledby={id} className="card">
      <div className="p-4 sm:p-5">
        <h2 id={id} className="text-h2 font-semibold">
          {title}
        </h2>
        {description && <p className="mt-1 text-body text-gray-600">{description}</p>}
      </div>
      <div className={padded ? 'px-4 pb-4 sm:px-5 sm:pb-5' : ''}>{children}</div>
    </section>
  );
}

function ExportButtons({ period, anchor }) {
  const { download } = useAuth();
  const [busy, setBusy] = useState(null);
  const [error, setError] = useState('');
  const [started, setStarted] = useState('');

  const run = async (format) => {
    setBusy(format);
    setError('');
    setStarted('');
    try {
      await download(`/reports/reclaimer/export?format=${format}&period=${period}&date=${anchor}`);
      setStarted(`Laporan ${format === 'pdf' ? 'PDF' : 'Excel'} diunduh. Cek folder unduhan browser.`);
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(null);
    }
  };

  const button = (format, label, Icon, className) => (
    <button type="button" onClick={() => run(format)} disabled={busy !== null} className={`${className} h-11 px-4 text-body`}>
      {busy === format ? (
        <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />
      ) : (
        <Icon className="h-5 w-5" aria-hidden="true" />
      )}
      {busy === format ? 'Menyiapkan…' : label}
    </button>
  );

  return (
    <div className="flex flex-col gap-2">
      <div className="flex flex-wrap gap-3">
        {button('xlsx', 'Unduh Excel', FileSpreadsheet, 'btn-primary')}
        {button('pdf', 'Unduh PDF', FileText, 'btn-secondary')}
      </div>
      {error && <Alert tone="danger">{error}</Alert>}
      {started && <p role="status" className="text-caption text-gray-600">{started}</p>}
    </div>
  );
}

function ReportBody({ report }) {
  const s = report.summary;
  const target = report.target_recovery_rate;
  const below = (rate, count) => count && rate < target;

  return (
    <div className="mt-6 space-y-6">
      <p className="text-body text-gray-600">
        {s.chain_count} rangkaian selesai ({s.batch_count} batch)
        {s.open_chain_count > 0 && ` · ${s.open_chain_count} rangkaian belum selesai tidak dihitung`}. Angka sama dengan
        dashboard: per rangkaian selesai, pada tanggal dan shift batch awalnya.
      </p>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
          label="Bahan masuk"
          value={formatNumber(s.input)}
          unit="kg"
          note={`Tinta X ${formatNumber(s.ink)} + solvent ${formatNumber(s.solvent_used)}`}
        />
        <StatCard label="Hasil recovery" value={formatNumber(s.recovery)} unit="kg" note={`Residu estimasi ${formatNumber(s.residue)} kg`} />
        <StatCard
          label="Recovery rate"
          value={s.chain_count ? formatPercent(s.recovery_rate) : '–'}
          note={`Target ${formatPercent(target)} · ${s.below_target_count} rangkaian di bawah target`}
          tone={!s.chain_count ? 'neutral' : s.recovery_rate >= target ? 'good' : 'bad'}
        />
        <StatCard
          label={report.solvent.closing_is_current ? 'Saldo solvent saat ini' : 'Saldo solvent akhir'}
          value={formatNumber(report.solvent.closing)}
          unit="kg"
          note={`Saldo awal ${formatNumber(report.solvent.opening)} kg`}
        />
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Section
          id="lap-solvent"
          title="Saldo solvent"
          description="Bergerak per batch, termasuk batch yang rangkaiannya belum selesai."
          padded
        >
          <dl className="divide-y divide-gray-100 text-body tabular-nums">
            {[
              ['Saldo awal', report.solvent.opening],
              ['Solvent dipakai', -report.solvent.used],
              ['Hasil recovery masuk', report.solvent.recovered],
              ...(report.solvent.adjustment ? [['Penyesuaian', report.solvent.adjustment]] : []),
            ].map(([label, value]) => (
              <div key={label} className="flex justify-between gap-4 py-2">
                <dt className="text-gray-600">{label}</dt>
                <dd className="text-gray-900">
                  {value > 0 && label !== 'Saldo awal' ? '+' : ''}
                  <Kg value={value} />
                </dd>
              </div>
            ))}
            <div className="flex justify-between gap-4 py-2 font-semibold">
              <dt>{report.solvent.closing_is_current ? 'Saldo saat ini' : 'Saldo akhir'}</dt>
              <dd>
                <Kg value={report.solvent.closing} />
              </dd>
            </div>
          </dl>
        </Section>

        <Section
          id="lap-maintenance"
          title="Maintenance mesin"
          description={
            report.maintenance.count
              ? `${report.maintenance.count} kali, total downtime ${formatDuration(report.maintenance.total_minutes)} di periode ini.`
              : undefined
          }
          padded={!report.maintenance.count}
        >
          {report.maintenance.count ? (
            <Table caption="Maintenance di periode">
              <thead>
                <tr>
                  <Th>Mulai</Th>
                  <Th>Alasan</Th>
                  <Th numeric>Durasi</Th>
                </tr>
              </thead>
              <tbody>
                {report.maintenance.rows.map((m) => (
                  <tr key={m.start}>
                    <Td className="whitespace-nowrap">{formatDateTime(m.start)}</Td>
                    <Td>{m.reason}</Td>
                    <Td numeric className="whitespace-nowrap">
                      {formatDuration(m.minutes_in_period)}
                    </Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          ) : (
            <p className="text-body text-gray-600">Tidak ada maintenance di periode ini.</p>
          )}
        </Section>
      </div>

      <Section id="lap-shift" title="Perbandingan per shift" description="Rangkaian lintas shift dihitung di shift batch awalnya.">
        <Table caption="Perbandingan per shift">
          <thead>
            <tr>
              <Th>Shift</Th>
              <Th numeric>Rangkaian</Th>
              <Th numeric>Bahan masuk</Th>
              <Th numeric>Recovery</Th>
              <Th numeric>Residu estimasi</Th>
              <Th numeric>Recovery rate</Th>
            </tr>
          </thead>
          <tbody>
            {report.by_shift.map((r) => (
              <tr key={r.shift}>
                <Td className="whitespace-nowrap font-medium text-gray-900">{r.shift}</Td>
                <Td numeric>{r.chain_count}</Td>
                <Td numeric><Kg value={r.input} /></Td>
                <Td numeric><Kg value={r.recovery} /></Td>
                <Td numeric><Kg value={r.residue} /></Td>
                <Td numeric className={below(r.recovery_rate, r.chain_count) ? 'font-semibold text-danger-fg' : ''}>
                  {r.chain_count ? formatPercent(r.recovery_rate) : '–'}
                </Td>
              </tr>
            ))}
          </tbody>
        </Table>
      </Section>

      {report.breakdown && (
        <Section id="lap-rincian" title={`Rincian per ${BREAKDOWN_LABEL[report.breakdown.unit].toLowerCase()}`}>
          <Table caption={`Rincian per ${BREAKDOWN_LABEL[report.breakdown.unit].toLowerCase()}`}>
            <thead>
              <tr>
                <Th>{BREAKDOWN_LABEL[report.breakdown.unit]}</Th>
                <Th numeric>Rangkaian</Th>
                <Th numeric>Bahan masuk</Th>
                <Th numeric>Recovery</Th>
                <Th numeric>Residu estimasi</Th>
                <Th numeric>Recovery rate</Th>
              </tr>
            </thead>
            <tbody>
              {report.breakdown.rows.map((r) => (
                <tr key={r.start}>
                  <Td className="whitespace-nowrap">{bucketLabel(r, report.breakdown.unit)}</Td>
                  <Td numeric>{r.chain_count}</Td>
                  <Td numeric><Kg value={r.input} /></Td>
                  <Td numeric><Kg value={r.recovery} /></Td>
                  <Td numeric><Kg value={r.residue} /></Td>
                  <Td numeric className={below(r.recovery_rate, r.chain_count) ? 'font-semibold text-danger-fg' : ''}>
                    {r.chain_count ? formatPercent(r.recovery_rate) : '–'}
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        </Section>
      )}

      <Section
        id="lap-bawah-target"
        title="Rangkaian di bawah target"
        description="Beserta tinjauan supervisor."
        padded={!report.below_target.length}
      >
        {report.below_target.length ? (
          <Table caption="Rangkaian di bawah target">
            <thead>
              <tr>
                <Th>Rangkaian</Th>
                <Th>Tanggal dan shift</Th>
                <Th numeric>Recovery</Th>
                <Th numeric>Target</Th>
                <Th>Tinjauan supervisor</Th>
              </tr>
            </thead>
            <tbody>
              {report.below_target.map((c) => (
                <tr key={c.root_id}>
                  <Td>
                    <span className="whitespace-nowrap font-mono text-gray-900">{c.batch_number}</span>
                    {c.batch_count > 1 && <span className="block text-caption text-gray-600">+ {c.batch_count - 1} lanjutan</span>}
                  </Td>
                  <Td className="whitespace-nowrap">
                    {formatDate(c.date)}
                    <span className="block text-caption text-gray-600">{c.shift}</span>
                  </Td>
                  <Td numeric className="font-semibold text-danger-fg">{formatPercent(c.recovery_rate)}</Td>
                  <Td numeric>{formatPercent(c.target_recovery_rate)}</Td>
                  <Td className="min-w-[14rem]">
                    {c.review ? (
                      `${c.review.by}: ${c.review.notes}`
                    ) : (
                      <span className="text-gray-600">Belum ditinjau</span>
                    )}
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        ) : (
          <p className="text-body text-gray-600">Tidak ada rangkaian di bawah target di periode ini.</p>
        )}
      </Section>

      {report.temp_abnormal.length > 0 && (
        <Section id="lap-suhu" title="Batch dengan suhu di luar normal">
          <Table caption="Batch dengan suhu di luar normal">
            <thead>
              <tr>
                <Th>No. batch</Th>
                <Th>Tanggal dan shift</Th>
                <Th numeric>Oil Temperature</Th>
                <Th numeric>Vapour Temperature</Th>
              </tr>
            </thead>
            <tbody>
              {report.temp_abnormal.map((b) => (
                <tr key={b.batch_number}>
                  <Td className="whitespace-nowrap font-mono text-gray-900">{b.batch_number}</Td>
                  <Td className="whitespace-nowrap">
                    {formatDate(b.date)}
                    <span className="block text-caption text-gray-600">{b.shift}</span>
                  </Td>
                  <Td numeric className="whitespace-nowrap">{formatNumber(b.oil_temp, 1)} °C</Td>
                  <Td numeric className="whitespace-nowrap">{formatNumber(b.vapour_temp, 1)} °C</Td>
                </tr>
              ))}
            </tbody>
          </Table>
        </Section>
      )}

      <Section id="lap-rangkaian" title={`Rincian rangkaian (${report.chains.length})`} padded={!report.chains.length}>
        {report.chains.length ? (
          <details open={report.period === 'day' || report.period === 'week'}>
            <summary className="focus-ring mx-4 mb-3 inline-flex min-h-11 cursor-pointer items-center rounded text-body font-medium text-brand-blue-700 sm:mx-5">
              Tampilkan atau sembunyikan rincian
            </summary>
            <Table caption="Rincian rangkaian batch">
              <thead>
                <tr>
                  <Th>Rangkaian</Th>
                  <Th>Tanggal dan shift</Th>
                  <Th>Operator</Th>
                  <Th numeric>Tinta X</Th>
                  <Th numeric>Solvent</Th>
                  <Th numeric>Recovery</Th>
                  <Th numeric>Recovery rate</Th>
                </tr>
              </thead>
              <tbody>
                {report.chains.map((c) => (
                  <tr key={c.root_id}>
                    <Td>
                      <span className="whitespace-nowrap font-mono text-gray-900">{c.batch_number}</span>
                      {c.batch_count > 1 && <span className="block text-caption text-gray-600">+ {c.batch_count - 1} lanjutan</span>}
                    </Td>
                    <Td className="whitespace-nowrap">
                      {formatDate(c.date)}
                      <span className="block text-caption text-gray-600">{c.shift}</span>
                    </Td>
                    <Td className="whitespace-nowrap">{c.operator}</Td>
                    <Td numeric><Kg value={c.ink} /></Td>
                    <Td numeric><Kg value={c.solvent_used} /></Td>
                    <Td numeric><Kg value={c.recovery} /></Td>
                    <Td numeric className={c.is_below_target ? 'font-semibold text-danger-fg' : ''}>
                      {c.is_complete ? formatPercent(c.recovery_rate) : <span className="text-caption text-gray-600">belum selesai</span>}
                    </Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </details>
        ) : (
          <EmptyState icon={Recycle} title="Belum ada batch di periode ini.">
            Pilih periode lain, atau tunggu operator mencatat batch.
          </EmptyState>
        )}
      </Section>
    </div>
  );
}

export default function ReportPage() {
  const [params, setParams] = useSearchParams();
  const today = localToday();
  const period = PERIODS.includes(params.get('period')) ? params.get('period') : 'month';
  const rawDate = params.get('date');
  const anchor = rawDate && /^\d{4}-\d{2}-\d{2}$/.test(rawDate) && rawDate <= today ? rawDate : today;
  const { data, error, loading, reload } = useResource(`/reports/reclaimer?period=${period}&date=${anchor}`);

  const change = (nextPeriod, nextAnchor) =>
    setParams({ period: nextPeriod, date: nextAnchor > today ? today : nextAnchor }, { replace: true });

  return (
    <>
      <PageHeader
        title="Laporan Reclaimer"
        description={`Material balance, saldo solvent, dan catatan operasional, ${periodLabel(anchor, period)}. Ekspor ke Excel atau PDF untuk arsip dan audit.`}
        actions={<ExportButtons period={period} anchor={anchor} />}
      />

      <div className="mt-6">
        <PeriodPicker periods={PERIODS} period={period} anchor={anchor} today={today} onChange={change} />
      </div>

      {!data ? (
        <div className="mt-6">
          {loading ? (
            <LoadingCard label="Menyusun laporan…" />
          ) : (
            <ErrorCard title="Laporan belum bisa dimuat." message={error} onRetry={reload} />
          )}
        </div>
      ) : (
        <div className={`transition-opacity ${loading ? 'opacity-60' : ''}`} aria-busy={loading}>
          <ReportBody report={data} />
        </div>
      )}
    </>
  );
}
