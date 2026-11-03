// Keterangan yang diisi saat mulai dan saat pallet penuh (dan catatan drum data lama)
export default function PalletNotes({ pallet }) {
  if (!pallet.notes && !pallet.drum_notes.length) return <span className="text-gray-600">–</span>;

  return (
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
  );
}
