// design.md §6.5: header gray-25 tanpa huruf kapital, baris 48px, angka rata kanan.
// Tabel yang lebih lebar dari layar digulir di dalam kartunya, bukan seluruh halaman.

export function Table({ caption, children }) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full border-collapse text-left text-body">
        {caption && <caption className="sr-only">{caption}</caption>}
        {children}
      </table>
    </div>
  );
}

export function Th({ numeric = false, className = '', children }) {
  return (
    <th
      scope="col"
      className={`h-11 border-b border-gray-200 bg-gray-25 px-4 text-label font-medium text-gray-600 ${
        numeric ? 'text-right' : ''
      } ${className}`}
    >
      {children}
    </th>
  );
}

export function Td({ numeric = false, className = '', children }) {
  return (
    <td
      className={`h-12 border-b border-gray-100 px-4 py-2 text-gray-800 ${
        numeric ? 'text-right tabular-nums' : ''
      } ${className}`}
    >
      {children}
    </td>
  );
}
