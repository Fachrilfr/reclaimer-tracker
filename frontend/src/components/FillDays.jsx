// Lama pengisian pallet: sejak mulai diisi sampai penuh dan terkirim ke TPS (atau sampai
// hari ini bila masih diisi). Bila melewati batas kirim ke TPS, baris kedua menyebut lewatnya.
export default function FillDays({ pallet }) {
  const { fill_days: days, tps } = pallet;
  const filling = !tps.sent_at;
  const lateDays = filling ? Math.max(0, -tps.days_remaining) : tps.late_days;

  return (
    <>
      <span className="block whitespace-nowrap font-medium tabular-nums text-gray-900">
        {days === 0 ? 'Kurang dari 1 hari' : `${days} hari`}
      </span>
      {lateDays > 0 ? (
        <span className="block whitespace-nowrap text-caption font-medium text-danger-fg">Lewat batas kirim {lateDays} hari</span>
      ) : (
        <span className="block whitespace-nowrap text-caption text-gray-600">{filling ? 'masih diisi' : 'sampai terkirim ke TPS'}</span>
      )}
    </>
  );
}
