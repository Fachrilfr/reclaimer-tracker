import { useEffect, useId, useRef, useState } from 'react';

// Grafik tren satu seri (FR-48). Mengikuti spesifikasi dataviz: garis 2px, kolom maksimal
// 24px dengan ujung atas membulat 4px, gridline hairline solid, satu sumbu. Satu seri, jadi
// tanpa kotak legenda: judul kartu yang menamainya.
//
// Garis: kurva monoton (tidak pernah melampaui nilai sebenarnya, jadi tidak ada puncak
// palsu), area bergradasi tipis di bawahnya, titik hanya untuk yang aktif atau terpisah
// sendiri, garis putus-putus dari titik aktif ke sumbu, dan tooltip gelap di atas titik.
// Hover dan panah keyboard menampilkan nilai; tabel di bawahnya memuat semua nilai.

const HEIGHT = 240;
const MARGIN = { top: 20, right: 72, bottom: 28, left: 52 };
const GRID = '#EEF1F5'; // gray-100
const AXIS_TEXT = '#5B6673'; // gray-600
const REFERENCE = '#2B333D'; // gray-800
const GUIDE = '#9AA5B1'; // gray-400
const TOOLTIP_WIDTH = 168;

// Titik yang ditandai (misalnya di bawah target): merah tua (cukup beda dari hijau recovery
// untuk buta warna, ΔE 7,1 di validator dataviz) dan bentuk
// segitiga ke bawah, supaya bedanya terbaca tanpa mengandalkan warna saja
export const FLAG_COLOR = '#9A1C1C';
export const FLAG_TRIANGLE = (cx, cy, r) =>
  `M${cx - r},${cy - r * 0.75} L${cx + r},${cy - r * 0.75} L${cx},${cy + r} Z`;

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
 * Kurva kubik monoton (Fritsch–Carlson): halus seperti contoh desain, tetapi tidak
 * pernah naik atau turun melewati titik data, jadi tidak menampilkan nilai yang tidak ada.
 */
function monotonePath(pts) {
  if (pts.length === 1) return `M${pts[0].x},${pts[0].y}`;
  const n = pts.length;
  const d = [];
  for (let i = 0; i < n - 1; i++) d.push((pts[i + 1].y - pts[i].y) / (pts[i + 1].x - pts[i].x));
  const m = pts.map((_, i) => {
    if (i === 0) return d[0];
    if (i === n - 1) return d[n - 2];
    return d[i - 1] * d[i] <= 0 ? 0 : (d[i - 1] + d[i]) / 2;
  });
  for (let i = 0; i < n - 1; i++) {
    if (d[i] === 0) {
      m[i] = 0;
      m[i + 1] = 0;
      continue;
    }
    const a = m[i] / d[i];
    const b = m[i + 1] / d[i];
    const s = a * a + b * b;
    if (s > 9) {
      const t = 3 / Math.sqrt(s);
      m[i] = t * a * d[i];
      m[i + 1] = t * b * d[i];
    }
  }

  let path = `M${pts[0].x},${pts[0].y}`;
  for (let i = 0; i < n - 1; i++) {
    const h = (pts[i + 1].x - pts[i].x) / 3;
    path += ` C${pts[i].x + h},${pts[i].y + m[i] * h} ${pts[i + 1].x - h},${pts[i + 1].y - m[i + 1] * h} ${pts[i + 1].x},${pts[i + 1].y}`;
  }
  return path;
}

/**
 * @param {{ key: string, label: string, longLabel: string, value: number|null, note?: string }[]} points
 * @param {'line'|'column'} kind
 */
