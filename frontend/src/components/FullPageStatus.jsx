import { LoaderCircle } from 'lucide-react';
import TopBar from './TopBar.jsx';
import EmptyState from './EmptyState.jsx';

// Layar penuh sebelum sesi siap: saat memeriksa token, atau saat server tidak bisa dihubungi
export default function FullPageStatus({ loading = false, icon, title, children, action }) {
  return (
    <div className="flex min-h-screen flex-col">
      <TopBar />
      <main className="flex flex-1 items-center justify-center px-4">
        {loading ? (
          <p role="status" className="flex items-center gap-3 text-body-lg text-gray-600">
            <LoaderCircle className="h-6 w-6 text-brand-blue-600 motion-safe:animate-spin" aria-hidden="true" />
            {title}
          </p>
        ) : (
          <div className="card w-full max-w-lg">
            <EmptyState icon={icon} title={title} action={action}>
              {children}
            </EmptyState>
          </div>
        )}
      </main>
    </div>
  );
}
