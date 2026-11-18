import { PencilLine } from 'lucide-react';
import { formatDateTime } from '../lib/format.js';

/**
 * Tanda baris yang sudah dikoreksi lewat Koreksi data, beserta siapa, kapan, dan alasannya.
 * Nilai lama dan baru ada di Koreksi data → Riwayat koreksi.
 */
export default function CorrectedBadge({ correction, className = 'mt-1' }) {
  if (!correction) return null;
  const detail = [correction.by, formatDateTime(correction.at)].filter(Boolean).join(', ');

  return (
    <span className={`block ${className}`}>
      <span className="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-25 px-2 py-0.5 text-caption font-medium text-gray-800">
        <PencilLine className="h-3.5 w-3.5" aria-hidden="true" />
        Dikoreksi
      </span>
      <span className="mt-0.5 block text-caption text-gray-600">
        {detail}
        {correction.reason && `: ${correction.reason}`}
      </span>
    </span>
  );
}
