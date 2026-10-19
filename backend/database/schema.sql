-- ==========================================================
-- Database Schema: reclaimer
-- System: Material Balance & B3 Tracker
-- PT Indofood CBP Sukses Makmur Tbk - Packaging Division
-- Berdasarkan PRD Terbaru (Draf 19 Oktober 2026)
-- Login Identifier: NIK (Nomor Induk Karyawan)
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `reclaimer` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `reclaimer`;

-- Matikan foreign key checks sementara untuk kemudahan migrasi/re-create
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `surat_jalan_pallets`;
DROP TABLE IF EXISTS `surat_jalan`;
DROP TABLE IF EXISTS `b3_notifications_log`;
DROP TABLE IF EXISTS `b3_drum_logs`;
DROP TABLE IF EXISTS `b3_pallets`;
DROP TABLE IF EXISTS `solvent_ledger`;
DROP TABLE IF EXISTS `reclaimer_batches`;
DROP TABLE IF EXISTS `machine_maintenance_logs`;
DROP TABLE IF EXISTS `parameters`;
DROP TABLE IF EXISTS `vendors`;
DROP TABLE IF EXISTS `materials`;
DROP TABLE IF EXISTS `shifts`;
DROP TABLE IF EXISTS `machines`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------
-- 1. Tabel Users & Hak Akses Role
-- Login menggunakan NIK (Nomor Induk Karyawan)
-- Role: operator, supervisor, hse, management, admin
-- ----------------------------------------------------------
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nik` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Nomor Induk Karyawan (kredensial login)',
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('operator', 'supervisor', 'hse', 'management', 'admin') NOT NULL DEFAULT 'operator',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. Tabel Mesin Reclaimer & Status Maintenance (FR-20, FR-21, FR-22)
-- ----------------------------------------------------------
CREATE TABLE `machines` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `status` ENUM('operating', 'maintenance', 'idle') NOT NULL DEFAULT 'operating',
    `maintenance_reason` TEXT NULL,
    `maintenance_started_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Riwayat Log Status Maintenance Mesin
CREATE TABLE `machine_maintenance_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `machine_id` INT UNSIGNED NOT NULL,
    `action` ENUM('start', 'end') NOT NULL,
    `reason` TEXT NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `logged_at` DATETIME NOT NULL,
    `notified_hse` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`machine_id`) REFERENCES `machines`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. Tabel Master Shift
-- ----------------------------------------------------------
CREATE TABLE `shifts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(50) NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4. Tabel Master Bahan (Material) - Tinta X, Solvent
-- ----------------------------------------------------------
CREATE TABLE `materials` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('ink', 'solvent', 'other') NOT NULL DEFAULT 'ink',
    `unit` VARCHAR(20) NOT NULL DEFAULT 'kg',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 5. Tabel Master Vendor Pengangkut Limbah B3
-- ----------------------------------------------------------
CREATE TABLE `vendors` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `pic_name` VARCHAR(100) NULL,
    `contact` VARCHAR(50) NULL,
    `address` TEXT NULL,
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 6. Tabel Parameter Sistem (Section 8)
-- Target recovery (35%), batas simpan (90 hari), jenjang pengingat (30, 14, 7 hari), dll.
-- ----------------------------------------------------------
CREATE TABLE `parameters` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `param_key` VARCHAR(50) NOT NULL UNIQUE,
    `param_value` VARCHAR(100) NOT NULL,
    `unit` VARCHAR(20) NULL,
    `description` VARCHAR(255) NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 7. Tabel Transaksi Batch Reclaimer (FR-01 s/d FR-08)
