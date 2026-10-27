import { formatDate, formatDateTime } from './format.js';

// Waktu tombol "mulai diisi" dan "penuh" ditekan; data lama tanpa jam memakai tanggalnya saja.
// Dipakai tabel pallet operator (Input pallet B3) dan inventaris HSE (Limbah B3) supaya sama.
export const startedText = (p) => (p.started_at ? formatDateTime(p.started_at) : formatDate(p.start_date));
export const fullText = (p) => (p.full_at ? formatDateTime(p.full_at) : p.full_date ? formatDate(p.full_date) : null);
