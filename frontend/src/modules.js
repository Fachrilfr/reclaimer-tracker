import {
  Archive,
  Biohazard,
  Cylinder,
  Database,
  Droplets,
  FileChartColumn,
  History,
  LayoutDashboard,
  Recycle,
  SlidersHorizontal,
  Truck,
  Users,
  Wrench,
} from 'lucide-react';
import MachinePage from './pages/MachinePage.jsx';
import ReportPage from './pages/ReportPage.jsx';
import DashboardPage from './pages/DashboardPage.jsx';
import BatchPage from './pages/BatchPage.jsx';
import DrumPage from './pages/DrumPage.jsx';
import SolventPage from './pages/SolventPage.jsx';
import WastePage from './pages/WastePage.jsx';
import SuratJalanPage from './pages/SuratJalanPage.jsx';
import ArchivePage from './pages/ArchivePage.jsx';

// `key` sama dengan nama fitur di backend/config/access.php. Urutan di sini = urutan menu.
// `page` diisi komponen halaman saat modulnya selesai; selama null, menu diberi label
// "Segera" dan halamannya menjelaskan bahwa modul belum tersedia.
// Ikon mengikuti design.md §9.
export const MODULE_GROUPS = [
  {
    label: 'Produksi',
    modules: [
      {
        key: 'dashboard',
        label: 'Dashboard',
        path: '/dashboard',
        icon: LayoutDashboard,
        summary: 'Hal yang perlu ditindak, empat angka utama, dan perbandingan per shift.',
        page: DashboardPage,
      },
      {
        key: 'batch',
        label: 'Input batch',
        path: '/batch',
        icon: Recycle,
        summary:
          'Catat Tinta X, hasil recovery, dan suhu per batch. Residu, recovery rate, dan saldo solvent dihitung langsung.',
        page: BatchPage,
      },
      {
        key: 'solvent',
        label: 'Pemakaian solvent',
        path: '/solvent',
        icon: Droplets,
        summary: 'Catat solvent yang diambil dari saldo hasil recovery. Saldo langsung berkurang.',
        page: SolventPage,
      },
      {
        key: 'mesin',
        label: 'Status mesin',
        path: '/mesin',
        icon: Wrench,
        summary: 'Laporkan maintenance mesin reclaimer dan saat mesin kembali beroperasi.',
        page: MachinePage,
      },
    ],
  },
  {
    label: 'Limbah B3',
    modules: [
      {
        key: 'limbah_b3',
        label: 'Limbah B3',
        path: '/limbah-b3',
        icon: Biohazard,
        summary:
          'Inventaris pallet dari input drum operator: status kirim ke TPS, hitung mundur masa simpan, dan pengingat ke HSE.',
        page: WastePage,
      },
      {
        key: 'drum_b3',
        label: 'Input drum B3',
        path: '/drum-b3',
        icon: Cylinder,
        summary: 'Catat drum residu yang sudah penuh. Tiga drum penuh dicatat sebagai satu pallet.',
        page: DrumPage,
      },
      {
        key: 'surat_jalan',
        label: 'Surat jalan',
        path: '/surat-jalan',
        icon: Truck,
        summary: 'Serahkan pallet dari TPS ke vendor: pallet, vendor, tanggal, nomor surat jalan, berat, dan bukti.',
        page: SuratJalanPage,
      },
      {
        key: 'arsip',
        label: 'Arsip surat jalan',
        path: '/arsip',
        icon: Archive,
        summary: 'Riwayat surat jalan sebagai penutup dokumen limbah B3, dengan pencarian dan unduhan bukti.',
        page: ArchivePage,
      },
    ],
  },
  {
    label: 'Laporan',
    modules: [
      {
        key: 'laporan',
        label: 'Laporan',
        path: '/laporan',
        icon: FileChartColumn,
        summary: 'Laporan harian, mingguan, bulanan, dan tahunan, dengan ekspor Excel dan PDF.',
        page: ReportPage,
      },
      {
        key: 'audit',
        label: 'Jejak audit',
        path: '/audit',
        icon: History,
        summary: 'Riwayat perubahan dari batch reclaimer sampai surat jalan dan dokumen penutup.',
        page: null,
      },
    ],
  },
  {
    label: 'Pengaturan',
    modules: [
      {
        key: 'parameter',
        label: 'Parameter',
        path: '/parameter',
        icon: SlidersHorizontal,
        summary: 'Target recovery, isi pallet, batas masa simpan, jenjang pengingat, dan penerimanya.',
        page: null,
      },
      {
        key: 'data_master',
        label: 'Data master',
        path: '/data-master',
        icon: Database,
        summary: 'Bahan, jenis limbah B3, shift, dan vendor.',
        page: null,
      },
      {
        key: 'pengguna',
        label: 'Pengguna',
        path: '/pengguna',
        icon: Users,
        summary: 'Akun pengguna, role, dan status aktif.',
        page: null,
      },
    ],
  },
];

export const MODULES = MODULE_GROUPS.flatMap((group) => group.modules);

export function moduleByKey(key) {
  return MODULES.find((module) => module.key === key);
}

export function moduleForPath(pathname) {
  return MODULES.find((module) => pathname === module.path || pathname.startsWith(`${module.path}/`));
}
