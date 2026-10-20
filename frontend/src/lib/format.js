// Format angka dan tanggal Indonesia (design.md §3): 1.250,50 kg, 19 Okt 2026.

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const MONTHS_LONG = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

export function formatNumber(value, decimals = 2) {
  if (value === null || value === undefined || Number.isNaN(value)) return '–';
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(value);
}

export function formatPercent(value) {
  return `${formatNumber(value, 1)}%`;
}

// Tanggal dari API selalu YYYY-MM-DD; dipecah manual agar tidak bergeser zona waktu
export function parseDate(iso) {
  const [y, m, d] = iso.split('-').map(Number);
  return { y, m, d };
}

export function formatDate(iso) {
  if (!iso) return '–';
  const { y, m, d } = parseDate(iso);
  return `${d} ${MONTHS[m - 1]} ${y}`;
}

export function formatMonth(iso) {
  const { y, m } = parseDate(iso);
  return `${MONTHS_LONG[m - 1]} ${y}`;
}

export function formatDateRange(startIso, endIso) {
  const a = parseDate(startIso);
  const b = parseDate(endIso);
  if (startIso === endIso) return formatDate(startIso);
  if (a.m === b.m && a.y === b.y) return `${a.d}–${b.d} ${MONTHS[a.m - 1]} ${a.y}`;
  return `${formatDate(startIso)} – ${formatDate(endIso)}`;
}

export function toIso(y, m, d) {
  return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

/**
 * Angka dari input operator. Koma adalah desimal ("125,5"); bila ada koma, titik dianggap
 * pemisah ribuan ("1.250,5"). Tanpa koma, satu titik dianggap desimal ("125.5").
 * Mengembalikan null bila kosong, NaN bila bukan angka.
 */
export function parseDecimal(raw) {
  const text = String(raw ?? '').replace(/\s/g, '');
  if (!text) return null;
  const normalized = text.includes(',') ? text.replace(/\./g, '').replace(',', '.') : text;
  return /^\d+(\.\d+)?$/.test(normalized) ? Number(normalized) : Number.NaN;
}
