import { Clock, OctagonAlert, ShieldCheck, TriangleAlert, Truck } from 'lucide-react';
import { formatDate, formatDateTime } from '../lib/format.js';

// Status limbah B3 (design.md §2.4). Sisa hari selalu ditulis dalam angka;
// warna dan ikon hanya penguat, bukan satu-satunya penanda.
const LEVELS = {
  aman: { label: 'Aman', icon: ShieldCheck, className: 'border-success-border bg-success-bg text-success-fg' },
  perhatian: { label: 'Perhatian', icon: Clock, className: 'border-warning-border bg-warning-bg text-warning-fg' },
  mendesak: { label: 'Mendesak', icon: TriangleAlert, className: 'border-urgent-border bg-urgent-bg text-urgent-fg' },
  kritis: { label: 'Kritis', icon: OctagonAlert, className: 'border-danger-border bg-danger-bg text-danger-fg' },
  diserahkan: { label: 'Diserahkan', icon: Truck, className: 'border-info-border bg-info-bg text-info-fg' },
  terkirim: { label: 'Sudah dikirim', icon: Truck, className: 'border-info-border bg-info-bg text-info-fg' },
};

export const STORAGE_FILL = {
  aman: 'bg-success-fg',
  perhatian: 'bg-warning-fg',
  mendesak: 'bg-urgent-fg',
  kritis: 'bg-danger-fg',
  diserahkan: 'bg-info-fg',
  terkirim: 'bg-info-fg',
};

function countdownText({ level, days_remaining: days }) {
  if (days < 0) return `${LEVELS[level].label} · lewat ${-days} hari`;
  if (days === 0) return `${LEVELS[level].label} · batas hari ini`;
  return `${LEVELS[level].label} · ${days} hari lagi`;
}

/** Masa simpan di TPS sampai diserahkan ke vendor (HSE). */
export function storageText(storage) {
  if (storage.level === 'diserahkan') return 'Diserahkan';
  if (storage.days_remaining < 0) return `Lewat ${-storage.days_remaining} hari`;
  return countdownText(storage);
}

/** Pengiriman dari area reclaimer ke TPS (operator). */
export function tpsText(tps) {
  if (tps.level !== 'terkirim') return countdownText(tps);
  const late = tps.late_days > 0 ? ` · terlambat ${tps.late_days} hari` : '';
  return `Sudah dikirim ${formatDate(tps.sent_at)}${late}`;
}

/** Keterangan keterlambatan kirim ke TPS, misalnya karena mesin maintenance. */
export function tpsDelayText(tps) {
  const delay = tps.delay;
  if (!delay || delay.type !== 'machine_maintenance') return null;
  const period = `${formatDateTime(delay.start)} – ${delay.end ? formatDateTime(delay.end) : 'sekarang'}`;
  return `${tps.level === 'terkirim' ? 'Terlambat' : 'Lewat batas'} karena mesin maintenance (${period}): ${delay.reason}.`;
}

function Pill({ level, children }) {
  const { icon: Icon, className } = LEVELS[level];
  return (
    <span
      className={`inline-flex items-center gap-1 whitespace-nowrap rounded-full border px-2 py-0.5 text-caption font-medium ${className}`}
    >
      <Icon className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
      {children}
    </span>
  );
}

export default function StorageBadge({ storage }) {
  return <Pill level={storage.level}>{storageText(storage)}</Pill>;
}

/** compact: tanpa tanggal kirim, untuk tabel yang sudah punya kolom "Sampai di TPS". */
export function TpsBadge({ tps, compact = false }) {
  if (compact && tps.level === 'terkirim') {
    return <Pill level="terkirim">{tps.late_days > 0 ? `Terlambat ${tps.late_days} hari` : 'Tepat waktu'}</Pill>;
  }
  return <Pill level={tps.level}>{tpsText(tps)}</Pill>;
}

/** Meter hari yang sudah terpakai dari batas (FR-50), untuk masa simpan maupun batas kirim TPS. */
export function StorageMeter({ storage, label }) {
  if (storage.level === 'diserahkan' || storage.level === 'terkirim') return null;
  const used = Math.min(storage.days_elapsed, storage.max_days);
  const percent = Math.round((used / storage.max_days) * 100);

  return (
    <div
      role="meter"
      aria-label={label}
      aria-valuemin={0}
      aria-valuemax={storage.max_days}
      aria-valuenow={used}
      aria-valuetext={`${storage.days_elapsed} dari ${storage.max_days} hari terpakai`}
      className="h-2 w-full overflow-hidden rounded-full bg-gray-100"
    >
      <div className={`h-full rounded-full ${STORAGE_FILL[storage.level]}`} style={{ width: `${percent}%` }} />
    </div>
  );
}
