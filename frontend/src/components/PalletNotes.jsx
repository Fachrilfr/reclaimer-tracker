import { tpsDelayText } from './StorageBadge.jsx';

// Keterangan yang diisi saat mulai dan saat pallet penuh (dan catatan drum data lama), ditambah keterangan
// otomatis dari sistem (misalnya terlambat karena mesin maintenance)
export default function PalletNotes({ pallet }) {
  const systemNote = tpsDelayText(pallet.tps);
  if (!pallet.notes && !pallet.drum_notes.length && !systemNote) return <span className="text-gray-600">–</span>;

  return (
    <ul className="space-y-0.5">
      {systemNote && (
        <li>
          <span className="text-gray-600">Sistem: </span>
          {systemNote}
        </li>
      )}
      {pallet.notes && (
        <li>
          <span className="text-gray-600">Pallet: </span>
          {pallet.notes}
        </li>
      )}
      {pallet.drum_notes.map((d) => (
        <li key={d.sequence}>
          <span className="text-gray-600">Drum ke-{d.sequence}: </span>
          {d.notes}
        </li>
      ))}
    </ul>
  );
}
