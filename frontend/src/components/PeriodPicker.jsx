import { useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { formatDate, formatDateRange, formatMonth, formatMonthShort, parseDate, toIso } from '../lib/format.js';

// Pemilih periode bersama dashboard dan laporan (FR-47, FR-55).
// Semua periode dipilih lewat satu model kalender: harian dan mingguan di kisi tanggal,
// bulanan di kisi 12 bulan, tahunan di kisi 12 tahun.
// Mingguan: setiap klik menjadi tanggal awal, tanggal akhir otomatis (tanggal awal + 6).
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
const YEARS_PER_PAGE = 12;
const PERIOD_NOUN = { day: 'harian', week: 'mingguan', month: 'bulanan', year: 'tahunan' };

// Hari-hari satu bulan dalam baris Senin s/d Minggu; null untuk sel kosong sebelum tanggal 1
function monthCells(monthIso) {
  const { y, m } = parseDate(monthIso);
  const lead = (new Date(y, m - 1, 1).getDay() + 6) % 7;
  const days = new Date(y, m, 0).getDate();
  return [...Array(lead).fill(null), ...Array.from({ length: days }, (_, i) => toIso(y, m, i + 1))];
}

/**
 * Halaman kalender yang dibuka pertama, sebagai tanggal ISO:
 * harian dan mingguan tanggal 1 bulan terpilih, bulanan 1 Januari tahun terpilih,
 * tahunan 1 Januari tahun terakhir di halaman 12 tahun. Halaman tahun terbaru berakhir di tahun ini.
 */
function initialPage(period, anchor, today) {
  const { y, m } = parseDate(anchor);
  if (period === 'day' || period === 'week') return toIso(y, m, 1);
  if (period === 'month') return toIso(y, 1, 1);
  const thisYear = parseDate(today).y;
  return toIso(thisYear - Math.floor((thisYear - y) / YEARS_PER_PAGE) * YEARS_PER_PAGE, 1, 1);
}

function stepPage(page, period, delta) {
  const { y, m } = parseDate(page);
  if (period === 'day' || period === 'week') {
    const d = new Date(y, m - 1 + delta, 1);
    return toIso(d.getFullYear(), d.getMonth() + 1, 1);
  }
  return toIso(y + delta * (period === 'year' ? YEARS_PER_PAGE : 1), 1, 1);
}

const NAV_LABEL = {
  day: ['Bulan sebelumnya', 'Bulan berikutnya'],
  week: ['Bulan sebelumnya', 'Bulan berikutnya'],
  month: ['Tahun sebelumnya', 'Tahun berikutnya'],
  year: [`${YEARS_PER_PAGE} tahun sebelumnya`, `${YEARS_PER_PAGE} tahun berikutnya`],
};

// Satu sel pilihan (bulan atau tahun): terpilih biru penuh, periode sekarang bergaris bawah
function cellTone({ selected, future, current }) {
  if (selected) return 'bg-brand-blue-600 font-semibold text-white';
  if (future) return 'text-gray-400';
  return `text-gray-800 hover:bg-gray-25 ${current ? 'font-semibold underline underline-offset-4' : ''}`;
}

/**
 * Kalender untuk semua periode, dibuka dari satu tombol. Klik langsung menerapkan pilihan
 * lalu kalender tertutup; halaman menampilkan loading seperti biasa.
 */
function PeriodCalendar({ period, anchor, end, today, onChange }) {
  const [open, setOpen] = useState(false);
  const [page, setPage] = useState(() => initialPage(period, anchor, today));
  // Mingguan: pratinjau 7 hari saat kursor atau fokus berada di sebuah tanggal
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
    setPage(initialPage(period, anchor, today));
    setOpen(true);
  };

  useEffect(() => {
    if (!open) return undefined;
    const grid = gridRef.current;
    (grid?.querySelector('[data-selected="true"]:not(:disabled)') ?? grid?.querySelector('button:not(:disabled)'))?.focus();
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

  const pick = (iso) => {
    if (period === 'week') onChange('week', iso, addDays(iso, MAX_WEEK_DAYS - 1));
    else onChange(period, iso);
    close();
  };

  const { y: pageYear } = parseDate(page);
  const thisYear = parseDate(today).y;
  const isDays = period === 'day' || period === 'week';
  const canNext = isDays ? page.slice(0, 7) < today.slice(0, 7) : pageYear < thisYear;
  const heading = isDays ? formatMonth(page) : period === 'month' ? String(pageYear) : `${pageYear - YEARS_PER_PAGE + 1}–${pageYear}`;
  const label = periodLabel(anchor, period, end);
  const caption = period === 'week' ? `Periode mingguan · ${daysBetween(anchor, end) + 1} hari` : `Periode ${PERIOD_NOUN[period]}`;

  const renderDays = () => {
    // Harian: hanya tanggal terpilih. Mingguan: pita 7 hari, ikut pratinjau saat kursor di tanggal lain
    const start = period === 'week' ? hover ?? anchor : anchor;
    const last = period === 'week' ? (hover ? addDays(hover, MAX_WEEK_DAYS - 1) : end) : anchor;
    return (
      <div ref={gridRef} className="mt-2 grid grid-cols-7 gap-y-1 text-center" onMouseLeave={() => setHover(null)}>
        {WEEKDAYS.map((w) => (
          <span key={w} aria-hidden="true" className="py-1 text-caption font-medium text-gray-600">
            {w}
          </span>
        ))}
        {monthCells(page).map((day, i) => {
          if (!day) return <span key={`kosong-${i}`} />;
          const isStart = day === start;
          const isEnd = day === last;
          const inRange = day > start && day < last;
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
          const edgeLabel = period === 'week' ? (isStart ? 'awal' : isEnd ? 'akhir' : null) : null;
          const selected = period === 'week' ? day >= anchor && day <= end : day === anchor;
          const suffix = period === 'week' ? (day === anchor ? ', tanggal awal' : day === end ? ', tanggal akhir' : '') : '';
          return (
            <button
              key={day}
              type="button"
              // Tanggal setelah hari ini tidak bisa dipilih; di mingguan tetap tampil di pita
              disabled={future}
              data-selected={day === anchor ? 'true' : undefined}
              aria-pressed={selected}
              aria-label={`${formatDate(day)}${suffix}`}
              onMouseEnter={() => period === 'week' && !future && setHover(day)}
              onFocus={() => period === 'week' && setHover(day)}
              onClick={() => pick(day)}
              className={`focus-ring flex h-12 flex-col items-center justify-center text-body leading-none tabular-nums transition-colors disabled:cursor-not-allowed ${shape} ${tone} ${
                isStart || isEnd ? 'font-semibold' : ''
              } ${day === today && !isStart && !isEnd ? 'font-semibold underline underline-offset-4' : ''}`}
            >
              {parseDate(day).d}
              {edgeLabel && <span className="mt-1 text-[11px] font-medium">{edgeLabel}</span>}
            </button>
          );
        })}
      </div>
    );
  };

  const renderMonths = () => (
    <div ref={gridRef} className="mt-3 grid grid-cols-3 gap-2">
      {Array.from({ length: 12 }, (_, i) => toIso(pageYear, i + 1, 1)).map((iso) => {
        const selected = iso === periodStart(anchor, 'month');
        const future = iso.slice(0, 7) > today.slice(0, 7);
        return (
          <button
            key={iso}
            type="button"
            disabled={future}
            data-selected={selected ? 'true' : undefined}
            aria-pressed={selected}
            aria-label={formatMonth(iso)}
            onClick={() => pick(iso)}
            className={`focus-ring h-12 rounded-lg text-body transition-colors disabled:cursor-not-allowed ${cellTone({
              selected,
              future,
              current: iso.slice(0, 7) === today.slice(0, 7),
            })}`}
          >
            {formatMonthShort(iso)}
          </button>
        );
      })}
    </div>
  );

  const renderYears = () => (
    <div ref={gridRef} className="mt-3 grid grid-cols-3 gap-2">
      {Array.from({ length: YEARS_PER_PAGE }, (_, i) => pageYear - YEARS_PER_PAGE + 1 + i).map((year) => {
        const selected = year === parseDate(anchor).y;
        const future = year > thisYear;
        return (
          <button
            key={year}
            type="button"
            disabled={future}
            data-selected={selected ? 'true' : undefined}
            aria-pressed={selected}
            onClick={() => pick(toIso(year, 1, 1))}
            className={`focus-ring h-12 rounded-lg text-body tabular-nums transition-colors disabled:cursor-not-allowed ${cellTone({
              selected,
              future,
              current: year === thisYear,
            })}`}
          >
            {year}
          </button>
        );
      })}
    </div>
  );

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
          <span className={`text-caption font-medium ${open ? 'text-brand-blue-700' : 'text-gray-600'}`}>{caption}</span>
          <span className="truncate text-body font-semibold tabular-nums text-gray-900">{label}</span>
        </span>
      </button>

      {open && (
        <div
          role="dialog"
          aria-label={`Pilih periode ${PERIOD_NOUN[period]}`}
          className="absolute left-0 top-full z-20 mt-2 w-full min-w-[18rem] rounded-card border border-gray-200 bg-white p-3 shadow-pop md:left-auto md:right-0 md:w-[22rem]"
        >
          {period === 'week' && <p className="mb-3 text-body text-gray-800">Klik tanggal awal. 7 hari sejak tanggal itu langsung terpilih.</p>}
          <div className="flex items-center justify-between gap-2">
            <button
              type="button"
              onClick={() => setPage(stepPage(page, period, -1))}
              aria-label={NAV_LABEL[period][0]}
              className="btn-secondary h-11 w-11 shrink-0"
            >
              <ChevronLeft className="h-5 w-5" aria-hidden="true" />
            </button>
            <p className="text-body font-semibold tabular-nums text-gray-900" aria-live="polite">
              {heading}
            </p>
            <button
              type="button"
              onClick={() => setPage(stepPage(page, period, 1))}
              disabled={!canNext}
              aria-label={NAV_LABEL[period][1]}
              className="btn-secondary h-11 w-11 shrink-0"
            >
              <ChevronRight className="h-5 w-5" aria-hidden="true" />
            </button>
          </div>

          {isDays ? renderDays() : period === 'month' ? renderMonths() : renderYears()}

          <div className="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3">
            <p className="text-caption text-gray-600" aria-live="polite">
              Dipakai: {label}
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

        <PeriodCalendar period={period} anchor={anchor} end={end} today={today} onChange={onChange} />

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