-- Material Balance Otomatis:
-- input total = Tinta X + solvent dipakai
-- residu (estimasi) = input total - recovery
-- recovery rate = (recovery / input total) * 100
-- ----------------------------------------------------------
CREATE TABLE `reclaimer_batches` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `batch_number` VARCHAR(50) NOT NULL UNIQUE,
    `date` DATE NOT NULL,
    `shift_id` INT UNSIGNED NOT NULL,
    `regu` VARCHAR(20) NULL COMMENT 'Regu kerja, contoh: B2, AX, C7',
    `operator_id` BIGINT UNSIGNED NOT NULL,
    `machine_id` INT UNSIGNED NOT NULL,
    `material_id` INT UNSIGNED NOT NULL COMMENT 'Bahan baku (Tinta X)',
    
    -- Opsi Batch Lanjutan dari Shift Sebelumnya (FR-05)
    `is_continuation` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Batch lanjutan tanpa Tinta baru',
    `parent_batch_id` BIGINT UNSIGNED NULL COMMENT 'Relasi ke batch awal jika lanjutan',
    
    -- Input Berat (kg) (FR-02)
    `ink_weight` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Berat Tinta X yang masuk ke mesin (kg)',
    `solvent_used_weight` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Solvent yang dipakai (kg)',
    `recovery_weight` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Hasil recovery solvent bersih (kg)',
    
    -- Suhu Operasional (FR-03)
    `oil_temp` DECIMAL(5, 2) NOT NULL COMMENT 'Oil Temperature (Normal 100 - 160 C)',
    `vapour_temp` DECIMAL(5, 2) NOT NULL COMMENT 'Vapour Temperature (Normal 40 - 60 C)',
    `is_temp_abnormal` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'True bila di luar rentang normal',
    
    -- Hasil Perhitungan Otomatis Material Balance (FR-04)
    `total_input_weight` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Input total = Tinta X + Solvent dipakai',
    `residue_estimated_weight` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Residu (estimasi) = Input total - recovery',
    `recovery_rate_percent` DECIMAL(5, 2) NOT NULL DEFAULT 0.00 COMMENT 'Recovery rate = recovery / total input (%)',
    `residue_rate_percent` DECIMAL(5, 2) NOT NULL DEFAULT 0.00 COMMENT 'Residu rate = residu / total input (%)',
    
    -- Status & Alert (FR-45)
    `is_recovery_low` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Alert bila recovery rate < target (35%)',
    `supervisor_reviewed` BOOLEAN NOT NULL DEFAULT FALSE,
    `supervisor_notes` TEXT NULL,
    `notes` TEXT NULL COMMENT 'Keterangan operasional / quick buttons (Start Pkl, Stop Pkl, dll)',
    
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`),
    FOREIGN KEY (`operator_id`) REFERENCES `users`(`id`),
    FOREIGN KEY (`machine_id`) REFERENCES `machines`(`id`),
    FOREIGN KEY (`material_id`) REFERENCES `materials`(`id`),
    FOREIGN KEY (`parent_batch_id`) REFERENCES `reclaimer_batches`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 8. Tabel Buku Besar Mutasi Saldo Solvent (FR-10, FR-11, FR-12)
-- Saldo berkurang saat solvent dipakai, bertambah saat recovery
-- ----------------------------------------------------------
CREATE TABLE `solvent_ledger` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `mutation_date` DATETIME NOT NULL,
    `type` ENUM('in', 'out', 'adjustment') NOT NULL COMMENT 'in: hasil recovery, out: solvent dipakai, adjustment: penyesuaian manual',
    `amount` DECIMAL(10, 2) NOT NULL COMMENT 'Jumlah mutasi (kg)',
    `previous_balance` DECIMAL(10, 2) NOT NULL,
    `current_balance` DECIMAL(10, 2) NOT NULL,
    `batch_id` BIGINT UNSIGNED NULL COMMENT 'Relasi jika mutasi berasal dari batch reclaimer',
    `reason` VARCHAR(255) NULL COMMENT 'Alasan penyesuaian saldo (wajib jika adjustment)',
    `user_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`batch_id`) REFERENCES `reclaimer_batches`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 9. Tabel Pallet Limbah B3 & Masa Simpan (FR-25 s/d FR-31)
