import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { BellRing, Biohazard, Search, Truck } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { useResource } from '../lib/useResource.js';
import { SOROT_CLASS, useSorot } from '../lib/useSorot.js';
import { formatDate, formatDateTime } from '../lib/format.js';
import PageHeader from '../components/PageHeader.jsx';
import Alert from '../components/Alert.jsx';
import EmptyState from '../components/EmptyState.jsx';
import PalletNotes from '../components/PalletNotes.jsx';
import StorageBadge, { TpsBadge, tpsDelayText } from '../components/StorageBadge.jsx';
import { fullText, startedText } from '../lib/pallet.js';
import { ErrorCard } from '../components/LoadState.jsx';
import { Table, Td, Th } from '../components/Table.jsx';

// Lokasi pallet: urutan tab mengikuti tugas HSE, yang di TPS paling dulu
const LOCATIONS = [
  { value: 'tps', label: 'Di TPS', empty: 'Belum ada pallet di TPS.', hint: 'Pallet masuk ke sini otomatis saat operator menandai pallet penuh.' },
  { value: 'reclaimer', label: 'Di area reclaimer', empty: 'Tidak ada pallet yang sedang diisi.', hint: 'Operator memulai pallet baru dari halaman Input pallet B3.' },
  { value: 'diserahkan', label: 'Diserahkan', empty: 'Belum ada pallet yang diserahkan ke vendor.', hint: 'Pallet pindah ke sini setelah surat jalannya tersimpan.' },
  { value: 'semua', label: 'Semua', empty: 'Belum ada pallet tercatat.', hint: 'Pallet muncul setelah operator mulai mengisi pallet B3.' },
];

const REMINDER_LABEL = { perhatian: 'Perhatian', mendesak: 'Mendesak', kritis: 'Kritis' };

// Keterangan: masa simpan di TPS (tugas HSE), surat jalan bila sudah diserahkan, lalu
// catatan operator dan keterangan otomatis sistem
function Keterangan({ pallet }) {
  const hasNotes = pallet.notes || pallet.drum_notes.length > 0 || tpsDelayText(pallet.tps);

  return (
    <div className="space-y-1.5">
      {pallet.location !== 'diserahkan' && (
        <div>
          <span className="text-gray-600">Masa simpan: </span>
          <StorageBadge storage={pallet.storage} />
          <span className="mt-0.5 block text-caption text-gray-600">Batas simpan {formatDate(pallet.expiry_date)}</span>
        </div>
      )}
      {pallet.surat_jalan && (
        <p>
          <span className="text-gray-600">Surat jalan: </span>
          <Link
            to={`/arsip?q=${encodeURIComponent(pallet.surat_jalan.number)}`}
            className="focus-ring rounded font-mono font-medium text-brand-blue-700 hover:underline"
          >
            {pallet.surat_jalan.number}
          </Link>
          <span className="text-gray-600">
            {' '}
            · {formatDate(pallet.surat_jalan.handover_date)} · {pallet.surat_jalan.vendor}
          </span>
        </p>
      )}
      {hasNotes && <PalletNotes pallet={pallet} />}
    </div>
  );
}

// Kolom sama dengan tabel pallet operator (Input pallet B3), supaya mudah dibaca semua role
function PalletTable({ pallets, selectable, selected, onToggle, sorot }) {
  return (
    <Table caption="Inventaris pallet limbah B3">
      <thead>
        <tr>
          {selectable && (
            <Th className="w-12">
              <span className="sr-only">Pilih untuk surat jalan</span>
            </Th>
          )}
          <Th>No. pallet</Th>
          <Th>Mulai diisi</Th>
          <Th>Penuh dan terkirim ke TPS</Th>
          <Th>Batas kirim ke TPS</Th>
          <Th>Status kirim ke TPS</Th>
          <Th>Keterangan</Th>
        </tr>
      </thead>
      <tbody>
        {pallets.map((p) => (
          <tr
            key={p.id}
            data-sorot={`pallet-${p.id}`}
            tabIndex={-1}
            className={sorot === `pallet-${p.id}` ? SOROT_CLASS : selected.includes(p.id) ? 'bg-brand-blue-50' : undefined}
          >
            {selectable && (
              <Td>
                <input
                  type="checkbox"
                  checked={selected.includes(p.id)}
                  onChange={() => onToggle(p.id)}
                  aria-label={`Pilih ${p.pallet_number} untuk surat jalan`}
                  className="h-5 w-5 accent-brand-blue-600"
                />
              </Td>
            )}
            <Td className="whitespace-nowrap font-mono text-gray-900">{p.pallet_number}</Td>
            <Td className="whitespace-nowrap">{startedText(p)}</Td>
            <Td className="whitespace-nowrap">{fullText(p) ?? <span className="text-gray-600">Sedang diisi</span>}</Td>
            <Td className="whitespace-nowrap">{formatDate(p.tps_due_date)}</Td>
            <Td>
              <TpsBadge tps={p.tps} compact />
            </Td>
            <Td className="min-w-[16rem] text-body">
              <Keterangan pallet={p} />
            </Td>
          </tr>
        ))}
      </tbody>
    </Table>
  );
}

