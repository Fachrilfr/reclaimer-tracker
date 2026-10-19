# Reclaimer Tracker

Material Balance & B3 Tracker: pencatatan batch reclaimer, material balance, dan masa simpan limbah B3. Kebutuhan lengkap ada di PRD, panduan tampilan di `design.md`.

## Struktur Project

- `backend/`: API Laravel 13 (login NIK dengan Sanctum)
- `frontend/`: React + Vite + Tailwind

## Prasyarat

- PHP 8.3+ dengan ekstensi `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip` (dan `pdo_sqlite` untuk menjalankan test)
- Composer 2
- Node.js 20+
- MySQL atau MariaDB (misalnya dari XAMPP)

## Menjalankan di lokal

Buat database kosong bernama `reclaimer`, lalu:

```bash
# Backend
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve

# Frontend (terminal lain)
cd frontend
npm install
npm run dev
```

Buka http://localhost:5173. Akun uji dari seeder memakai kata sandi `password123`:

| NIK | Role |
| --- | --- |
| OP-1001 | Operator |
| SPV-2001 | Supervisor |
| HSE-3001 | HSE |
| MGT-4001 | Manajemen |
| ADM-5001 | Admin |

## Database

Skema database hanya dikelola lewat migrasi di `backend/database/migrations`, dan data awal lewat seeder di `backend/database/seeders`. Untuk mengubah tabel, buat migrasi baru (`php artisan make:migration`), jangan mengubah database langsung.

Untuk mengulang database dari awal (semua data terhapus):

```bash
php artisan migrate:fresh --seed
```

## Hak akses

Matriks hak akses PRD §5.1 ada di satu tempat: `backend/config/access.php`. Frontend menerimanya lewat `/api/auth/me`, jadi menu dan halaman ikut berubah tanpa perlu diubah terpisah.

- Route API modul memakai middleware `akses:<fitur>,<tingkat>`, misalnya `->middleware('akses:batch,isi')`.
- Daftar menu, path, dan ikon ada di `frontend/src/modules.js`. `key` di sana harus sama dengan nama fitur di `config/access.php`.
- Saat halaman modul selesai, isi `page` di `modules.js` dengan komponennya. Label "Segera" di menu hilang otomatis.

## Test

```bash
cd backend
php artisan test
```
