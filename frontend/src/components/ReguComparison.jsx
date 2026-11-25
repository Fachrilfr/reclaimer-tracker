import { Table, Th, Td } from './Table.jsx';
import { formatNumber } from '../lib/format.js';

export default function ReguComparison({ rows }) {
  return <section className="card">
    <div className="p-4 sm:p-5">
      <h2 className="text-h2 font-semibold">Perbandingan per regu</h2>
      <p className="text-caption text-gray-600">Hasil dan pemakaian mengikuti tanggal input operasional. Estimasi residu dihitung sekali pada regu dan tanggal penyelesaian proses.</p>
    </div>
    <Table caption="Perbandingan per regu">
      <thead><tr><Th>Regu</Th><Th numeric>Hasil solvent (kg)</Th><Th numeric>Estimasi residu (kg)</Th><Th numeric>Pemakaian solvent (kg)</Th></tr></thead>
      <tbody>{rows.map((row) => <tr key={row.regu}><Td>{row.regu}</Td><Td numeric>{formatNumber(row.recovery)}</Td><Td numeric>{formatNumber(row.residue)}</Td><Td numeric>{formatNumber(row.used)}</Td></tr>)}</tbody>
    </Table>
  </section>;
}
