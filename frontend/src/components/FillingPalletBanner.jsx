import { Link, useLocation } from 'react-router-dom';
import { Cylinder } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { useNotifications } from '../notifications/NotificationContext.jsx';
import { formatDate, formatDateTime } from '../lib/format.js';

// Banner di semua halaman selama ada pallet yang sudah mulai diisi tapi belum ditandai penuh.
// Hilang sendiri setelah pallet ditekan "Pallet penuh" di Input pallet B3.
export default function FillingPalletBanner() {
  const { fillingPallet: pallet } = useNotifications();
  const { can } = useAuth();
  const { pathname } = useLocation();

  if (!pallet) return null;

  return (
    <div role="status" className="mb-6 rounded-card border border-info-border bg-info-bg p-4 text-info-fg">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-start gap-3">
          <Cylinder className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
          <div className="text-body">
            <p className="font-semibold">
              Pallet <span className="font-mono">{pallet.pallet_number}</span> sedang diisi, belum ditandai penuh.
            </p>
            <p>Mulai diisi {pallet.started_at ? formatDateTime(pallet.started_at) : formatDate(pallet.start_date)}.</p>
          </div>
        </div>
        {can('drum_b3') && pathname !== '/drum-b3' && (
          <Link to="/drum-b3" className="btn-secondary h-11 shrink-0 px-4 text-body">
            Buka Input pallet B3
          </Link>
        )}
      </div>
    </div>
  );
}
