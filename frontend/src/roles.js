// Modul utama per peran, mengikuti rute default dari AuthController::login
export const ROLES = {
  operator: { label: 'Operator', module: 'Form input batch reclaimer' },
  supervisor: { label: 'Supervisor', module: 'Dashboard alert dan toleransi' },
  hse: { label: 'HSE', module: 'Monitoring limbah B3 dan masa simpan' },
  management: { label: 'Manajemen', module: 'Dashboard eksekutif dan laporan' },
  admin: { label: 'Admin', module: 'Kelola master data dan parameter' },
};

// Akun dari DatabaseSeeder, hanya ditampilkan saat `npm run dev`
export const SEEDED_ACCOUNTS = [
  { nik: 'OP-1001', name: 'Operator Aziz', role: 'operator' },
  { nik: 'SPV-2001', name: 'Supervisor Reclaimer', role: 'supervisor' },
  { nik: 'HSE-3001', name: 'HSE Officer', role: 'hse' },
  { nik: 'MGT-4001', name: 'Manajemen Divisi', role: 'management' },
  { nik: 'ADM-5001', name: 'Admin Sistem', role: 'admin' },
];

export const SEEDED_PASSWORD = 'password123';

export function roleInfo(role) {
  return ROLES[role] ?? { label: role, module: 'Beranda' };
}