// Pengingat masa simpan yang terkirim ke HSE (FR-30)
function ReminderLog() {
  const { data, error, loading, reload } = useResource('/b3/reminders');

  return (
    <section aria-labelledby="log-title" className="card mt-6">
      <div className="px-4 pt-4 sm:px-5 sm:pt-5">
        <h2 id="log-title" className="flex items-center gap-2 text-h2 font-semibold">
          <BellRing className="h-5 w-5 text-gray-600" aria-hidden="true" />
          Log pengingat
        </h2>
        <p className="mt-1 text-body text-gray-600">
          Dikirim ke lonceng HSE setiap hari pukul 07.00: sekali saat sisa masa simpan 30 dan 14 hari, lalu setiap hari mulai
          sisa 7 hari sampai pallet diserahkan.
        </p>
      </div>
      {!data ? (
        <div className="p-4 sm:p-5">
          {loading ? (
            <p role="status" className="text-body text-gray-600">
              Memuat log pengingat…
            </p>
          ) : (
            <Alert tone="danger">
              {error}{' '}
              <button type="button" onClick={reload} className="focus-ring rounded font-semibold underline">
                Coba lagi
              </button>
            </Alert>
          )}
        </div>
      ) : data.length ? (
        <ul className="mt-3 divide-y divide-gray-100 border-t border-gray-100">
          {data.map((log) => (
            <li key={log.id} className="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-baseline sm:gap-4 sm:px-5">
              <span className="w-40 shrink-0 text-caption text-gray-600">{formatDateTime(log.sent_at)}</span>
              <span className="text-body text-gray-800">
                <span className="font-semibold">{REMINDER_LABEL[log.level] ?? log.level}</span> · {log.message}
              </span>
            </li>
          ))}
        </ul>
      ) : (
        <p className="px-4 pb-5 pt-3 text-body text-gray-600 sm:px-5">
          Belum ada pengingat. Pengingat pertama terkirim saat sisa masa simpan sebuah pallet mencapai 30 hari.
        </p>
      )}
    </section>
  );
}

