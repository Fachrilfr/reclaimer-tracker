import { useEffect, useId, useRef, useState } from 'react';

// Grafik tren satu seri (FR-48). Mengikuti spesifikasi dataviz: garis 2px, titik r=4 dengan
// cincin putih 2px, kolom maksimal 24px dengan ujung atas membulat 4px, gridline hairline
// solid, satu sumbu. Satu seri, jadi tanpa kotak legenda: judul kartu yang menamainya.
// Hover dan panah keyboard menampilkan nilai; tabel di bawahnya memuat semua nilai.

const HEIGHT = 220;
const MARGIN = { top: 16, right: 72, bottom: 28, left: 52 };
const GRID = '#EEF1F5'; // gray-100
const AXIS_TEXT = '#5B6673'; // gray-600
const REFERENCE = '#2B333D'; // gray-800

// Langkah sumbu bulat (1, 2, 5 × 10^n) dengan sekitar 4–6 garis
function niceScale(maxValue) {
  if (maxValue <= 0) return { max: 1, step: 0.25 };
  const raw = maxValue / 4;
  const exp = 10 ** Math.floor(Math.log10(raw));
  const f = raw / exp;
  const step = (f < 1.5 ? 1 : f < 3.5 ? 2 : f < 7.5 ? 5 : 10) * exp;
  return { max: Math.ceil(maxValue / step) * step, step };
}

function columnPath(x, y, w, h, r) {
  const radius = Math.min(r, w / 2, h);
  return `M${x},${y + h} V${y + radius} Q${x},${y} ${x + radius},${y} H${x + w - radius} Q${x + w},${y} ${x + w},${y + radius} V${y + h} Z`;
}

/**
 * @param {{ key: string, label: string, longLabel: string, value: number|null, note?: string }[]} points
 * @param {'line'|'column'} kind
 */
