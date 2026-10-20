import { LoaderCircle, WifiOff } from 'lucide-react';
import EmptyState from './EmptyState.jsx';

export function LoadingCard({ label }) {
  return (
    <div className="card flex items-center justify-center gap-3 px-4 py-16 text-body-lg text-gray-600" role="status">
      <LoaderCircle className="h-6 w-6 text-brand-blue-600 motion-safe:animate-spin" aria-hidden="true" />
      {label}
    </div>
  );
}

export function ErrorCard({ title, message, onRetry }) {
  return (
    <div className="card">
      <EmptyState
        icon={WifiOff}
        title={title}
        action={
          <button type="button" onClick={onRetry} className="btn-primary h-11 px-4 text-body">
            Coba lagi
          </button>
        }
      >
        {message}
      </EmptyState>
    </div>
  );
}
