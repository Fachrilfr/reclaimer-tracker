// Total hari kirim ke TPS: tanggal penuh dan terkirim dikurangi tanggal mulai diisi.
// Pallet yang masih diisi belum punya total.
export default function FillDays({ pallet }) {
  if (pallet.fill_days === null) return <span className="text-gray-600">–</span>;
  return <span className="whitespace-nowrap tabular-nums">{pallet.fill_days} hari</span>;
}