-- 1 Pallet = 3 Drum. Masa simpan default 90 hari sejak pallet mulai terisi
-- ----------------------------------------------------------
CREATE TABLE `b3_pallets` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `pallet_number` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Nomor pallet unik, misal: PLT-202610-001',
    `drum_count` INT NOT NULL DEFAULT 0 COMMENT 'Jumlah drum terisi (maksimal 3)',
    `start_date` DATE NOT NULL COMMENT 'Tanggal mulai terisi (titik awal hitungan masa simpan)',
    `full_date` DATE NULL COMMENT 'Tanggal pallet penuh (3 drum)',
    `max_storage_days` INT NOT NULL DEFAULT 90 COMMENT 'Batas simpan (default 90 hari)',
    `expiry_date` DATE NOT NULL COMMENT 'Batas akhir masa simpan = start_date + max_storage_days',
    
    -- Status Siklus Hidup Pallet
    `status` ENUM('filling', 'full', 'scheduled', 'transported') NOT NULL DEFAULT 'filling',
    
    -- Konfirmasi Jadwal Pengangkutan (FR-29)
    `scheduled_date` DATE NULL COMMENT 'Tanggal terjadwal pengangkutan',
    `scheduled_vendor_id` INT UNSIGNED NULL COMMENT 'Vendor yang dijadwalkan',
    
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (`scheduled_vendor_id`) REFERENCES `vendors`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log Pengisian Drum ke Pallet (1 Pallet = 3 Drum)
CREATE TABLE `b3_drum_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `pallet_id` BIGINT UNSIGNED NOT NULL,
    `drum_sequence` INT NOT NULL COMMENT 'Drum ke-1, ke-2, atau ke-3',
    `filled_date` DATE NOT NULL,
    `operator_id` BIGINT UNSIGNED NOT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`pallet_id`) REFERENCES `b3_pallets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`operator_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 10. Log Notifikasi Pengingat Masa Simpan Limbah B3 (FR-27, FR-28, FR-30)
-- Jenjang: awal (sisa 30 hari), mendesak (14 hari), kritis (7 hari)
-- ----------------------------------------------------------
CREATE TABLE `b3_notifications_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `pallet_id` BIGINT UNSIGNED NOT NULL,
    `notification_type` ENUM('early_30d', 'urgent_14d', 'critical_7d', 'machine_maintenance') NOT NULL,
    `days_remaining` INT NOT NULL COMMENT 'Sisa hari eksak',
    `recipient_role` VARCHAR(50) NOT NULL DEFAULT 'hse',
    `message` TEXT NOT NULL,
    `sent_at` DATETIME NOT NULL,
    `is_read` BOOLEAN NOT NULL DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`pallet_id`) REFERENCES `b3_pallets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 11. Tabel Surat Jalan Limbah B3 & Dokumen Penutup (FR-35 s/d FR-40)
-- Menggantikan manifest langsung di tahap serah terima
-- Status: menunggu_dokumen_penutup -> ditutup
-- ----------------------------------------------------------
CREATE TABLE `surat_jalan` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `surat_jalan_number` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Nomor surat jalan resmi',
    `vendor_id` INT UNSIGNED NOT NULL,
    `handover_date` DATE NOT NULL COMMENT 'Tanggal serah terima limbah ke vendor',
    `total_pallets` INT NOT NULL DEFAULT 1 COMMENT 'Jumlah pallet yang diangkut (minimal 1 pallet)',
    `drum_weight_total` DECIMAL(10, 2) NULL COMMENT 'Berat drum total (opsional)',
    `vendor_officer_name` VARCHAR(100) NULL COMMENT 'Nama petugas vendor yang menerima (opsional)',
    `file_path_surat_jalan` VARCHAR(255) NOT NULL COMMENT 'Bukti surat jalan wajib terunggah (PDF/JPG/PNG)',
    
    -- Manifest & Dokumen Penutup (Menyusul setelah disetujui pengelola)
    `manifest_number` VARCHAR(100) NULL COMMENT 'Nomor manifest resmi (opsional / boleh menyusul)',
    `file_path_closing_doc` VARCHAR(255) NULL COMMENT 'Bukti dokumen penutup (PDF/JPG/PNG)',
    `closing_doc_uploaded_at` DATETIME NULL,
    `status` ENUM('waiting_closing_doc', 'closed') NOT NULL DEFAULT 'waiting_closing_doc',
    
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relasi Surat Jalan dengan Pallet yang Diangkut
CREATE TABLE `surat_jalan_pallets` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `surat_jalan_id` BIGINT UNSIGNED NOT NULL,
    `pallet_id` BIGINT UNSIGNED NOT NULL,
    
    FOREIGN KEY (`surat_jalan_id`) REFERENCES `surat_jalan`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`pallet_id`) REFERENCES `b3_pallets`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 12. Tabel Jejak Audit (Audit Logs) - FR-57, ISO 14001
-- ----------------------------------------------------------
CREATE TABLE `audit_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NULL,
    `action` VARCHAR(50) NOT NULL COMMENT 'login, create, update, delete, review, schedule, close',
    `entity_type` VARCHAR(50) NOT NULL COMMENT 'users, reclaimer_batches, b3_pallets, surat_jalan, machine, parameters',
    `entity_id` BIGINT UNSIGNED NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Initial Master Data & Parameter Seeding (Section 8)
-- ==========================================================

