import { useState } from 'react';
import { Recycle } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { useResource } from '../lib/useResource.js';
import { SOROT_CLASS, useSorot } from '../lib/useSorot.js';
import { formatDate, formatNumber } from '../lib/format.js';
import PageHeader from '../components/PageHeader.jsx';
import StatCard from '../components/StatCard.jsx';
import PeriodPicker, { usePeriodParams } from '../components/PeriodPicker.jsx';
import { BREAKDOWN_LABEL, Kg, REPORT_PERIODS, Section, bucketLabel } from '../components/ReportParts.jsx';
import EmptyState from '../components/EmptyState.jsx';
import { ErrorCard, LoadingCard } from '../components/LoadState.jsx';
import { Table, Td, Th } from '../components/Table.jsx';
import { ActivePallets, B3AlertCard, B3_VIEWS } from './DashboardPage.jsx';

// Baris pertama tabel residu per input; sisanya dibuka lewat tombol supaya periode tahunan tidak memanjang
const INPUT_PREVIEW = 20;

// Pallet tidak ditimbang di TPS, jadi berat residu dibaca dari dua sisi: estimasi dari
// input reclaimer dan timbangan vendor saat serah terima.
export function ResiduePerInput({ inputs, sorot }) {
  const [showAll, setShowAll] = useState(false);
  const shown = showAll ? inputs : inputs.slice(0, INPUT_PREVIEW);

  if (!inputs.length) {
    return (
      <EmptyState icon={Recycle} title="Belum ada proses selesai di periode ini.">
        Residu dihitung setelah input reclaimer ditandai selesai.
      </EmptyState>
    );
  }

  return (
    <>
      <Table caption="Residu per input">
        <thead>
          <tr>
            <Th>No. input</Th>
            <Th>Tanggal dan shift</Th>
            <Th numeric>Tinta X masuk</Th>
            <Th numeric>Berat residu</Th>
          </tr>
        </thead>
        <tbody>
          {shown.map((c) => (
            <tr
              key={c.root_id}
              data-sorot={`rangkaian-${c.root_id}`}
              tabIndex={-1}
              className={sorot === `rangkaian-${c.root_id}` ? SOROT_CLASS : undefined}
            >
              <Td>
                <span className="whitespace-nowrap font-mono text-gray-900">{c.batch_number}</span>
                {c.batch_count > 1 && <span className="block text-caption text-gray-600">+ {c.batch_count - 1} lanjutan</span>}
              </Td>
              <Td className="whitespace-nowrap">
                {formatDate(c.date)}
                <span className="block text-caption text-gray-600">{c.shift}</span>
              </Td>
              <Td numeric>
                <Kg value={c.input} />
              </Td>
              <Td numeric className="font-semibold text-gray-900">
                <Kg value={c.residue} />
              </Td>
            </tr>
          ))}
        </tbody>
      </Table>
      {inputs.length > INPUT_PREVIEW && (
        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 px-4 py-3 text-body sm:px-5">
          <span className="text-gray-600">
            {showAll ? `Semua ${inputs.length} input ditampilkan.` : `${inputs.length - INPUT_PREVIEW} input lainnya belum ditampilkan.`}
          </span>
          <button
            type="button"
            onClick={() => setShowAll((v) => !v)}
            className="focus-ring inline-flex min-h-11 items-center rounded font-semibold text-brand-blue-700 hover:underline"
          >
            {showAll ? `Tampilkan ${INPUT_PREVIEW} terbaru saja` : 'Tampilkan semua'}
          </button>
        </div>
      )}
    </>
  );
}

