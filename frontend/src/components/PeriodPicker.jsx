import { useSearchParams } from 'react-router-dom';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { formatDate, formatDateRange, formatMonth, parseDate, toIso } from '../lib/format.js';

// Pemilih periode bersama dashboard dan laporan (FR-47, FR-55).
// Mingguan memakai tanggal awal dan akhir pilihan pengguna. Saat tanggal awal dipilih,
// tanggal akhir otomatis 7 hari (tanggal awal + 6), lalu boleh diubah.
export const PERIOD_OPTIONS = {
  day: 'Harian',
  week: 'Mingguan',
  month: 'Bulanan',
  year: 'Tahunan',
};

// Sama dengan ReportPeriod::MAX_WEEK_DAYS di backend
const MAX_WEEK_DAYS = 31;

const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

export function localToday() {
  const now = new Date();
  return toIso(now.getFullYear(), now.getMonth() + 1, now.getDate());
}

export function addDays(iso, days) {
  const { y, m, d } = parseDate(iso);
  const date = new Date(y, m - 1, d + days);
  return toIso(date.getFullYear(), date.getMonth() + 1, date.getDate());
}

function daysBetween(from, to) {
  const a = parseDate(from);
  const b = parseDate(to);
  return Math.round((new Date(b.y, b.m - 1, b.d) - new Date(a.y, a.m - 1, a.d)) / 86400000);
}

export function periodStart(iso, period) {
  const { y, m } = parseDate(iso);
  if (period === 'day' || period === 'week') return iso;
  if (period === 'month') return toIso(y, m, 1);
  return toIso(y, 1, 1);
}

// Geser periode satu langkah; mingguan bergeser sepanjang rentang yang dipilih
function shiftPeriod(anchor, end, period, step) {
  const { y, m } = parseDate(anchor);
  if (period === 'day') return { anchor: addDays(anchor, step) };
  if (period === 'week') {
    const length = daysBetween(anchor, end) + 1;
    return { anchor: addDays(anchor, step * length), end: addDays(end, step * length) };
  }
  const date = period === 'month' ? new Date(y, m - 1 + step, 1) : new Date(y + step, 0, 1);
  return { anchor: toIso(date.getFullYear(), date.getMonth() + 1, date.getDate()) };
}

export function periodLabel(iso, period, end) {
  if (period === 'day') return formatDate(iso);
  if (period === 'week') return formatDateRange(iso, end ?? addDays(iso, 6));
  if (period === 'month') return formatMonth(iso);
  return String(parseDate(iso).y);
}

/**
 * Periode dari query string (?period=&date=&end=), dipakai bersama dashboard dan laporan.
 * `query` siap ditempel ke URL API, termasuk `end` untuk mingguan.
 */
export function usePeriodParams(periods, defaultPeriod) {
  const [params, setParams] = useSearchParams();
  const today = localToday();
  const period = periods.includes(params.get('period')) ? params.get('period') : defaultPeriod;
  const rawDate = params.get('date');
  const anchor = rawDate && ISO_DATE.test(rawDate) && rawDate <= today ? rawDate : today;

  let end = null;
  if (period === 'week') {
    const rawEnd = params.get('end');
    const valid = rawEnd && ISO_DATE.test(rawEnd) && rawEnd >= anchor && daysBetween(anchor, rawEnd) < MAX_WEEK_DAYS;
    end = valid ? rawEnd : addDays(anchor, 6);
  }

  const change = (nextPeriod, nextAnchor, nextEnd) => {
    const date = nextAnchor > today ? today : nextAnchor;
    const next = { period: nextPeriod, date };
    // Tanggal akhir hanya dibawa bila masih di rentang yang sah; selain itu kembali ke 7 hari
    if (nextPeriod === 'week' && nextEnd && nextEnd >= date && daysBetween(date, nextEnd) < MAX_WEEK_DAYS) {
      next.end = nextEnd;
    }
    setParams(next, { replace: true });
  };

  const isCurrent = period === 'week' ? anchor <= today && today <= end : periodStart(anchor, period) === periodStart(today, period);

  return {
    period,
    anchor,
    end,
    today,
    isCurrent,
    label: periodLabel(anchor, period, end),
    query: `period=${period}&date=${anchor}${end ? `&end=${end}` : ''}`,
    change,
  };
}

// Di ponsel tanggal awal dan akhir mengisi satu baris sendiri di atas tombol geser
function WeekRange({ anchor, end, today, onChange }) {
  const labelClass = 'flex min-w-0 flex-col gap-1 md:flex-row md:items-center md:gap-2';
  return (
    <div className="order-first grid w-full grid-cols-2 gap-2 md:order-none md:flex md:w-auto">
      <label className={labelClass}>
        <span className="text-label font-medium text-gray-800">Dari</span>
        <input
          type="date"
          max={today}
          value={anchor}
          // Tanggal awal baru: tanggal akhir otomatis 7 hari, termasuk tanggal awal
          onChange={(e) => e.target.value && onChange('week', e.target.value, addDays(e.target.value, 6))}
          className="field min-w-0 md:w-40"
        />
      </label>
      <label className={labelClass}>
        <span className="text-label font-medium text-gray-800">Sampai</span>
        <input
          type="date"
          min={anchor}
          max={addDays(anchor, MAX_WEEK_DAYS - 1)}
          value={end}
          onChange={(e) => e.target.value && onChange('week', anchor, e.target.value)}
          className="field min-w-0 md:w-40"
        />
      </label>
    </div>
  );
}

export default function PeriodPicker({ periods, period, anchor, end, today, isCurrent, onChange }) {
  const next = shiftPeriod(anchor, end, period, 1);
  const prev = shiftPeriod(anchor, end, period, -1);
  const canGoNext = periodStart(next.anchor, period) <= today;
  const { y } = parseDate(today);

  // Mingguan "Sekarang": 7 hari terakhir sampai hari ini
  const goToday = () => (period === 'week' ? onChange('week', addDays(today, -6), today) : onChange(period, today));

  return (
    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div className="inline-flex rounded-lg border border-gray-200 bg-white p-1" role="group" aria-label="Jenis periode">
        {periods.map((value) => (
          <button
            key={value}
            type="button"
            aria-pressed={period === value}
            onClick={() => (value === 'week' && period !== 'week' ? onChange('week', anchor, addDays(anchor, 6)) : onChange(value, anchor, end))}
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
          onClick={() => onChange(period, prev.anchor, prev.end)}
          aria-label="Periode sebelumnya"
          className="btn-secondary h-11 w-11 shrink-0"
        >
          <ChevronLeft className="h-5 w-5" aria-hidden="true" />
        </button>

        {period === 'week' && <WeekRange anchor={anchor} end={end} today={today} onChange={onChange} />}
        {period !== 'week' && (
          <label htmlFor="period-anchor" className="sr-only">
            {`Pilih ${PERIOD_OPTIONS[period].toLowerCase()}`}
          </label>
        )}
        {period === 'day' && (
          <input
            id="period-anchor"
            type="date"
            max={today}
            value={anchor}
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
          onClick={() => onChange(period, next.anchor, next.end)}
          disabled={!canGoNext}
          aria-label="Periode berikutnya"
          className="btn-secondary h-11 w-11 shrink-0"
        >
          <ChevronRight className="h-5 w-5" aria-hidden="true" />
        </button>
        <button
          type="button"
          onClick={goToday}
          disabled={isCurrent}
          className="btn-secondary h-11 w-full shrink-0 px-4 text-body sm:w-auto"
        >
          Sekarang
        </button>
      </div>
    </div>
  );
}
