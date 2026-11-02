import { useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { formatDate, formatDateRange, formatMonth, parseDate, toIso } from '../lib/format.js';

// Pemilih periode bersama dashboard dan laporan (FR-47, FR-55).
// Mingguan memakai tanggal awal dan akhir pilihan pengguna, dipilih di satu kalender.
// Saat tanggal awal dipilih, tanggal akhir otomatis 7 hari (tanggal awal + 6), lalu boleh diubah.
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

const WEEKDAYS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

// Hari-hari satu bulan dalam baris Senin s/d Minggu; null untuk sel kosong sebelum tanggal 1
function monthCells(monthIso) {
  const { y, m } = parseDate(monthIso);
  const lead = (new Date(y, m - 1, 1).getDay() + 6) % 7;
  const days = new Date(y, m, 0).getDate();
  return [...Array(lead).fill(null), ...Array.from({ length: days }, (_, i) => toIso(y, m, i + 1))];
}

/**
 * Tanggal awal dan akhir mingguan dipilih di satu kalender. Klik pertama = tanggal awal,
 * tanggal akhir langsung tersorot 7 hari (awal + 6). Klik kedua = tanggal akhir, atau
 * "Pakai 7 hari" untuk menerima rentang otomatis.
 */
function WeekRangeCalendar({ anchor, end, today, onChange }) {
  const [open, setOpen] = useState(false);
  // start null = menunggu tanggal awal; terisi = menunggu tanggal akhir
  const [start, setStart] = useState(null);
  const [month, setMonth] = useState(anchor.slice(0, 7) + '-01');
  const rootRef = useRef(null);
  const triggerRef = useRef(null);
  const gridRef = useRef(null);

  const close = (restoreFocus = true) => {
    setOpen(false);
    setStart(null);
    if (restoreFocus) triggerRef.current?.focus();
  };

  const toggle = () => {
    if (open) return close();
    setMonth(anchor.slice(0, 7) + '-01');
    setStart(null);
    setOpen(true);
  };

  useEffect(() => {
    if (!open) return undefined;
    // Fokus ke tanggal awal yang sedang dipakai (atau tanggal pertama yang bisa dipilih)
    const target = gridRef.current?.querySelector('[data-selected="true"]:not(:disabled)') ?? gridRef.current?.querySelector('button:not(:disabled)');
    target?.focus();
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

  const shownStart = start ?? anchor;
  const shownEnd = start ? addDays(start, 6) : end;
  const maxEnd = start ? addDays(start, MAX_WEEK_DAYS - 1) : null;

  const pick = (day) => {
    if (!start || day < start) {
      setStart(day);
      return;
    }
    onChange('week', start, day);
    close();
  };

  const stepMonth = (step) => {
    const { y, m } = parseDate(month);
    const d = new Date(y, m - 1 + step, 1);
    setMonth(toIso(d.getFullYear(), d.getMonth() + 1, 1));
  };
  const canNextMonth = month.slice(0, 7) < (maxEnd ?? today).slice(0, 7);

  return (
    <div ref={rootRef} className="relative order-first w-full md:order-none md:w-auto">
      <button
        ref={triggerRef}
        type="button"
        onClick={toggle}
        aria-expanded={open}
        aria-haspopup="dialog"
        className="field flex w-full items-center gap-2 text-left md:w-64"
      >
        <CalendarDays className="h-5 w-5 shrink-0 text-gray-600" aria-hidden="true" />
        <span className="sr-only">Rentang mingguan:</span>
        <span className="truncate tabular-nums">{formatDateRange(anchor, end)}</span>
      </button>

      {open && (
        <div
          role="dialog"
          aria-label="Pilih tanggal awal dan akhir"
          className="absolute left-0 top-full z-20 mt-2 w-full min-w-[18rem] rounded-card border border-gray-200 bg-white p-3 shadow-pop md:left-auto md:right-0 md:w-80"
        >
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

          <p className="mt-2 text-caption text-gray-600" aria-live="polite">
            {start
              ? `Mulai ${formatDate(start)}. Pilih tanggal akhir, paling lambat ${formatDate(maxEnd)}.`
              : 'Pilih tanggal awal.'}
          </p>

          <div ref={gridRef} className="mt-2 grid grid-cols-7 gap-y-1 text-center">
            {WEEKDAYS.map((w) => (
              <span key={w} aria-hidden="true" className="py-1 text-caption font-medium text-gray-600">
                {w}
              </span>
            ))}
            {monthCells(month).map((day, i) => {
              if (!day) return <span key={`kosong-${i}`} />;
              const disabled = start ? day > maxEnd : day > today;
              const isEdge = day === shownStart || day === shownEnd;
              const inRange = day > shownStart && day < shownEnd;
              return (
                <button
                  key={day}
                  type="button"
                  disabled={disabled}
                  data-selected={day === shownStart}
                  aria-pressed={isEdge || inRange}
                  aria-label={formatDate(day)}
                  onClick={() => pick(day)}
                  className={`focus-ring h-11 rounded-lg text-body tabular-nums transition-colors disabled:cursor-not-allowed disabled:text-gray-400 ${
                    isEdge
                      ? 'bg-brand-blue-600 font-semibold text-white'
                      : inRange
                        ? 'bg-brand-blue-50 text-brand-blue-700'
                        : 'text-gray-800 hover:bg-gray-25'
                  } ${day === today && !isEdge ? 'font-semibold underline underline-offset-4' : ''}`}
                >
                  {parseDate(day).d}
                </button>
              );
            })}
          </div>

          <div className="mt-3 flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-3">
            <button type="button" onClick={() => close()} className="btn-secondary h-11 px-4 text-body">
              Batal
            </button>
            {start && (
              <button
                type="button"
                onClick={() => {
                  onChange('week', start, addDays(start, 6));
                  close();
                }}
                className="btn-primary h-11 px-4 text-body"
              >
                Pakai 7 hari
              </button>
            )}
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