export function ResidueBreakdown({ breakdown }) {
  const { unit, rows } = breakdown;

  return (
    <Table caption={`Residu per ${BREAKDOWN_LABEL[unit].toLowerCase()}`}>
      <thead>
        <tr>
          <Th>{BREAKDOWN_LABEL[unit]}</Th>
          <Th numeric>Total proses</Th>
          <Th numeric>Berat residu</Th>
          <Th numeric>Timbangan vendor</Th>
          <Th numeric>Surat jalan</Th>
        </tr>
      </thead>
      <tbody>
        {rows.map((r) => (
          <tr key={r.start}>
            <Td className="whitespace-nowrap">{bucketLabel(r, unit)}</Td>
            <Td numeric>{r.chain_count}</Td>
            <Td numeric>{r.chain_count ? <Kg value={r.residue} /> : '–'}</Td>
            <Td numeric>{r.surat_jalan_count ? <Kg value={r.vendor_weight} /> : '–'}</Td>
            <Td numeric>{r.surat_jalan_count || '–'}</Td>
          </tr>
        ))}
      </tbody>
    </Table>
  );
}

export default function HseDashboardPage() {
  const { can } = useAuth();
  const { period, anchor, end, today, isCurrent, label, query, change } = usePeriodParams(REPORT_PERIODS, 'month');
  const { data, error, loading, reload } = useResource(`/dashboard/hse?${query}`);
  const sorot = useSorot(Boolean(data) && !loading);
  const view = B3_VIEWS.storage;

  return (
    <>
      <PageHeader title="Dashboard HSE" description={`Berat residu dari input reclaimer dan timbangan vendor, serta pallet di TPS, ${label}.`} />

      <div className="mt-6">
        <PeriodPicker periods={REPORT_PERIODS} period={period} anchor={anchor} end={end} today={today} isCurrent={isCurrent} onChange={change} />
      </div>

      {!data ? (
        <div className="mt-6">
          {loading ? (
            <LoadingCard label="Memuat dashboard HSE…" />
          ) : (
            <ErrorCard title="Dashboard HSE belum bisa dimuat." message={error} onRetry={reload} />
          )}
        </div>
      ) : (
        <div className={`mt-6 space-y-6 transition-opacity ${loading ? 'opacity-60' : ''}`} aria-busy={loading}>
          <section aria-labelledby="hse-totals-title">
            <h2 id="hse-totals-title" className="sr-only">
              Angka utama
            </h2>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <StatCard
                label="Berat residu"
                value={formatNumber(data.totals.residue)}
                unit="kg"
                note={
                  data.totals.chain_count
                    ? `Dari ${data.totals.chain_count} proses selesai (${data.totals.batch_count} input)`
                    : 'Belum ada proses selesai di periode ini'
                }
              />
              <StatCard
                label="Timbangan vendor"
                value={formatNumber(data.totals.vendor_weight)}
                unit="kg"
                note={
                  data.totals.surat_jalan_count
                    ? `${data.totals.surat_jalan_count} surat jalan · ${data.totals.handover_pallet_count} pallet diserahkan`
                    : 'Belum ada serah terima ke vendor di periode ini'
                }
              />
              <StatCard
                label="Pallet di TPS"
                value={formatNumber(data.totals.tps_pallet_count, 0)}
                unit="pallet"
                note={`${data.totals.tps_drum_count} drum, belum diangkut vendor · saat ini`}
              />
            </div>
          </section>

          <B3AlertCard
            view={view}
            pallets={data.action_pallets}
            linkFor={(p) => (can('limbah_b3') ? `/limbah-b3?lokasi=semua&sorot=pallet-${p.id}` : `/dashboard-hse?${query}&sorot=pallet-${p.id}`)}
          />

          {data.breakdown && (
            <Section id="hse-breakdown" title={`Per ${BREAKDOWN_LABEL[data.breakdown.unit].toLowerCase()}`}>
              <ResidueBreakdown breakdown={data.breakdown} />
            </Section>
          )}

          <Section
            id="hse-inputs"
            title="Residu per input"
            description="Berat residu = Tinta X masuk dikurangi hasil solvent, per proses yang sudah selesai. Terbaru di atas."
          >
            <ResiduePerInput inputs={data.inputs} sorot={sorot} />
          </Section>

          <ActivePallets view={view} sorot={sorot} pallets={data.tps_pallets} />
        </div>
      )}
    </>
  );
}
