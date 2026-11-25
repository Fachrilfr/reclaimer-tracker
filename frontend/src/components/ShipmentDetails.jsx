import { Link } from 'react-router-dom';
import { Truck } from 'lucide-react';
import { formatDate } from '../lib/format.js';
import EmptyState from './EmptyState.jsx';
import { Kg, Section } from './ReportParts.jsx';
import { Table, Td, Th } from './Table.jsx';

export default function ShipmentDetails({ shipments }) {
  return (
      <Section id="b3-sj" title="Surat jalan">
        {shipments.length ? (
          <Table caption="Surat jalan di periode">
            <thead>
              <tr>
                <Th>No. surat jalan</Th>
                <Th>Serah terima</Th>
                <Th>Pallet</Th>
                <Th numeric>Drum diangkut</Th>
                <Th numeric>Berat vendor</Th>
                <Th>Petugas vendor</Th>
              </tr>
            </thead>
            <tbody>
              {shipments.map((sj) => (
                <tr key={sj.id}>
                  <Td className="min-w-[11rem]">
                    <Link
                      to={`/arsip?q=${encodeURIComponent(sj.number)}`}
                      className="focus-ring rounded font-mono font-medium text-brand-blue-700 hover:underline"
                    >
                      {sj.number}
                    </Link>
                  </Td>
                  <Td className="min-w-[12rem]">
                    {formatDate(sj.handover_date)}
                    <span className="block text-caption text-gray-600">{sj.vendor}</span>
                  </Td>
                  <Td>
                    {sj.pallets.map((p) => (
                      <span key={p.id} className="block whitespace-nowrap font-mono text-gray-900">
                        {p.pallet_number}
                      </span>
                    ))}
                  </Td>
                  <Td numeric>{sj.total_drums}</Td>
                  <Td numeric>
                    <Kg value={sj.weight_kg} />
                  </Td>
                  <Td className="whitespace-nowrap">{sj.vendor_officer_name ?? <span className="text-gray-600">–</span>}</Td>
                </tr>
              ))}
            </tbody>
          </Table>
        ) : (
          <EmptyState icon={Truck} title="Belum ada surat jalan di periode ini.">
            Pilih periode lain, atau buat surat jalan untuk pallet yang ada di TPS.
          </EmptyState>
        )}
      </Section>
  );
}
