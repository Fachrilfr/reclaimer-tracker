import { useEffect } from 'react';

const APP_NAME = 'Material Balance & B3 Tracker';

// H1 dan aksi utama di kanan (design.md §5). Judul tab browser ikut diganti.
export default function PageHeader({ title, description, actions }) {
  useEffect(() => {
    document.title = `${title} · ${APP_NAME}`;
  }, [title]);

  return (
    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div className="min-w-0">
        <h1 className="text-h1 font-bold">{title}</h1>
        {description && <p className="mt-1 max-w-3xl text-body text-gray-600">{description}</p>}
      </div>
      {actions && <div className="flex shrink-0 flex-wrap gap-3">{actions}</div>}
    </div>
  );
}
