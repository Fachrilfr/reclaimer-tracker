import { useSearchParams } from 'react-router-dom';
import { Recycle } from 'lucide-react';
import { useResource } from '../lib/useResource.js';
import { formatDate, formatDateTime, formatDuration, formatNumber, formatPercent } from '../lib/format.js';
import PageHeader from '../components/PageHeader.jsx';
import PeriodPicker, { localToday, periodLabel } from '../components/PeriodPicker.jsx';
import StatCard from '../components/StatCard.jsx';
import { BREAKDOWN_LABEL, Kg, REPORT_PERIODS, Section, bucketLabel } from '../components/ReportParts.jsx';
import ExportButtons from '../components/ExportButtons.jsx';
import EmptyState from '../components/EmptyState.jsx';
import { ErrorCard, LoadingCard } from '../components/LoadState.jsx';
import { Table, Td, Th } from '../components/Table.jsx';

const PERIODS = REPORT_PERIODS;

// Pengambilan solvent dari saldo di periode ini, terpisah dari batch
function SolventUsages({ usages }) {
  if (!usages.length) {
    return <p className="mt-3 text-caption text-gray-600">Tidak ada solvent yang diambil dari saldo di periode ini.</p>;
  }

  return (
    <div className="-mx-4 mt-4 sm:-mx-5">
      <Table caption="Pemakaian solvent di periode">
        <thead>
          <tr>
            <Th>Tanggal</Th>
            <Th>Keperluan</Th>
            <Th numeric>Diambil</Th>
          </tr>
        </thead>
        <tbody>
          {usages.map((u) => (
            <tr key={u.id}>
              <Td className="whitespace-nowrap">
                {formatDate(u.date)}
                <span className="block text-caption text-gray-600">{u.shift}</span>
              </Td>
              <Td>
                {u.purpose}
                {u.notes && <span className="block text-caption text-gray-600">{u.notes}</span>}
              </Td>
              <Td numeric>
                <Kg value={u.weight} />
              </Td>
            </tr>
          ))}
        </tbody>
      </Table>
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
        <StatCard label="Tinta X masuk" value={formatNumber(s.input)} unit="kg" note="Bahan masuk ke mesin reclaimer" />
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
          description="Bertambah dari hasil recovery tiap batch, berkurang saat solvent diambil dari saldo."
          padded
        >
          <dl className="divide-y divide-gray-100 text-body tabular-nums">
            {[
              ['Saldo awal', report.solvent.opening],
              ['Hasil recovery masuk', report.solvent.recovered],
              ['Solvent dipakai', -report.solvent.used],
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
          <SolventUsages usages={report.solvent_usages} />
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
              <Th numeric>Tinta X masuk</Th>
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
                <Th numeric>Tinta X masuk</Th>
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
                    <Td numeric><Kg value={c.input} /></Td>
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
        actions={<ExportButtons path="/reports/reclaimer/export" period={period} anchor={anchor} />}
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
