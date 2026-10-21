import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Archive, ChevronLeft, ChevronRight, Download, LoaderCircle, Search } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { useResource } from '../lib/useResource.js';
import { formatDate, formatDateTime, formatNumber } from '../lib/format.js';
import PageHeader from '../components/PageHeader.jsx';
import Alert from '../components/Alert.jsx';
import EmptyState from '../components/EmptyState.jsx';
import { ErrorCard } from '../components/LoadState.jsx';
import { Table, Td, Th } from '../components/Table.jsx';

function DownloadProof({ item, onError }) {
  const { download } = useAuth();
  const [busy, setBusy] = useState(false);

  return (
    <button
      type="button"
      disabled={busy}
      onClick={async () => {
        setBusy(true);
        onError('');
        try {
          await download(`/surat-jalan/${item.id}/file`);
        } catch (err) {
          onError(`Bukti ${item.number} belum bisa diunduh: ${err.message}`);
        } finally {
          setBusy(false);
        }
      }}
      aria-label={`Unduh bukti surat jalan ${item.number}`}
      className="btn-secondary h-11 whitespace-nowrap px-3 text-body"
    >
      {busy ? <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" /> : <Download className="h-5 w-5" aria-hidden="true" />}
      Bukti
    </button>
  );
}

export default function ArchivePage() {
  const [params, setParams] = useSearchParams();
  const q = params.get('q') ?? '';
  const vendor = params.get('vendor') ?? '';
  const month = params.get('bulan') ?? '';
  const page = Number(params.get('halaman')) || 1;
  const [search, setSearch] = useState(q);
  const [downloadError, setDownloadError] = useState('');
  const query = new URLSearchParams({ page: String(page) });
  if (q) query.set('q', q);
  if (vendor) query.set('vendor_id', vendor);
  if (month) query.set('bulan', month);
  const list = useResource(`/surat-jalan?${query}`);
  const meta = list.meta;
  const hasFilter = Boolean(q || vendor || month);

  const update = (next, { keepPage = false } = {}) => {
    const merged = new URLSearchParams(params);
    Object.entries(next).forEach(([k, v]) => (v ? merged.set(k, v) : merged.delete(k)));
    if (!keepPage) merged.delete('halaman');
    setParams(merged, { replace: true });
  };

  const reset = () => {
    setSearch('');
    setParams({}, { replace: true });
  };

  return (
    <>
      <PageHeader
        title="Arsip surat jalan"
        description="Surat jalan yang sudah tersimpan, sebagai penutup dokumen limbah B3. Cari per nomor surat jalan, pallet, vendor, atau nama berkas."
      />

      <section aria-labelledby="archive-title" className="card mt-6">
        <div className="px-4 pt-4 sm:px-5 sm:pt-5">
          <h2 id="archive-title" className="sr-only">
            Daftar surat jalan
          </h2>
          <form
            role="search"
            onSubmit={(e) => {
              e.preventDefault();
              update({ q: search.trim() });
            }}
            className="grid grid-cols-1 gap-3 md:grid-cols-[1fr_14rem_12rem_auto]"
          >
            <div>
              <label htmlFor="arsip-cari" className="mb-1.5 block text-label font-medium text-gray-800">
                Cari
              </label>
              <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" aria-hidden="true" />
                <input
                  id="arsip-cari"
                  type="search"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="No. surat jalan, pallet, vendor, berkas"
                  className="field pl-9"
                />
              </div>
            </div>
            <div>
              <label htmlFor="arsip-vendor" className="mb-1.5 block text-label font-medium text-gray-800">
                Vendor
              </label>
              <select id="arsip-vendor" value={vendor} onChange={(e) => update({ vendor: e.target.value })} className="field">
                <option value="">Semua vendor</option>
                {meta?.vendors.map((v) => (
                  <option key={v.id} value={v.id}>
                    {v.name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label htmlFor="arsip-bulan" className="mb-1.5 block text-label font-medium text-gray-800">
                Bulan serah terima
              </label>
              <input id="arsip-bulan" type="month" value={month} onChange={(e) => update({ bulan: e.target.value })} className="field" />
            </div>
            <div className="flex items-end gap-2">
              <button type="submit" className="btn-primary h-11 px-4 text-body">
                Cari
              </button>
              {hasFilter && (
                <button type="button" onClick={reset} className="btn-secondary h-11 px-4 text-body">
                  Reset
                </button>
              )}
            </div>
          </form>
          {meta && (
            <p className="mt-3 text-body text-gray-600" role="status">
              {meta.total} surat jalan{hasFilter ? ' cocok dengan filter' : ''}.
            </p>
          )}
          {downloadError && (
            <Alert tone="danger" className="mt-3">
              {downloadError}
            </Alert>
          )}
        </div>

        <div className="mt-4">
          {!list.data ? (
            <div className="px-4 pb-5 sm:px-5">
              {list.loading ? (
                <p role="status" className="text-body text-gray-600">
                  Memuat arsip surat jalan…
                </p>
              ) : (
                <ErrorCard title="Arsip surat jalan belum bisa dimuat." message={list.error} onRetry={list.reload} />
              )}
            </div>
          ) : list.data.length ? (
            <div className={`transition-opacity ${list.loading ? 'opacity-60' : ''}`} aria-busy={list.loading}>
              <Table caption="Arsip surat jalan">
                <thead>
                  <tr>
                    <Th>No. surat jalan</Th>
                    <Th>Serah terima</Th>
                    <Th>Pallet</Th>
                    <Th numeric>Berat vendor</Th>
                    <Th>Petugas vendor</Th>
                    <Th>Dicatat oleh</Th>
                    <Th>
                      <span className="sr-only">Bukti</span>
                    </Th>
                  </tr>
                </thead>
                <tbody>
                  {list.data.map((s) => (
                    <tr key={s.id}>
                      <Td className="min-w-[11rem]">
                        <span className="block font-mono font-medium text-gray-900">{s.number}</span>
                        {s.notes && <span className="block text-caption text-gray-600">{s.notes}</span>}
                      </Td>
                      <Td className="min-w-[12rem]">
                        {formatDate(s.handover_date)}
                        <span className="block text-caption text-gray-600">{s.vendor}</span>
                      </Td>
                      <Td className="min-w-[9rem]">
                        {s.pallets.map((p) => (
                          <span key={p.id} className="block whitespace-nowrap font-mono text-gray-900">
                            {p.pallet_number}
                          </span>
                        ))}
                      </Td>
                      <Td numeric className="whitespace-nowrap">
                        {formatNumber(s.weight_kg)} <span className="text-gray-600">kg</span>
                      </Td>
                      <Td className="whitespace-nowrap">{s.vendor_officer_name ?? <span className="text-gray-600">–</span>}</Td>
                      <Td className="whitespace-nowrap">
                        {s.created_by}
                        <span className="block text-caption text-gray-600">{formatDateTime(s.created_at)}</span>
                      </Td>
                      <Td>
                        <DownloadProof item={s} onError={setDownloadError} />
                        <span className="mt-1 block max-w-[10rem] truncate text-caption text-gray-600" title={s.file_name}>
                          {s.file_name}
                        </span>
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
              {meta.last_page > 1 && (
                <nav
                  aria-label="Halaman arsip surat jalan"
                  className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 text-body text-gray-600 sm:px-5"
                >
                  <span>
                    Halaman {meta.page} dari {meta.last_page}
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
              title="Tidak ada surat jalan yang cocok dengan filter."
              action={
                <button type="button" onClick={reset} className="btn-secondary h-11 px-4 text-body">
                  Reset filter
                </button>
              }
            />
          ) : (
            <EmptyState icon={Archive} title="Belum ada surat jalan.">
              Surat jalan muncul di sini setelah HSE menyerahkan pallet dari TPS ke vendor.
            </EmptyState>
          )}
        </div>
      </section>
    </>
  );
}
