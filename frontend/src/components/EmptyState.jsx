// design.md §6.9: ikon garis 48px gray-400, satu kalimat penjelasan, satu tombol aksi
export default function EmptyState({ icon: Icon, title, children, action }) {
  return (
    <div className="flex flex-col items-center px-4 py-12 text-center">
      {Icon && <Icon className="h-12 w-12 text-gray-400" strokeWidth={1.5} aria-hidden="true" />}
      <p className="mt-4 max-w-md text-body-lg font-medium text-gray-900">{title}</p>
      {children && <div className="mt-1 max-w-md text-body text-gray-600">{children}</div>}
      {action && <div className="mt-6">{action}</div>}
    </div>
  );
}
