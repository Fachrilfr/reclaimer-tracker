import { Link } from 'react-router-dom';
import { Wrench } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { useNotifications } from '../notifications/NotificationContext.jsx';
import { formatDateTime } from '../lib/format.js';

// Banner di semua halaman selama mesin maintenance. Tidak bisa ditutup; hilang sendiri
// setelah mesin dinyatakan kembali beroperasi.
export default function MaintenanceBanner() {
  const { machine } = useNotifications();
  const { can } = useAuth();

  if (machine?.status !== 'maintenance') return null;

  return (
    <div role="status" className="mb-6 rounded-card border border-warning-border bg-warning-bg p-4 text-warning-fg">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex items-start gap-3">
          <Wrench className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
          <div className="text-body">
            <p className="font-semibold">
              {machine.name} ({machine.code}) sedang maintenance sejak{' '}
              {formatDateTime(machine.maintenance_started_at)}.
            </p>
            <p>
              Alasan: {machine.maintenance_reason}
              {machine.maintenance_reported_by && ` · Dilaporkan oleh ${machine.maintenance_reported_by}`}
              {machine.maintenance_expected_end_at &&
                ` · Perkiraan selesai ${formatDateTime(machine.maintenance_expected_end_at)}`}
              .
            </p>
            <p className="mt-1">
              Batch reclaimer tidak bisa dicatat dan pengiriman residu ke TPS ikut terhenti. Batas kirim pallet ke TPS
              tetap berjalan.
            </p>
          </div>
        </div>
        {can('mesin') && (
          <Link to="/mesin" className="btn-secondary h-11 shrink-0 px-4 text-body">
            Lihat status mesin
          </Link>
        )}
      </div>
    </div>
  );
}