export default function TrendChart({ title, points, kind, color, format, axisFormat, reference, emptyText }) {
  const containerRef = useRef(null);
  const [width, setWidth] = useState(640);
  const [active, setActive] = useState(null);
  const tableId = useId();

  useEffect(() => {
    const el = containerRef.current;
    if (!el) return undefined;
    const observer = new ResizeObserver(([entry]) => setWidth(Math.max(280, entry.contentRect.width)));
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  const hasData = points.some((p) => p.value !== null);
  const n = points.length;
  const innerW = width - MARGIN.left - MARGIN.right;
  const innerH = HEIGHT - MARGIN.top - MARGIN.bottom;
  const band = innerW / Math.max(n, 1);
  const maxValue = Math.max(0, ...points.map((p) => p.value ?? 0), reference?.value ?? 0);
  const scale = niceScale(maxValue * 1.1);
  const yMax = scale.max;
  const ticks = Array.from({ length: Math.round(yMax / scale.step) + 1 }, (_, i) => i * scale.step);
  const x = (i) => MARGIN.left + (i + 0.5) * band;
  const y = (v) => MARGIN.top + innerH * (1 - v / yMax);
  const labelStep = Math.max(1, Math.ceil((n * 34) / innerW));

  // Garis menyambung antar titik yang punya data. Hari tanpa rangkaian selesai tidak
  // digambar sebagai 0, karena tidak ada produksi bukan berarti recovery 0%.
  const segments = points
    .map((p, i) => (p.value === null ? null : `${x(i)},${y(p.value)}`))
    .filter(Boolean)
    .map((xy, i) => `${i === 0 ? 'M' : 'L'}${xy}`);

  const activePoint = active !== null ? points[active] : null;

  const moveTo = (index) => setActive(Math.min(n - 1, Math.max(0, index)));

  const handlePointer = (event) => {
    const rect = event.currentTarget.getBoundingClientRect();
    const scale = width / rect.width;
    const px = (event.clientX - rect.left) * scale;
    moveTo(Math.floor((px - MARGIN.left) / band));
  };

  const handleKey = (event) => {
    const keys = {
      ArrowRight: () => moveTo((active ?? -1) + 1),
      ArrowLeft: () => moveTo((active ?? n) - 1),
      Home: () => moveTo(0),
      End: () => moveTo(n - 1),
      Escape: () => setActive(null),
    };
    if (keys[event.key]) {
      event.preventDefault();
      keys[event.key]();
    }
  };

  if (!hasData) {
    return <p className="px-4 pb-6 text-body text-gray-600 sm:px-5">{emptyText}</p>;
  }

  const tooltipLeft = activePoint ? Math.min(Math.max(x(active) - 80, 0), width - 160) : 0;

  return (
    <div className="px-4 pb-4 sm:px-5 sm:pb-5">
      <div
        ref={containerRef}
        className="focus-ring relative rounded-lg"
        tabIndex={0}
        role="group"
        aria-label={`${title}. Tekan panah kiri atau kanan untuk membaca nilai per titik.`}
        aria-describedby={tableId}
        onKeyDown={handleKey}
        onBlur={() => setActive(null)}
      >
        <svg
          width={width}
          height={HEIGHT}
          viewBox={`0 0 ${width} ${HEIGHT}`}
          className="block max-w-full touch-pan-y"
          aria-hidden="true"
          onPointerMove={handlePointer}
          onPointerDown={handlePointer}
          onPointerLeave={() => setActive(null)}
        >
          {ticks.map((t) => (
            <g key={t}>
              <line x1={MARGIN.left} x2={MARGIN.left + innerW} y1={y(t)} y2={y(t)} stroke={GRID} strokeWidth="1" />
              <text x={MARGIN.left - 8} y={y(t)} dy="0.32em" textAnchor="end" fontSize="12" fill={AXIS_TEXT} className="tabular-nums">
                {axisFormat(t)}
              </text>
            </g>
          ))}

          {/* Label dijarangkan dari kanan, supaya titik terakhir (periode berjalan) selalu berlabel */}
          {points.map((p, i) =>
            (n - 1 - i) % labelStep === 0 ? (
              <text key={p.key} x={x(i)} y={HEIGHT - 8} textAnchor="middle" fontSize="12" fill={AXIS_TEXT}>
                {p.label}
              </text>
            ) : null,
          )}

          {activePoint && (
            <line x1={x(active)} x2={x(active)} y1={MARGIN.top} y2={MARGIN.top + innerH} stroke="#9AA5B1" strokeWidth="1" />
          )}

          {kind === 'column' &&
            points.map((p, i) => {
              if (p.value === null || p.value <= 0) return null;
              const w = Math.min(24, band * 0.6);
              return (
                <path
                  key={p.key}
                  d={columnPath(x(i) - w / 2, y(p.value), w, MARGIN.top + innerH - y(p.value), 4)}
                  fill={color}
                  opacity={active === null || active === i ? 1 : 0.55}
                />
              );
            })}

          {kind === 'line' && (
            <>
              <path d={segments.join(' ')} fill="none" stroke={color} strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" />
              {points.map((p, i) =>
                p.value === null ? null : (
                  <circle
                    key={p.key}
                    cx={x(i)}
                    cy={y(p.value)}
                    r={active === i ? 6 : 4}
                    fill={color}
                    stroke="#FFFFFF"
                    strokeWidth="2"
                  />
                ),
              )}
            </>
          )}

          {reference && (
            <g>
              <line
                x1={MARGIN.left}
                x2={MARGIN.left + innerW}
                y1={y(reference.value)}
                y2={y(reference.value)}
                stroke={REFERENCE}
                strokeWidth="1.5"
                strokeDasharray="6 4"
              />
              <text x={MARGIN.left + innerW + 6} y={y(reference.value)} dy="0.32em" fontSize="12" fontWeight="600" fill={REFERENCE}>
                {reference.label}
              </text>
            </g>
          )}
        </svg>

        {activePoint && (
          <div
            className="pointer-events-none absolute top-0 w-40 rounded-lg border border-gray-200 bg-white p-2 shadow-pop"
            style={{ left: tooltipLeft }}
          >
            <p className="text-caption text-gray-600">{activePoint.longLabel}</p>
            <p className="flex items-center gap-1.5 text-body font-semibold tabular-nums text-gray-900">
              <span aria-hidden="true" className="inline-block h-0.5 w-3 rounded" style={{ backgroundColor: color }} />
              {activePoint.value === null ? 'Tidak ada data' : format(activePoint.value)}
            </p>
            {activePoint.note && <p className="text-caption text-gray-600">{activePoint.note}</p>}
          </div>
        )}
        <p className="sr-only" aria-live="polite">
          {activePoint
            ? `${activePoint.longLabel}: ${activePoint.value === null ? 'tidak ada data' : format(activePoint.value)}`
            : ''}
        </p>
      </div>

      <details className="mt-3">
        <summary className="focus-ring inline-flex min-h-11 cursor-pointer items-center rounded text-body font-medium text-brand-blue-700">
          Lihat data dalam tabel
        </summary>
        <div className="mt-2 max-h-64 overflow-y-auto rounded-lg border border-gray-200">
          <table id={tableId} className="w-full text-left text-body">
            <caption className="sr-only">{title}</caption>
            <tbody>
              {points.map((p) => (
                <tr key={p.key} className="border-b border-gray-100 last:border-0">
                  <th scope="row" className="px-3 py-2 font-normal text-gray-600">
                    {p.longLabel}
                  </th>
                  <td className="px-3 py-2 text-right tabular-nums text-gray-900">
                    {p.value === null ? '–' : format(p.value)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </details>
    </div>
  );
}
