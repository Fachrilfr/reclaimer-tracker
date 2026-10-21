import { Link, useSearchParams } from 'react-router-dom';
import { Truck } from 'lucide-react';
import { useResource } from '../lib/useResource.js';
import { formatDate, formatNumber } from '../lib/format.js';
import PageHeader from '../components/PageHeader.jsx';
import PeriodPicker, { localToday, periodLabel } from '../components/PeriodPicker.jsx';
import StatCard from '../components/StatCard.jsx';
import ExportButtons from '../components/ExportButtons.jsx';
import EmptyState from '../components/EmptyState.jsx';
import StorageBadge from '../components/StorageBadge.jsx';
import { BREAKDOWN_LABEL, Kg, REPORT_PERIODS, Section, bucketLabel } from '../components/ReportParts.jsx';
import { ErrorCard, LoadingCard } from '../components/LoadState.jsx';
import { Table, Td, Th } from '../components/Table.jsx';

const LOCATION = { tps: 'Di TPS', reclaimer: 'Di area reclaimer' };

function Empty({ children }) {
  return <p className="px-4 pb-5 text-body text-gray-600 sm:px-5">{children}</p>;
}

function ReportBody({ report }) {
  const s = report.summary;
  const stock = report.stock;
  const stockLabel = stock.is_current ? 'hari ini' : `per ${formatDate(stock.as_of)}`;
  const atTps = stock.rows.filter((p) => p.location === 'tps').length;
  const unit = report.breakdown?.unit;

  return (
    <div className="mt-6 space-y-6">
      <p className="text-body text-gray-600">
        Penyerahan dihitung dari tanggal serah terima surat jalan. Berat resmi dari timbangan vendor, dicatat per surat jalan.
        Batas masa simpan {report.max_storage_days} hari sejak pallet mulai terisi.
      </p>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
          label="Berat diserahkan"
          value={formatNumber(s.weight_kg)}
          unit="kg"
          note={`${s.surat_jalan_count} surat jalan, timbangan vendor`}
        />
        <StatCard label="Pallet diserahkan" value={s.pallet_count} note={`${s.drum_count} drum`} />
        <StatCard
          label="Diserahkan lewat batas simpan"
          value={s.over_limit_count}
          unit="pallet"
          note={s.pallet_count ? `Lama simpan terlama ${s.longest_days_stored} hari` : 'Belum ada penyerahan di periode ini'}
          tone={!s.pallet_count ? 'neutral' : s.over_limit_count ? 'bad' : 'good'}
        />
        <StatCard
          label={`Stok ${stockLabel}`}
          value={stock.rows.length}
          unit="pallet"
          note={`${atTps} di TPS · ${stock.rows.length - atTps} di area reclaimer`}
        />
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Section id="b3-vendor" title="Per vendor">
          {report.by_vendor.length ? (
            <Table caption="Berat per vendor">
              <thead>
                <tr>
                  <Th>Vendor</Th>
                  <Th numeric>Surat jalan</Th>
                  <Th numeric>Pallet</Th>
                  <Th numeric>Berat</Th>
                </tr>
              </thead>
              <tbody>
                {report.by_vendor.map((v) => (
                  <tr key={v.vendor}>
                    <Td>{v.vendor}</Td>
                    <Td numeric>{v.surat_jalan_count}</Td>
                    <Td numeric>{v.pallet_count}</Td>
                    <Td numeric>
                      <Kg value={v.weight_kg} />
                    </Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          ) : (
            <Empty>Tidak ada limbah B3 yang diserahkan ke vendor di periode ini.</Empty>
          )}
        </Section>

        {unit && (
          <Section id="b3-breakdown" title={`Per ${BREAKDOWN_LABEL[unit].toLowerCase()}`}>
            <Table caption={`Berat per ${BREAKDOWN_LABEL[unit].toLowerCase()}`}>
              <thead>
                <tr>
                  <Th>{BREAKDOWN_LABEL[unit]}</Th>
                  <Th numeric>Surat jalan</Th>
                  <Th numeric>Pallet</Th>
                  <Th numeric>Berat</Th>
                </tr>
              </thead>
              <tbody>
                {report.breakdown.rows.map((row) => (
                  <tr key={row.start}>
                    <Td className="whitespace-nowrap">{bucketLabel(row, unit)}</Td>
                    <Td numeric>{row.surat_jalan_count}</Td>
                    <Td numeric>{row.pallet_count}</Td>
                    <Td numeric>{row.weight_kg ? <Kg value={row.weight_kg} /> : <span className="text-gray-600">–</span>}</Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </Section>
        )}
      </div>

      <Section id="b3-sj" title="Surat jalan" description="Penyerahan pallet dari TPS ke vendor di periode ini.">
        {report.surat_jalan.length ? (
          <Table caption="Surat jalan di periode">
            <thead>
              <tr>
                <Th>No. surat jalan</Th>
                <Th>Serah terima</Th>
                <Th>Pallet</Th>
                <Th numeric>Berat vendor</Th>
                <Th>Petugas vendor</Th>
              </tr>
            </thead>
            <tbody>
              {report.surat_jalan.map((sj) => (
                <tr key={sj.id}>
                  <Td className="min-w-[11rem]">
                    <Link
                      to={`/arsip?q=${encodeURIComponent(sj.number)}`}
                      className="focus-ring rounded font-mono font-medium text-brand-blue-700 hover:underline"
                    >
                      {sj.number}
                    </Link>
                  </Td>
                  <Td className="min-w-[12rem]">
                    {formatDate(sj.handover_date)}
                    <span className="block text-caption text-gray-600">{sj.vendor}</span>
                  </Td>
                  <Td>
                    {sj.pallets.map((p) => (
                      <span key={p.id} className="block whitespace-nowrap font-mono text-gray-900">
                        {p.pallet_number}
                      </span>
                    ))}
                  </Td>
                  <Td numeric>
                    <Kg value={sj.weight_kg} />
                  </Td>
                  <Td className="whitespace-nowrap">{sj.vendor_officer_name ?? <span className="text-gray-600">–</span>}</Td>
                </tr>
              ))}
            </tbody>
          </Table>
        ) : (
          <EmptyState icon={Truck} title="Belum ada surat jalan di periode ini.">
            Pilih periode lain, atau buat surat jalan untuk pallet yang ada di TPS.
          </EmptyState>
        )}
      </Section>

      <Section
        id="b3-compliance"
        title="Kepatuhan masa simpan"
        description="Lama simpan pallet yang diserahkan, dari mulai terisi sampai serah terima. Yang lewat batas ada di atas."
      >
        {report.compliance.length ? (
          <Table caption="Kepatuhan masa simpan">
            <thead>
              <tr>
                <Th>Pallet</Th>
                <Th>Mulai terisi</Th>
                <Th>Serah terima</Th>
                <Th>Batas simpan</Th>
                <Th numeric>Lama simpan</Th>
                <Th>Status</Th>
              </tr>
            </thead>
            <tbody>
              {report.compliance.map((c) => (
                <tr key={c.pallet_number}>
                  <Td className="whitespace-nowrap">
                    <span className="font-mono text-gray-900">{c.pallet_number}</span>
                    <span className="block text-caption text-gray-600">{c.surat_jalan_number}</span>
                  </Td>
                  <Td className="whitespace-nowrap">{formatDate(c.start_date)}</Td>
                  <Td className="whitespace-nowrap">{formatDate(c.handover_date)}</Td>
                  <Td className="whitespace-nowrap">{formatDate(c.expiry_date)}</Td>
                  <Td numeric className="whitespace-nowrap">
                    {c.days_stored} hari
                  </Td>
                  <Td className={`whitespace-nowrap ${c.over_days ? 'font-semibold text-danger-fg' : ''}`}>
                    {c.over_days ? `Lewat batas ${c.over_days} hari` : 'Dalam batas'}
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        ) : (
          <Empty>Tidak ada pallet yang diserahkan di periode ini.</Empty>
        )}
      </Section>

      <Section
        id="b3-stock"
        title={`Stok ${stockLabel}`}
        description="Pallet yang belum diserahkan ke vendor, dengan sisa masa simpan saat itu."
      >
        {stock.rows.length ? (
          <Table caption={`Stok ${stockLabel}`}>
            <thead>
              <tr>
                <Th>Pallet</Th>
                <Th>Lokasi</Th>
                <Th numeric>Drum</Th>
                <Th>Mulai terisi</Th>
                <Th>Batas simpan</Th>
                <Th>Masa simpan</Th>
              </tr>
            </thead>
            <tbody>
              {stock.rows.map((p) => (
                <tr key={p.pallet_number}>
                  <Td className="whitespace-nowrap font-mono text-gray-900">{p.pallet_number}</Td>
                  <Td className="whitespace-nowrap">
                    {LOCATION[p.location]}
                    {p.sent_to_tps_at && (
                      <span className="block text-caption text-gray-600">sejak {formatDate(p.sent_to_tps_at)}</span>
                    )}
                  </Td>
                  <Td numeric>{p.drum_count}</Td>
                  <Td className="whitespace-nowrap">{formatDate(p.start_date)}</Td>
                  <Td className="whitespace-nowrap">{formatDate(p.expiry_date)}</Td>
                  <Td>
                    <StorageBadge storage={{ level: p.level, days_remaining: p.days_remaining }} />
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        ) : (
          <Empty>Tidak ada pallet yang tersimpan {stockLabel}.</Empty>
        )}
      </Section>
    </div>
  );
}

export default function B3ReportPage() {
  const [params, setParams] = useSearchParams();
  const today = localToday();
  const period = REPORT_PERIODS.includes(params.get('period')) ? params.get('period') : 'month';
  const rawDate = params.get('date');
  const anchor = rawDate && /^\d{4}-\d{2}-\d{2}$/.test(rawDate) && rawDate <= today ? rawDate : today;
  const { data, error, loading, reload } = useResource(`/reports/b3?period=${period}&date=${anchor}`);

  const change = (nextPeriod, nextAnchor) =>
    setParams({ period: nextPeriod, date: nextAnchor > today ? today : nextAnchor }, { replace: true });

  return (
    <>
      <PageHeader
        title="Laporan Limbah B3"
        description={`Berat limbah B3 yang diserahkan ke vendor, surat jalan, kepatuhan masa simpan, dan stok, ${periodLabel(anchor, period)}.`}
        actions={<ExportButtons path="/reports/b3/export" period={period} anchor={anchor} />}
      />

      <div className="mt-6">
        <PeriodPicker periods={REPORT_PERIODS} period={period} anchor={anchor} today={today} onChange={change} />
      </div>

      {!data ? (
        <div className="mt-6">
          {loading ? (
            <LoadingCard label="Menyusun laporan…" />
          ) : (
            <ErrorCard title="Laporan limbah B3 belum bisa dimuat." message={error} onRetry={reload} />
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
