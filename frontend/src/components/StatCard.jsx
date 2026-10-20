// Kartu angka utama (design.md §6.3): label 13px gray-600, angka 30px tabular, catatan caption
export default function StatCard({ label, value, unit, note, tone = 'neutral' }) {
  const noteClassName = {
    neutral: 'text-gray-600',
    good: 'text-success-fg',
    bad: 'text-danger-fg',
  }[tone];

  return (
    <div className="card p-4 sm:p-5">
      <p className="text-label font-medium text-gray-600">{label}</p>
      <p className="mt-1 text-display font-bold tabular-nums text-gray-900">
        {value}
        {unit && <span className="ml-1 text-body-lg font-medium text-gray-600">{unit}</span>}
      </p>
      {note && <p className={`mt-1 text-caption ${noteClassName}`}>{note}</p>}
    </div>
  );
}
