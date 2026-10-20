import { Clock, OctagonAlert, ShieldCheck, TriangleAlert, Truck } from 'lucide-react';

// Status masa simpan B3 (design.md §2.4). Sisa hari selalu ditulis dalam angka;
// warna dan ikon hanya penguat, bukan satu-satunya penanda.
const LEVELS = {
  aman: { label: 'Aman', icon: ShieldCheck, className: 'border-success-border bg-success-bg text-success-fg' },
  perhatian: { label: 'Perhatian', icon: Clock, className: 'border-warning-border bg-warning-bg text-warning-fg' },
  mendesak: { label: 'Mendesak', icon: TriangleAlert, className: 'border-urgent-border bg-urgent-bg text-urgent-fg' },
  kritis: { label: 'Kritis', icon: OctagonAlert, className: 'border-danger-border bg-danger-bg text-danger-fg' },
  diserahkan: { label: 'Diserahkan', icon: Truck, className: 'border-info-border bg-info-bg text-info-fg' },
};

export const STORAGE_FILL = {
  aman: 'bg-success-fg',
  perhatian: 'bg-warning-fg',
  mendesak: 'bg-urgent-fg',
  kritis: 'bg-danger-fg',
  diserahkan: 'bg-info-fg',
};

export function storageText({ level, days_remaining: days }) {
  if (level === 'diserahkan') return 'Diserahkan';
  if (days < 0) return `Lewat ${-days} hari`;
  if (days === 0) return `${LEVELS[level].label} · batas hari ini`;
  return `${LEVELS[level].label} · ${days} hari lagi`;
}

export default function StorageBadge({ storage }) {
  const { icon: Icon, className } = LEVELS[storage.level];

  return (
    <span
      className={`inline-flex items-center gap-1 whitespace-nowrap rounded-full border px-2 py-0.5 text-caption font-medium ${className}`}
    >
      <Icon className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
      {storageText(storage)}
    </span>
  );
}

/** Meter masa simpan yang sudah terpakai (FR-50). */
export function StorageMeter({ storage, label }) {
  if (storage.level === 'diserahkan') return null;
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
