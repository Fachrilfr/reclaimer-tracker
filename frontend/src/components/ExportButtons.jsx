import { useState } from 'react';
import { FileSpreadsheet, FileText, LoaderCircle } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import Alert from './Alert.jsx';

/** Tombol unduh Excel dan PDF untuk laporan. `path` contoh: /reports/b3/export */
export default function ExportButtons({ path, period, anchor }) {
  const { download } = useAuth();
  const [busy, setBusy] = useState(null);
  const [error, setError] = useState('');
  const [started, setStarted] = useState('');

  const run = async (format) => {
    setBusy(format);
    setError('');
    setStarted('');
    try {
      await download(`${path}?format=${format}&period=${period}&date=${anchor}`);
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
