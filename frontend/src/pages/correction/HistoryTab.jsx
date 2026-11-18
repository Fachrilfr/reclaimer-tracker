import { History, PencilLine, Trash2 } from 'lucide-react';
import { formatDate, formatDateTime, formatNumber } from '../../lib/format.js';
import { Table, Td, Th } from '../../components/Table.jsx';
import { FilterBar, ListBody, kg, signedKg, useCorrectionList } from './parts.jsx';

const ENTITY_OPTIONS = [
  ['reclaimer_batches', 'Input reclaimer'],
  ['solvent_usages', 'Pemakaian solvent'],
  ['b3_pallets', 'Pallet residu'],
  ['surat_jalan', 'Surat jalan'],
  ['machine_maintenance_logs', 'Maintenance mesin'],
];

const STATUS = {
  completed: 'Selesai',
  in_progress: 'Belum selesai',
  filling: 'Masih diisi',
  full: 'Penuh, di TPS',
  transported: 'Diangkut vendor',
};

// "2026-10-20 07:30" dari riwayat maintenance
const dateTime = (v) => formatDateTime(String(v).replace(' ', 'T'));
const celsius = (v) => `${formatNumber(Number(v), 1)} °C`;

// Nama isian dan cara menampilkan nilainya
const FIELDS = {
  date: ['Tanggal', formatDate],
  shift: ['Shift'],
  regu: ['Regu'],
  process_status: ['Status proses', (v) => STATUS[v] ?? v],
  recovery_weight: ['Hasil solvent', (v) => kg(Number(v))],
  ink_weight: ['Tinta X', (v) => kg(Number(v))],
  is_continuation: ['Input lanjutan', (v) => (v ? 'Ya' : 'Tidak')],
  oil_temp: ['Suhu oli', celsius],
  vapour_temp: ['Suhu uap', celsius],
  notes: ['Catatan'],
  solvent_adjustment: ['Penyesuaian saldo solvent', (v) => signedKg(Number(v))],
  weight: ['Berat solvent', (v) => kg(Number(v))],
  purpose: ['Keperluan'],
  status: ['Status pallet', (v) => STATUS[v] ?? v],
  start_date: ['Mulai diisi', formatDate],
  full_date: ['Penuh dan sampai di TPS', formatDate],
  drum_count: ['Jumlah drum', (v) => `${v} drum`],
  surat_jalan_number: ['No. surat jalan'],
  vendor: ['Vendor'],
  handover_date: ['Tanggal serah terima', formatDate],
  weight_kg: ['Berat timbangan vendor', (v) => kg(Number(v))],
  vendor_officer_name: ['Petugas vendor'],
  pallets: ['Pallet'],
  file_name: ['Berkas bukti'],
  file_path: ['Berkas lama di server'],
  start: ['Mulai', dateTime],
  end: ['Selesai', dateTime],
  maintenance_reason: ['Alasan maintenance'],
  end_note: ['Catatan perbaikan'],
};

function show(field, value) {
  if (value === null || value === undefined || value === '') return '–';
  const format = FIELDS[field]?.[1];
  return format ? format(value) : String(value);
}

function ActionBadge({ action }) {
  return action === 'delete' ? (
    <span className="inline-flex items-center gap-1 rounded-full border border-danger-border bg-danger-bg px-2 py-0.5 text-caption font-medium text-danger-fg">
      <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
      Dihapus
    </span>
  ) : (
    <span className="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-25 px-2 py-0.5 text-caption font-medium text-gray-800">
      <PencilLine className="h-3.5 w-3.5" aria-hidden="true" />
      Dikoreksi
    </span>
  );
}

export default function HistoryTab() {
  const state = useCorrectionList('/koreksi/riwayat');

  return (
    <section aria-label="Riwayat koreksi" className="card">
      <FilterBar state={state} placeholder="No. input, pallet, surat jalan, alasan">
        <div>
          <label htmlFor="koreksi-jenis" className="mb-1.5 block text-label font-medium text-gray-800">
            Jenis data
          </label>
          <select id="koreksi-jenis" value={state.filters.jenis} onChange={(e) => state.update({ jenis: e.target.value })} className="field">
            <option value="">Semua jenis</option>
            {ENTITY_OPTIONS.map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </div>
      </FilterBar>
      <ListBody state={state} icon={History} emptyTitle="Belum ada data yang dikoreksi atau dihapus.">
        {(rows) => (
          <Table caption="Riwayat koreksi">
            <thead>
              <tr>
                <Th>Waktu</Th>
                <Th>Data</Th>
                <Th>Perubahan</Th>
                <Th>Alasan</Th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} className="align-top">
                  <Td className="whitespace-nowrap">
                    {formatDateTime(r.at)}
                    <span className="block text-caption text-gray-600">{r.by}</span>
                  </Td>
                  <Td className="whitespace-nowrap">
                    <span className="block text-caption text-gray-600">{r.entity}</span>
                    <span className="block font-mono text-gray-900">{r.label}</span>
                    <span className="mt-1 block">
                      <ActionBadge action={r.action} />
                    </span>
                  </Td>
                  <Td className="min-w-[16rem] text-caption">
                    <ul className="space-y-0.5">
                      {r.changes.map((c) => (
                        <li key={c.field} className="break-words">
                          <span className="text-gray-600">{FIELDS[c.field]?.[0] ?? c.field}:</span>{' '}
                          {r.action === 'delete' ? (
                            <span className="text-gray-800">{show(c.field, c.old)}</span>
                          ) : c.field === 'solvent_adjustment' ? (
                            <span className="font-semibold text-gray-900">{show(c.field, c.new)}</span>
                          ) : (
                            <>
                              <span className="text-gray-800">{show(c.field, c.old)}</span> <span aria-hidden="true">→</span>
                              <span className="sr-only">menjadi</span> <span className="font-semibold text-gray-900">{show(c.field, c.new)}</span>
                            </>
                          )}
                        </li>
                      ))}
                    </ul>
                  </Td>
                  <Td className="min-w-[12rem]">{r.reason}</Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </ListBody>
    </section>
  );
}
