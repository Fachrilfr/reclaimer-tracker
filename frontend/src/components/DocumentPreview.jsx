import { useEffect, useRef, useState } from 'react';
import { FileText, LoaderCircle, Printer, X } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import ExportButtons from './ExportButtons.jsx';
import { ErrorCard } from './LoadState.jsx';

// Pilihan bagian yang dicetak. Nomor halaman tertentu tetap bisa dipilih di jendela cetak browser.
function PrintPanel({ sections, onToggle, onAll, onPrint }) {
  const count = sections.filter((s) => s.checked).length;

  return (
    <section id="print-panel" aria-labelledby="print-title" className="border-b border-gray-200 bg-white px-4 py-3 sm:px-6">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h3 id="print-title" className="text-body font-semibold text-gray-900">
          Pilih bagian yang dicetak
        </h3>
        <div className="flex gap-3 text-body">
          <button type="button" onClick={() => onAll(true)} className="focus-ring rounded font-medium text-brand-blue-700 hover:underline">
            Pilih semua
          </button>
          <button type="button" onClick={() => onAll(false)} className="focus-ring rounded font-medium text-brand-blue-700 hover:underline">
            Kosongkan
          </button>
        </div>
      </div>
      <div className="mt-2 flex flex-wrap gap-x-5 gap-y-1">
        {sections.map((s) => (
          <label key={s.name} className="flex min-h-11 cursor-pointer items-center gap-2 text-body text-gray-800">
            <input type="checkbox" checked={s.checked} onChange={() => onToggle(s.name)} className="h-5 w-5 accent-brand-blue-600" />
            {s.name}
          </label>
        ))}
      </div>
      <div className="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-caption text-gray-600">
          Untuk nomor halaman tertentu, pilih Halaman → Kustom di jendela cetak, misalnya 1-2 atau 3.
        </p>
        <button type="button" onClick={onPrint} disabled={!count} className="btn-primary h-11 px-4 text-body">
          <Printer className="h-5 w-5" aria-hidden="true" />
          Cetak {count} bagian
        </button>
      </div>
    </section>
  );
}

/**
 * Laporan dibaca dulu sebagai dokumen di aplikasi, tanpa mengunduh berkas (supaya tidak
 * ada penumpukan berkas bila hanya ingin melihat). Isinya sama dengan PDF; tombol unduh
 * PDF dan Excel hanya ada di dalam pratinjau ini.
 *
 * `basePath` contoh: /reports/b3 → /reports/b3/document dan /reports/b3/export
 */
export default function DocumentPreview({ basePath, title, period, anchor }) {
  const { request } = useAuth();
  const [open, setOpen] = useState(false);
  const [state, setState] = useState({ html: null, error: '', loading: false });
  const closeRef = useRef(null);
  const triggerRef = useRef(null);
  const frameRef = useRef(null);
  // Bagian dokumen (data-bagian di template laporan) yang bisa dipilih untuk dicetak
  const [sections, setSections] = useState([]);
  const [printing, setPrinting] = useState(false);

  const readSections = () => {
    const doc = frameRef.current?.contentDocument;
    if (!doc) return;
    setSections([...doc.querySelectorAll('[data-bagian]')].map((el) => ({ name: el.dataset.bagian, checked: true })));
  };

  // Bagian yang tidak dicentang disembunyikan saat dicetak saja; pratinjau tetap utuh
  const print = () => {
    const frame = frameRef.current;
    const doc = frame?.contentDocument;
    if (!doc) return;
    const chosen = new Set(sections.filter((s) => s.checked).map((s) => s.name));
    doc.querySelectorAll('[data-bagian]').forEach((el) => el.classList.toggle('tidak-dicetak', !chosen.has(el.dataset.bagian)));
    frame.contentWindow.print();
  };

  const load = async () => {
    setSections([]);
    setPrinting(false);
    setState({ html: null, error: '', loading: true });
    try {
      const { data } = await request(`${basePath}/document?period=${period}&date=${anchor}`);
      setState({ html: data.html, error: '', loading: false });
    } catch (err) {
      setState({ html: null, error: err.message, loading: false });
    }
  };

  const show = () => {
    setOpen(true);
    load();
  };

  const close = () => {
    setOpen(false);
    triggerRef.current?.focus();
  };

  useEffect(() => {
    if (!open) return undefined;
    closeRef.current?.focus();
    const onKey = (e) => e.key === 'Escape' && close();
    document.addEventListener('keydown', onKey);
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = previousOverflow;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  return (
    <>
      <button ref={triggerRef} type="button" onClick={show} className="btn-primary h-11 px-4 text-body">
        <FileText className="h-5 w-5" aria-hidden="true" />
        Lihat dokumen
      </button>

      {open && (
        <div className="fixed inset-0 z-50 flex flex-col bg-gray-900/50" role="dialog" aria-modal="true" aria-labelledby="preview-title">
          <div className="flex flex-col gap-3 border-b border-gray-200 bg-white px-4 py-3 sm:flex-row sm:items-start sm:justify-between sm:px-6">
            <div className="flex items-start gap-3">
              <button
                ref={closeRef}
                type="button"
                onClick={close}
                aria-label="Tutup pratinjau"
                className="focus-ring -ml-1 flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-25"
              >
                <X className="h-5 w-5" aria-hidden="true" />
              </button>
              <div>
                <h2 id="preview-title" className="text-h3 font-semibold text-gray-900">
                  {title}
                </h2>
                <p className="text-caption text-gray-600">Pratinjau dokumen. Unduh atau cetak hanya bila perlu.</p>
              </div>
            </div>
            <div className="flex flex-wrap items-start gap-3">
              <button
                type="button"
                onClick={() => setPrinting((v) => !v)}
                disabled={!sections.length}
                aria-expanded={printing}
                aria-controls="print-panel"
                className="btn-secondary h-11 px-4 text-body"
              >
                <Printer className="h-5 w-5" aria-hidden="true" />
                Cetak
              </button>
              <ExportButtons path={`${basePath}/export`} period={period} anchor={anchor} />
            </div>
          </div>

          {printing && (
            <PrintPanel
              sections={sections}
              onToggle={(name) => setSections((list) => list.map((s) => (s.name === name ? { ...s, checked: !s.checked } : s)))}
              onAll={(checked) => setSections((list) => list.map((s) => ({ ...s, checked })))}
              onPrint={print}
            />
          )}

          <div className="relative flex-1 overflow-hidden bg-gray-100">
            {state.loading && (
              <p role="status" className="flex h-full items-center justify-center gap-2 text-body text-gray-600">
                <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />
                Menyusun dokumen…
              </p>
            )}
            {state.error && (
              <div className="mx-auto max-w-xl p-6">
                <ErrorCard title="Dokumen belum bisa ditampilkan." message={state.error} onRetry={load} />
              </div>
            )}
            {state.html && (
              // sandbox tanpa allow-scripts: dokumen tidak menjalankan skrip apa pun.
              // allow-same-origin agar aplikasi bisa membaca bagian dokumen, allow-modals untuk jendela cetak.
              <iframe
                ref={frameRef}
                title={title}
                srcDoc={state.html}
                sandbox="allow-same-origin allow-modals"
                onLoad={readSections}
                className="h-full w-full border-0 bg-gray-100"
              />
            )}
          </div>
        </div>
      )}
    </>
  );
}
