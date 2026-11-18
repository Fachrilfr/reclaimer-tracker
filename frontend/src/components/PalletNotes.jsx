import CorrectedBadge from './CorrectedBadge.jsx';

// Keterangan yang diisi saat mulai dan saat pallet penuh (dan catatan drum data lama),
// serta tanda bila pallet pernah dikoreksi lewat Koreksi data
export default function PalletNotes({ pallet }) {
  if (!pallet.notes && !pallet.drum_notes.length && !pallet.correction) return <span className="text-gray-600">–</span>;

  return (
    <>
      <ul className="space-y-0.5">
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
      <CorrectedBadge correction={pallet.correction} className={pallet.notes || pallet.drum_notes.length ? 'mt-1' : ''} />
    </>
  );
}
