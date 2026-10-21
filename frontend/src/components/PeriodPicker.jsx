import { ChevronLeft, ChevronRight } from 'lucide-react';
import { formatDate, formatDateRange, formatMonth, parseDate, toIso } from '../lib/format.js';

// Pemilih periode bersama dashboard dan laporan (FR-47, FR-55).
// Minggu dihitung Senin sampai Minggu.
export const PERIOD_OPTIONS = {
  day: 'Harian',
  week: 'Mingguan',
  month: 'Bulanan',
  year: 'Tahunan',
};

export function localToday() {
  const now = new Date();
  return toIso(now.getFullYear(), now.getMonth() + 1, now.getDate());
}

function addDays(iso, days) {
  const { y, m, d } = parseDate(iso);
  const date = new Date(y, m - 1, d + days);
  return toIso(date.getFullYear(), date.getMonth() + 1, date.getDate());
}

export function periodStart(iso, period) {
  const { y, m, d } = parseDate(iso);
  if (period === 'day') return iso;
  if (period === 'week') return addDays(iso, -((new Date(y, m - 1, d).getDay() + 6) % 7));
  if (period === 'month') return toIso(y, m, 1);
  return toIso(y, 1, 1);
}

// Geser periode satu langkah; tanggal di luar bulan dibetulkan oleh Date
export function shiftAnchor(iso, period, step) {
  const { y, m, d } = parseDate(iso);
  if (period === 'day') return addDays(iso, step);
  if (period === 'week') return addDays(periodStart(iso, 'week'), step * 7);
  const date = period === 'month' ? new Date(y, m - 1 + step, 1) : new Date(y + step, 0, 1);
  return toIso(date.getFullYear(), date.getMonth() + 1, date.getDate());
}

export function periodLabel(iso, period) {
  if (period === 'day') return formatDate(iso);
  if (period === 'week') {
    const start = periodStart(iso, 'week');
    return formatDateRange(start, addDays(start, 6));
  }
  if (period === 'month') return formatMonth(iso);
  return String(parseDate(iso).y);
}

export default function PeriodPicker({ periods, period, anchor, today, onChange }) {
  const next = shiftAnchor(anchor, period, 1);
  const canGoNext = periodStart(next, period) <= today;
  const { y } = parseDate(today);

  return (
    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div className="inline-flex rounded-lg border border-gray-200 bg-white p-1" role="group" aria-label="Jenis periode">
        {periods.map((value) => (
          <button
            key={value}
            type="button"
            aria-pressed={period === value}
            onClick={() => onChange(value, anchor)}
            className={`focus-ring h-11 flex-1 rounded-md px-3 text-body font-semibold transition-colors sm:px-4 ${
              period === value ? 'bg-brand-blue-600 text-white' : 'text-gray-800 hover:bg-gray-25'
            }`}
          >
            {PERIOD_OPTIONS[value]}
          </button>
        ))}
      </div>

      <div className="flex flex-wrap items-center gap-2 sm:flex-nowrap">
        <button
          type="button"
          onClick={() => onChange(period, shiftAnchor(anchor, period, -1))}
          aria-label="Periode sebelumnya"
          className="btn-secondary h-11 w-11 shrink-0"
        >
          <ChevronLeft className="h-5 w-5" aria-hidden="true" />
        </button>

        <label htmlFor="period-anchor" className="sr-only">
          {period === 'week' ? 'Pilih salah satu tanggal di minggu itu' : `Pilih ${PERIOD_OPTIONS[period].toLowerCase()}`}
        </label>
        {(period === 'day' || period === 'week') && (
          <input
            id="period-anchor"
            type="date"
            max={today}
            value={period === 'week' ? periodStart(anchor, 'week') : anchor}
            onChange={(e) => e.target.value && onChange(period, e.target.value)}
            className="field min-w-0 flex-1 md:w-44 md:flex-none"
          />
        )}
        {period === 'month' && (
          <input
            id="period-anchor"
            type="month"
            max={today.slice(0, 7)}
            value={anchor.slice(0, 7)}
            onChange={(e) => e.target.value && onChange(period, `${e.target.value}-01`)}
            className="field min-w-0 flex-1 md:w-44 md:flex-none"
          />
        )}
        {period === 'year' && (
          <select
            id="period-anchor"
            value={parseDate(anchor).y}
            onChange={(e) => onChange(period, toIso(Number(e.target.value), 1, 1))}
            className="field min-w-0 flex-1 md:w-32 md:flex-none"
          >
            {Array.from({ length: 6 }, (_, i) => y - i).map((year) => (
              <option key={year} value={year}>
                {year}
              </option>
            ))}
          </select>
        )}

        <button
          type="button"
          onClick={() => onChange(period, next)}
          disabled={!canGoNext}
          aria-label="Periode berikutnya"
          className="btn-secondary h-11 w-11 shrink-0"
        >
          <ChevronRight className="h-5 w-5" aria-hidden="true" />
        </button>
        <button
          type="button"
          onClick={() => onChange(period, today)}
          disabled={periodStart(anchor, period) === periodStart(today, period)}
          className="btn-secondary h-11 w-full shrink-0 px-4 text-body sm:w-auto"
        >
          Sekarang
        </button>
      </div>
    </div>
  );
}