export default function TrendChart({ title, points, kind, color, format, axisFormat, reference, emptyText, flagLabel }) {
  const containerRef = useRef(null);
  const [width, setWidth] = useState(640);
  const [active, setActive] = useState(null);
  const tableId = useId();
  const gradientId = `area-${useId().replace(/:/g, '')}`;

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
  const baseline = MARGIN.top + innerH;
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
  const linePoints = points.flatMap((p, i) => (p.value === null ? [] : [{ x: x(i), y: y(p.value) }]));
  const line = linePoints.length ? monotonePath(linePoints) : '';
  const area =
    linePoints.length > 1
      ? `${line} L${linePoints[linePoints.length - 1].x},${baseline} L${linePoints[0].x},${baseline} Z`
      : '';
  // Titik tanpa tetangga berdata tidak membentuk garis, jadi tetap diberi penanda. Data
  // jarang (misalnya per bulan) juga diberi titik supaya posisi datanya terbaca.
  const showAllDots = linePoints.length <= 12;
  const isolated = (i) => (i === 0 || points[i - 1].value === null) && (i === n - 1 || points[i + 1].value === null);

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

  // Tooltip di atas titik aktif (atau ujung kolom). Boleh naik menutupi legenda di atas
  // grafik selama hover; baru pindah ke bawah titik bila tetap tidak muat.
  const anchorY = activePoint ? (activePoint.value === null ? baseline : y(activePoint.value)) : 0;
  const tooltipHeight = activePoint ? 52 + (activePoint.flagged ? 18 : 0) + (activePoint.note ? 18 : 0) : 0;
  const below = anchorY - 14 - tooltipHeight < -64;
  const tooltipCenter = activePoint ? Math.min(Math.max(x(active), TOOLTIP_WIDTH / 2), width - TOOLTIP_WIDTH / 2) : 0;
  const arrowOffset = activePoint ? x(active) - (tooltipCenter - TOOLTIP_WIDTH / 2) : 0;

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
          <defs>
            <linearGradient id={gradientId} x1="0" x2="0" y1="0" y2="1">
              <stop offset="0%" stopColor={color} stopOpacity="0.18" />
              <stop offset="100%" stopColor={color} stopOpacity="0" />
            </linearGradient>
          </defs>

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

          {kind === 'column' && activePoint && (
            <line x1={x(active)} x2={x(active)} y1={MARGIN.top} y2={baseline} stroke={GUIDE} strokeWidth="1" />
          )}

          {kind === 'column' &&
            points.map((p, i) => {
              if (p.value === null || p.value <= 0) return null;
              const w = Math.min(24, band * 0.6);
              return (
                <path
                  key={p.key}
                  d={columnPath(x(i) - w / 2, y(p.value), w, baseline - y(p.value), 4)}
                  fill={color}
                  opacity={active === null || active === i ? 1 : 0.55}
                />
              );
            })}

          {kind === 'line' && (
            <>
              {area && <path d={area} fill={`url(#${gradientId})`} />}
              <path d={line} fill="none" stroke={color} strokeWidth="2.5" strokeLinejoin="round" strokeLinecap="round" />
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

          {kind === 'line' && (
            <>
              {/* Garis putus-putus dari titik aktif ke sumbu */}
              {activePoint && activePoint.value !== null && (
                <line x1={x(active)} x2={x(active)} y1={y(activePoint.value)} y2={baseline} stroke={GUIDE} strokeWidth="1.5" strokeDasharray="4 4" />
              )}
              {points.map((p, i) => {
                if (p.value === null) return null;
                const isActive = active === i;
                if (p.flagged) {
                  return (
                    <path
                      key={p.key}
                      d={FLAG_TRIANGLE(x(i), y(p.value), isActive ? 7.5 : 5.5)}
                      fill={FLAG_COLOR}
                      stroke="#FFFFFF"
                      strokeWidth="2"
                      strokeLinejoin="round"
                    />
                  );
                }
                if (!isActive && !showAllDots && !isolated(i)) return null;
                return <circle key={p.key} cx={x(i)} cy={y(p.value)} r={isActive ? 6 : 4} fill={color} stroke="#FFFFFF" strokeWidth="2" />;
              })}
            </>
          )}
        </svg>

        {activePoint && (
          <div
            className="pointer-events-none absolute rounded-lg bg-brand-blue-900 px-3 py-2 text-center text-white shadow-pop"
            style={{
              width: TOOLTIP_WIDTH,
              left: tooltipCenter - TOOLTIP_WIDTH / 2,
              top: below ? anchorY + 14 : anchorY - 14,
              transform: below ? 'none' : 'translateY(-100%)',
            }}
          >
            <p className="text-body-lg font-bold tabular-nums">
              {activePoint.value === null ? 'Tidak ada data' : format(activePoint.value)}
            </p>
            <p className="text-caption text-white/75">{activePoint.longLabel}</p>
            {activePoint.flagged && (
              <p className="mt-0.5 flex items-center justify-center gap-1 text-caption font-semibold text-[#FCA5A5]">
                <svg width="10" height="10" aria-hidden="true">
                  <path d={FLAG_TRIANGLE(5, 5, 4.5)} fill="#FCA5A5" />
                </svg>
                {flagLabel}
              </p>
            )}
            {activePoint.note && <p className="text-caption text-white/75">{activePoint.note}</p>}
            {/* Ekor tooltip menunjuk ke titik */}
            <span
              aria-hidden="true"
              className="absolute h-2.5 w-2.5 rotate-45 bg-brand-blue-900"
              style={{ left: arrowOffset - 5, ...(below ? { top: -5 } : { bottom: -5 }) }}
            />
          </div>
        )}
        <p className="sr-only" aria-live="polite">
          {activePoint
            ? `${activePoint.longLabel}: ${activePoint.value === null ? 'tidak ada data' : format(activePoint.value)}${
                activePoint.flagged ? `, ${flagLabel}` : ''
              }`
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
                  <td className={`px-3 py-2 text-right tabular-nums ${p.flagged ? 'font-semibold text-danger-fg' : 'text-gray-900'}`}>
                    {p.value === null ? '–' : format(p.value)}
                    {p.flagged && <span className="block text-caption font-normal">{flagLabel}</span>}
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
