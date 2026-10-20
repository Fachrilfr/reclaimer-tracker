import { Hourglass } from 'lucide-react';

// Rangkaian batch yang masih menunggu batch lanjutan dari shift berikutnya
export default function UnfinishedBadge() {
  return (
    <span className="inline-flex items-center gap-1 whitespace-nowrap rounded-full border border-info-border bg-info-bg px-2 py-0.5 text-caption font-medium text-info-fg">
      <Hourglass className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
      Belum selesai
    </span>
  );
}
