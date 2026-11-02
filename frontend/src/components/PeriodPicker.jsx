import { useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { formatDate, formatDateRange, formatMonth, parseDate, toIso } from '../lib/format.js';

// Pemilih periode bersama dashboard dan laporan (FR-47, FR-55).
// Mingguan dipilih di satu kalender: setiap klik menjadi tanggal awal, tanggal akhir otomatis.
// Rentang selalu 7 hari (tanggal awal + 6).
export const PERIOD_OPTIONS = {
  day: 'Harian',
  week: 'Mingguan',
  month: 'Bulanan',
  year: 'Tahunan',
};

// Sama dengan ReportPeriod::MAX_WEEK_DAYS di backend
const MAX_WEEK_DAYS = 7;

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

const WEEKDAYS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

// Hari-hari satu bulan dalam baris Senin s/d Minggu; null untuk sel kosong sebelum tanggal 1
function monthCells(monthIso) {
  const { y, m } = parseDate(monthIso);
  const lead = (new Date(y, m - 1, 1).getDay() + 6) % 7;
  const days = new Date(y, m, 0).getDate();
  return [...Array(lead).fill(null), ...Array.from({ length: days }, (_, i) => toIso(y, m, i + 1))];
}

/**
 * Mingguan dipilih di satu kalender: setiap klik selalu menjadi tanggal awal, dan 7 hari
 * sejak tanggal itu langsung terpilih dan diterapkan, lalu kalender tertutup.
 */
function WeekRangeCalendar({ anchor, end, today, onChange }) {
  const [open, setOpen] = useState(false);
  const [month, setMonth] = useState(anchor.slice(0, 7) + '-01');
  // Pratinjau 7 hari saat kursor atau fokus berada di sebuah tanggal
  const [hover, setHover] = useState(null);
  const rootRef = useRef(null);
  const triggerRef = useRef(null);
  const gridRef = useRef(null);

  const close = (restoreFocus = true) => {
    setOpen(false);
    setHover(null);
    if (restoreFocus) triggerRef.current?.focus();
  };

  const toggle = () => {
    if (open) return close();
    setMonth(anchor.slice(0, 7) + '-01');
    setOpen(true);
  };

  useEffect(() => {
    if (!open) return undefined;
    gridRef.current?.querySelector('[data-edge="start"]:not(:disabled)')?.focus();
    const onDown = (e) => !rootRef.current?.contains(e.target) && close(false);
    const onKey = (e) => e.key === 'Escape' && close();
    document.addEventListener('mousedown', onDown);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDown);
      document.removeEventListener('keydown', onKey);
    };
    // Dijalankan hanya saat kalender dibuka; close() hanya memakai setter dan ref
  }, [open]);

  // Klik langsung menerapkan 7 hari lalu menutup kalender; halaman menampilkan loading seperti biasa
  const pick = (day) => {
    onChange('week', day, addDays(day, MAX_WEEK_DAYS - 1));
    close();
  };

  const stepMonth = (delta) => {
    const { y, m } = parseDate(month);
    const d = new Date(y, m - 1 + delta, 1);
    setMonth(toIso(d.getFullYear(), d.getMonth() + 1, 1));
  };
  const canNextMonth = month.slice(0, 7) < today.slice(0, 7);

  const previewStart = hover ?? anchor;
  const previewEnd = hover ? addDays(hover, MAX_WEEK_DAYS - 1) : end;

  return (
    <div ref={rootRef} className="relative order-first w-full md:order-none md:w-auto">
      <button
        ref={triggerRef}
        type="button"
        onClick={toggle}
        aria-expanded={open}
        aria-haspopup="dialog"
        className={`focus-ring flex min-h-11 w-full items-center gap-3 rounded-lg border px-3 py-1.5 text-left transition-colors md:w-72 ${
          open ? 'border-brand-blue-600 bg-brand-blue-50' : 'border-gray-200 bg-white hover:bg-gray-25'
        }`}
      >
        <CalendarDays className={`h-5 w-5 shrink-0 ${open ? 'text-brand-blue-700' : 'text-gray-600'}`} aria-hidden="true" />
        <span className="flex min-w-0 flex-col">
          <span className={`text-caption font-medium ${open ? 'text-brand-blue-700' : 'text-gray-600'}`}>
            Periode mingguan · {daysBetween(anchor, end) + 1} hari
          </span>
          <span className="truncate text-body font-semibold tabular-nums text-gray-900">{formatDateRange(anchor, end)}</span>
        </span>
      </button>

      {open && (
        <div
          role="dialog"
          aria-label="Pilih minggu"
          className="absolute left-0 top-full z-20 mt-2 w-full min-w-[18rem] rounded-card border border-gray-200 bg-white p-3 shadow-pop md:left-auto md:right-0 md:w-[22rem]"
        >
          <p className="mb-3 text-body text-gray-800">Klik tanggal awal. 7 hari sejak tanggal itu langsung terpilih.</p>
          <div className="flex items-center justify-between gap-2">
            <button type="button" onClick={() => stepMonth(-1)} aria-label="Bulan sebelumnya" className="btn-secondary h-11 w-11 shrink-0">
              <ChevronLeft className="h-5 w-5" aria-hidden="true" />
            </button>
            <p className="text-body font-semibold text-gray-900" aria-live="polite">
              {formatMonth(month)}
            </p>
            <button
              type="button"
              onClick={() => stepMonth(1)}
              disabled={!canNextMonth}
              aria-label="Bulan berikutnya"
              className="btn-secondary h-11 w-11 shrink-0"
            >
              <ChevronRight className="h-5 w-5" aria-hidden="true" />
            </button>
          </div>

          <div ref={gridRef} className="mt-2 grid grid-cols-7 gap-y-1 text-center" onMouseLeave={() => setHover(null)}>
            {WEEKDAYS.map((w) => (
              <span key={w} aria-hidden="true" className="py-1 text-caption font-medium text-gray-600">
                {w}
              </span>
            ))}
            {monthCells(month).map((day, i) => {
              if (!day) return <span key={`kosong-${i}`} />;
              const isStart = day === previewStart;
              const isEnd = day === previewEnd;
              const inRange = day > previewStart && day < previewEnd;
              const future = day > today;
              // Pita rentang menyambung: tanggal awal membulat di kiri, tanggal akhir di kanan
              const shape = isStart && isEnd ? 'rounded-lg' : isStart ? 'rounded-l-lg' : isEnd ? 'rounded-r-lg' : inRange ? '' : 'rounded-lg';
              const tone = isStart
                ? 'bg-brand-blue-600 text-white'
                : isEnd
                  ? 'bg-brand-blue-800 text-white'
                  : inRange
                    ? 'bg-brand-blue-50 text-brand-blue-700'
                    : future
                      ? 'text-gray-400'
                      : 'text-gray-800 hover:bg-gray-25';
              const edgeLabel = isStart ? 'awal' : isEnd ? 'akhir' : null;
              return (
                <button
                  key={day}
                  type="button"
                  // Tanggal awal tidak boleh setelah hari ini; hari sesudahnya tetap tampil di pita
                  disabled={future}
                  data-edge={day === anchor ? 'start' : undefined}
                  aria-pressed={day >= anchor && day <= end}
                  aria-label={`${formatDate(day)}${day === anchor ? ', tanggal awal' : day === end ? ', tanggal akhir' : ''}`}
                  onMouseEnter={() => !future && setHover(day)}
                  onFocus={() => setHover(day)}
                  onClick={() => pick(day)}
                  className={`focus-ring flex h-12 flex-col items-center justify-center text-body leading-none tabular-nums transition-colors disabled:cursor-not-allowed ${shape} ${tone} ${
                    edgeLabel ? 'font-semibold' : ''
                  } ${day === today && !edgeLabel ? 'font-semibold underline underline-offset-4' : ''}`}
                >
                  {parseDate(day).d}
                  {edgeLabel && <span className="mt-1 text-[11px] font-medium">{edgeLabel}</span>}
                </button>
              );
            })}
          </div>

          <div className="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3">
            <p className="text-caption text-gray-600" aria-live="polite">
              Dipakai: {formatDateRange(anchor, end)}
            </p>
            <button type="button" onClick={() => close()} className="btn-secondary h-11 px-4 text-body">
              Tutup
            </button>
          </div>
        </div>
      )}
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

        {period === 'week' && <WeekRangeCalendar anchor={anchor} end={end} today={today} onChange={onChange} />}
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
