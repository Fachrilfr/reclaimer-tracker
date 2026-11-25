import { formatDate } from '../lib/format.js';
import StorageBadge from './StorageBadge.jsx';

export function fillShipment(pallets) {
  const amounts = {};
  let room = 20;
  for (const p of pallets) {
    const n = Math.min(room, p.available_drums);
    if (n > 0) amounts[p.id] = n;
    room -= n;
  }
  return amounts;
}

export default function DrumPicker({ pallets, amounts, onChange, error }) {
  const total = Object.values(amounts).reduce((sum, n) => sum + n, 0);
  function setAmount(id, n) {
    const next = { ...amounts };
    if (n) next[id] = n;
    else delete next[id];
    onChange(next);
  }
  const available = pallets.reduce((sum, p) => sum + p.available_drums, 0);
  const allSelected = total > 0 && total === Math.min(20, available);
  const checkboxClass = 'focus-ring h-4 w-4 shrink-0 accent-brand-blue-700';

  return (
    <fieldset className="min-w-0">
      <legend className="sr-only">Drum yang diangkut</legend>
      <div className="overflow-hidden rounded-lg border border-gray-200">
        {onChange && (
          <div className="flex flex-wrap items-center justify-between gap-x-4 border-b border-gray-200 bg-gray-25 px-4 py-1">
            <label className="flex min-h-11 cursor-pointer items-center gap-3 text-label font-semibold text-gray-900">
              <input
                type="checkbox"
                className={checkboxClass}
                checked={allSelected}
                ref={(input) => { if (input) input.indeterminate = total > 0 && !allSelected; }}
                disabled={!available}
                onChange={() => onChange(allSelected ? {} : fillShipment(pallets))}
                aria-label="Pilih semua, maksimal 20 drum"
              />
              Pilih semua
            </label>
            <div className="flex items-center gap-3 text-caption">
              {total > 0 && !allSelected && (
                <button type="button" className="focus-ring min-h-11 rounded text-brand-blue-700 hover:underline" onClick={() => onChange({})}>
                  Kosongkan
                </button>
              )}
              <span aria-live="polite" className="whitespace-nowrap tabular-nums text-gray-600">
                <strong className={total === 20 ? 'text-success-fg' : 'text-gray-900'}>{total}</strong> dari 20 drum
              </span>
            </div>
          </div>
        )}
        <ul className="divide-y divide-gray-100">
          {pallets.map((p) => {
            const count = amounts[p.id] ?? 0;
            const max = Math.min(p.available_drums, Math.max(count, 20 - total + count));
            return (
              <li key={p.id} className={`flex items-start gap-3 px-4 py-3 ${count ? 'bg-brand-blue-50' : ''}`}>
                {onChange && (
                  <input type="checkbox" className={`${checkboxClass} mt-1`} checked={count > 0}
                    aria-label={`Pilih ${p.pallet_number}`} disabled={!count && !max}
                    onChange={() => setAmount(p.id, count ? 0 : max)} />
                )}
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span className="font-mono font-medium text-gray-900">{p.pallet_number}</span>
                    {p.storage && <StorageBadge storage={p.storage} />}
                  </div>
                  <p className="mt-1 text-caption text-gray-600">{p.available_drums} drum tersedia &middot; Masuk TPS {formatDate(p.sent_to_tps_at)}</p>
                </div>
                {onChange && count > 0 && (
                  <label className="flex shrink-0 flex-col gap-1 text-caption text-gray-600">
                    Angkut (drum)
                    <select className="focus-ring rounded-md border border-gray-300 bg-white px-2 py-1 text-body text-gray-900"
                      aria-label={`Jumlah drum dari ${p.pallet_number}`} value={count}
                      onChange={(e) => setAmount(p.id, Number(e.target.value))}>
                      {Array.from({ length: max }, (_, i) => <option key={i + 1}>{i + 1}</option>)}
                    </select>
                  </label>
                )}
              </li>
            );
          })}
        </ul>
      </div>
      {error && <p className="mt-2 text-caption text-danger-fg">{error}</p>}
    </fieldset>
  );
}
