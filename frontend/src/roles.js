export const ROLE_LABELS = {
  operator: 'Operator',
  supervisor: 'Supervisor',
  hse: 'HSE',
  management: 'Manajemen',
  admin: 'Super Admin',
};

export function roleLabel(role) {
  return ROLE_LABELS[role] ?? role;
}

// Kartu alert di dashboard per role, sesuai bagian masing-masing:
//   tps      = pallet yang harus dikirim dari area reclaimer ke TPS (bagian reclaimer)
//   storage  = masa simpan limbah B3 di TPS sampai diserahkan ke vendor (bagian HSE)
//   recovery = rangkaian di bawah target recovery (bagian reclaimer)
// Supervisor reclaimer tidak memantau TPS (sudah ada di Limbah B3 milik HSE), dan HSE
// tidak memantau target recovery.
export const DASHBOARD_ALERTS = {
  operator: { b3: 'tps', recovery: true },
  supervisor: { b3: 'tps', recovery: true },
  hse: { b3: 'storage', recovery: false },
  management: { b3: 'storage', recovery: true },
};

// Tingkat akses PRD §5. Kelola mencakup isi, tinjau, dan lihat; isi dan tinjau mencakup lihat.
// Harus sama dengan 'levels' di backend/config/access.php.
export const ACCESS_LEVELS = {
  kelola: { label: 'Kelola', meaning: 'Tambah, ubah, dan atur', includes: ['kelola', 'isi', 'tinjau', 'lihat'] },
  isi: { label: 'Isi', meaning: 'Input data', includes: ['isi', 'lihat'] },
  tinjau: { label: 'Tinjau', meaning: 'Memeriksa dan menindaklanjuti', includes: ['tinjau', 'lihat'] },
  lihat: { label: 'Lihat', meaning: 'Hanya baca', includes: ['lihat'] },
};

// Akun dari UserSeeder, hanya ditampilkan saat `npm run dev`
export const SEEDED_ACCOUNTS = [
  { nik: 'OP-1001', role: 'operator' },
  { nik: 'SPV-2001', role: 'supervisor' },
  { nik: 'HSE-3001', role: 'hse' },
  { nik: 'MGT-4001', role: 'management' },
  { nik: 'ADM-5001', role: 'admin' },
];

export const SEEDED_PASSWORD = 'password123';
