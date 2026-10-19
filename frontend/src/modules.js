import {
  Archive,
  BellRing,
  Biohazard,
  Cylinder,
  Database,
  FileChartColumn,
  History,
  LayoutDashboard,
  Recycle,
  SlidersHorizontal,
  Truck,
  Users,
} from 'lucide-react';

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
        page: null,
      },
      {
        key: 'batch',
        label: 'Input batch',
        path: '/batch',
        icon: Recycle,
        summary:
          'Catat Tinta X, solvent, hasil recovery, dan suhu per batch. Selisih, recovery rate, dan saldo solvent dihitung langsung.',
        page: null,
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
        summary: 'Pallet limbah B3 yang masih tersimpan, dengan hitung mundur masa simpan.',
        page: null,
      },
      {
        key: 'drum_b3',
        label: 'Input drum B3',
        path: '/drum-b3',
        icon: Cylinder,
        summary: 'Catat drum residu yang sudah penuh. Tiga drum penuh dicatat sebagai satu pallet.',
        page: null,
      },
      {
        key: 'pengingat',
        label: 'Pengingat',
        path: '/pengingat',
        icon: BellRing,
        summary: 'Pengingat pengangkutan bertingkat ke HSE, dikirim setiap hari pukul 07.00.',
        page: null,
      },
      {
        key: 'surat_jalan',
        label: 'Surat jalan',
        path: '/surat-jalan',
        icon: Truck,
        summary: 'Catat pengangkutan ke vendor: pallet, vendor, tanggal, nomor surat jalan, dan bukti.',
        page: null,
      },
      {
        key: 'arsip',
        label: 'Arsip manifest',
        path: '/arsip',
        icon: Archive,
        summary: 'Lengkapi nomor manifest dan unggah dokumen penutup untuk menutup pengangkutan.',
        page: null,
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
        page: null,
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
