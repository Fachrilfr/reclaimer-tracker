import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import PageHeader from '../../components/PageHeader.jsx';
import Alert from '../../components/Alert.jsx';
import BatchTab from './BatchTab.jsx';
import SolventTab from './SolventTab.jsx';
import PalletTab from './PalletTab.jsx';
import SuratJalanTab from './SuratJalanTab.jsx';
import MaintenanceTab from './MaintenanceTab.jsx';
import HistoryTab from './HistoryTab.jsx';

// Bagian yang dibuka disimpan di URL (?bagian=solvent) supaya tetap setelah muat ulang
const TABS = [
  { key: 'input', label: 'Input reclaimer', Component: BatchTab },
  { key: 'solvent', label: 'Pemakaian solvent', Component: SolventTab },
  { key: 'pallet', label: 'Pallet residu', Component: PalletTab },
  { key: 'surat-jalan', label: 'Surat jalan', Component: SuratJalanTab },
  { key: 'maintenance', label: 'Maintenance mesin', Component: MaintenanceTab },
  { key: 'riwayat', label: 'Riwayat koreksi', Component: HistoryTab },
];

/**
 * Koreksi data (Supervisor dan Super Admin): ubah atau hapus data yang salah ketik.
 * Alasan wajib, nilai lama tercatat di Riwayat koreksi, dan baris yang dikoreksi
 * bertanda Dikoreksi di semua daftar.
 */
export default function CorrectionPage() {
  const [params, setParams] = useSearchParams();
  const active = TABS.find((t) => t.key === params.get('bagian')) ?? TABS[0];
  const [message, setMessage] = useState('');

  const choose = (key) => {
    setMessage('');
    setParams(key === TABS[0].key ? {} : { bagian: key }, { replace: true });
  };

  const { Component } = active;
  return (
    <>
      <PageHeader
        title="Koreksi data"
        description="Perbaiki atau hapus data yang salah ketik. Alasan wajib diisi, nilai lama tetap tercatat di Riwayat koreksi, dan baris yang dikoreksi diberi tanda Dikoreksi."
      />
      <div className="my-6 max-w-full overflow-x-auto">
        <div role="tablist" aria-label="Jenis data" className="inline-flex rounded-lg border border-gray-200 bg-white p-1">
          {TABS.map((t) => (
            <button
              key={t.key}
              type="button"
              role="tab"
              aria-selected={active.key === t.key}
              onClick={() => choose(t.key)}
              className={`focus-ring h-11 whitespace-nowrap rounded-md px-4 text-body font-semibold transition-colors ${
                active.key === t.key ? 'bg-brand-blue-600 text-white' : 'text-gray-800 hover:bg-gray-25'
              }`}
            >
              {t.label}
            </button>
          ))}
        </div>
      </div>
      {message && (
        <Alert tone="success" className="mb-4">
          {message}
        </Alert>
      )}
      <Component key={active.key} onMessage={setMessage} />
    </>
  );
}