export default function WastePage() {
  const { can } = useAuth();
  const navigate = useNavigate();
  const canHandOver = can('surat_jalan', 'isi');
  const [params, setParams] = useSearchParams();
  const location = LOCATIONS.some((l) => l.value === params.get('lokasi')) ? params.get('lokasi') : 'tps';
  const q = params.get('q') ?? '';
  const [search, setSearch] = useState(q);
  const [selected, setSelected] = useState([]);
  const list = useResource(`/b3/pallets?lokasi=${location}&q=${encodeURIComponent(q)}`);
  const sorot = useSorot(Boolean(list.data));
  const meta = list.meta;
  const current = LOCATIONS.find((l) => l.value === location);
  const selectable = canHandOver && location === 'tps';

  const update = (next) => {
    const merged = new URLSearchParams(params);
    Object.entries(next).forEach(([k, v]) => (v ? merged.set(k, v) : merged.delete(k)));
    merged.delete('sorot');
    setParams(merged, { replace: true });
    setSelected([]);
  };

  const toggle = (id) => setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));

  return (
    <>
      <PageHeader
        title="Limbah B3"
        description="Pallet dari input drum operator, sejak mulai terisi di area reclaimer, sampai di TPS, hingga diserahkan ke vendor lewat surat jalan."
        actions={
          canHandOver && (
            <Link to="/surat-jalan" className="btn-primary h-11 px-4 text-body">
              <Truck className="h-5 w-5" aria-hidden="true" />
              Buat surat jalan
            </Link>
          )
        }
      />

      {meta?.needs_action > 0 && (
        <Alert tone="danger" className="mt-6" title={`${meta.needs_action} pallet mendesak atau kritis.`}>
          Sisa masa simpannya 14 hari atau kurang. Serahkan ke vendor lewat surat jalan sebelum batas simpan.
        </Alert>
      )}

      <section aria-labelledby="inventory-title" className="card mt-6">
        <div className="space-y-4 px-4 pt-4 sm:px-5 sm:pt-5">
          <h2 id="inventory-title" className="text-h2 font-semibold">
            Inventaris pallet
          </h2>
          <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div role="group" aria-label="Lokasi pallet" className="flex flex-wrap gap-2">
              {LOCATIONS.map((l) => (
                <button
                  key={l.value}
                  type="button"
                  aria-pressed={location === l.value}
                  onClick={() => update({ lokasi: l.value === 'tps' ? '' : l.value })}
                  className={`h-11 rounded-lg border px-3 text-body font-medium ${
                    location === l.value
                      ? 'border-brand-blue-600 bg-brand-blue-600 text-white'
                      : 'border-gray-200 bg-white text-gray-800 hover:bg-gray-25'
                  }`}
                >
                  {l.label}
                  {meta && <span className="ml-1.5 tabular-nums opacity-80">{meta.counts[l.value]}</span>}
                </button>
              ))}
            </div>
            <form
              role="search"
              onSubmit={(e) => {
                e.preventDefault();
                update({ q: search.trim() });
              }}
              className="flex gap-2"
            >
              <label htmlFor="cari-pallet" className="sr-only">
                Cari no. pallet atau surat jalan
              </label>
              <div className="relative w-full lg:w-72">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" aria-hidden="true" />
                <input
                  id="cari-pallet"
                  type="search"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="No. pallet atau surat jalan"
                  className="field pl-9"
                />
              </div>
              <button type="submit" className="btn-secondary h-11 px-4 text-body">
                Cari
              </button>
            </form>
          </div>
          {selectable && (
            <div className="flex flex-col gap-2 rounded-lg bg-gray-25 p-3 sm:flex-row sm:items-center sm:justify-between">
              <p className="text-body text-gray-800">
                {selected.length
                  ? `${selected.length} pallet dipilih untuk diserahkan.`
                  : 'Centang pallet yang akan diserahkan ke vendor, lalu buat surat jalannya.'}
              </p>
              <button
                type="button"
                disabled={!selected.length}
                onClick={() => navigate(`/surat-jalan?pallet=${selected.join(',')}`)}
                className="btn-primary h-11 px-4 text-body"
              >
                <Truck className="h-5 w-5" aria-hidden="true" />
                Serahkan {selected.length || ''} pallet
              </button>
            </div>
          )}
        </div>

        <div className="mt-4">
          {!list.data ? (
            <div className="px-4 pb-5 sm:px-5">
              {list.loading ? (
                <p role="status" className="text-body text-gray-600">
                  Memuat pallet limbah B3…
                </p>
              ) : (
                <ErrorCard title="Pallet limbah B3 belum bisa dimuat." message={list.error} onRetry={list.reload} />
              )}
            </div>
          ) : list.data.length ? (
            <div className={`transition-opacity ${list.loading ? 'opacity-60' : ''}`} aria-busy={list.loading}>
              <PalletTable
                pallets={list.data}
                selectable={selectable}
                selected={selected}
                onToggle={toggle}
                sorot={sorot}
              />
            </div>
          ) : q ? (
            <EmptyState
              icon={Search}
              title={`Tidak ada pallet yang cocok dengan "${q}".`}
              action={
                <button
                  type="button"
                  onClick={() => {
                    setSearch('');
                    update({ q: '' });
                  }}
                  className="btn-secondary h-11 px-4 text-body"
                >
                  Hapus pencarian
                </button>
              }
            />
          ) : (
            <EmptyState icon={Biohazard} title={current.empty}>
              {current.hint}
            </EmptyState>
          )}
        </div>
      </section>

      <ReminderLog />
    </>
  );
}
