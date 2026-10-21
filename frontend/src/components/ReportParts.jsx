import { formatDate, formatDateRange, formatMonth, formatNumber } from '../lib/format.js';

// Bagian yang dipakai bersama oleh halaman Laporan Reclaimer dan Laporan Limbah B3

export const REPORT_PERIODS = ['day', 'week', 'month', 'year'];

export const BREAKDOWN_LABEL = { day: 'Hari', week: 'Minggu', month: 'Bulan' };

export function Kg({ value }) {
  return (
    <span className="whitespace-nowrap">
      {formatNumber(value)} <span className="text-gray-600">kg</span>
    </span>
  );
}

/** Label baris rincian: tanggal, rentang minggu, atau bulan. */
export function bucketLabel(row, unit) {
  if (unit === 'month') return formatMonth(row.start);
  return row.start === row.end ? formatDate(row.start) : formatDateRange(row.start, row.end);
}

export function Section({ id, title, description, children, padded = false }) {
  return (
    <section aria-labelledby={id} className="card">
      <div className="p-4 sm:p-5">
        <h2 id={id} className="text-h2 font-semibold">
          {title}
        </h2>
        {description && <p className="mt-1 text-body text-gray-600">{description}</p>}
      </div>
      <div className={padded ? 'px-4 pb-4 sm:px-5 sm:pb-5' : ''}>{children}</div>
    </section>
  );
}