-- Parameter Sistem Awal (Section 8)
INSERT INTO `parameters` (`param_key`, `param_value`, `unit`, `description`) VALUES
('target_recovery_rate', '35.0', '%', 'Target minimum recovery rate solvent (Usulan 35%)'),
('b3_max_storage_days', '90', 'hari', 'Batas maksimal masa simpan limbah B3'),
('b3_reminder_early_days', '30', 'hari', 'Pengingat awal masa simpan pallet B3 (sisa 30 hari)'),
('b3_reminder_urgent_days', '14', 'hari', 'Pengingat status mendesak masa simpan pallet B3 (sisa 14 hari)'),
('b3_reminder_critical_days', '7', 'hari', 'Pengingat status kritis masa simpan pallet B3 (sisa 7 hari)'),
('min_pallet_per_delivery', '1', 'pallet', 'Syarat minimal pallet per pengangkutan limbah'),
('drums_per_pallet', '3', 'drum', 'Kapasitas drum per satu pallet B3 (1 pallet = 3 drum)'),
('max_vendor_delivery_weeks', '3', 'minggu', 'Batas maksimal penyerahan residu ke vendor'),
('daily_reminder_hour', '07:00', 'WIB', 'Waktu pengiriman pengingat harian ke HSE');

-- Master Mesin
INSERT INTO `machines` (`code`, `name`, `description`, `status`) VALUES
('IRAC-01', 'Mesin Reclaimer IRAC', 'Mesin reclaimer distilasi pelarut packaging (Area Reclaimer)', 'operating');

-- Master Shift
INSERT INTO `shifts` (`code`, `name`, `start_time`, `end_time`) VALUES
('SHIFT-1', 'Shift 1 (Pagi)', '07:00:00', '15:00:00'),
('SHIFT-2', 'Shift 2 (Siang)', '15:00:00', '23:00:00'),
('SHIFT-3', 'Shift 3 (Malam)', '23:00:00', '07:00:00');

-- Master Material
INSERT INTO `materials` (`code`, `name`, `type`, `unit`) VALUES
('TINTA-X', 'Tinta X', 'ink', 'kg'),
('SOLVENT', 'Solvent Pencuci', 'solvent', 'kg');

-- Master Vendor Pengangkut B3
INSERT INTO `vendors` (`name`, `pic_name`, `contact`, `address`) VALUES
('PT Prasadha Pamunah Limbah Industri (PPLI)', 'Bpk. Hendra', '021-8674060', 'Jl. Raya Narogong Km 22, Cileungsi, Bogor'),
('PT Wastec International', 'Ibu Ratna', '021-58302111', 'Kawasan Industri Cilegon, Banten');

-- Pengguna Awal dengan NIK (Password default: 'password123' -> bcrypt hash)
-- Hash: $2y$10$w6GvI1g210/6oZ4aGgq35eN.aBq3UdfqO2oQ5n2dY7J4sL8Qf3Zce
INSERT INTO `users` (`nik`, `name`, `email`, `password`, `role`) VALUES
('ADM-5001', 'Admin Sistem', 'admin@indofood.co.id', '$2y$10$w6GvI1g210/6oZ4aGgq35eN.aBq3UdfqO2oQ5n2dY7J4sL8Qf3Zce', 'admin'),
('SPV-2001', 'Supervisor Reclaimer', 'supervisor@indofood.co.id', '$2y$10$w6GvI1g210/6oZ4aGgq35eN.aBq3UdfqO2oQ5n2dY7J4sL8Qf3Zce', 'supervisor'),
('OP-1001', 'Operator Aziz', 'aziz@indofood.co.id', '$2y$10$w6GvI1g210/6oZ4aGgq35eN.aBq3UdfqO2oQ5n2dY7J4sL8Qf3Zce', 'operator'),
('HSE-3001', 'HSE Officer', 'hse@indofood.co.id', '$2y$10$w6GvI1g210/6oZ4aGgq35eN.aBq3UdfqO2oQ5n2dY7J4sL8Qf3Zce', 'hse'),
('MGT-4001', 'Manajemen Divisi', 'manajemen@indofood.co.id', '$2y$10$w6GvI1g210/6oZ4aGgq35eN.aBq3UdfqO2oQ5n2dY7J4sL8Qf3Zce', 'management');

-- Saldo Awal Solvent di Buku Besar (1000 kg awal)
INSERT INTO `solvent_ledger` (`mutation_date`, `type`, `amount`, `previous_balance`, `current_balance`, `reason`, `user_id`) VALUES
(NOW(), 'adjustment', 1000.00, 0.00, 1000.00, 'Saldo awal sistem reclaimer', 1);
