# PRD: Material Balance & B3 Tracker

Product Requirements Document, draf 19 Oktober 2026. Sumber: prototipe HTML, catatan diskusi lapangan dengan HSE, dan rancangan database.

## 1. Ringkasan

Material Balance & B3 Tracker adalah aplikasi web untuk mencatat proses reclaimer tinta dan solvent, menghitung material balance secara otomatis, serta mengawasi masa simpan limbah B3 sampai diserahkan ke vendor. Aplikasi menggantikan pencatatan manual (template Laporan Proses Reclaimer Mesin IRAC) dengan form digital, dashboard, pengingat bertingkat ke HSE, dan arsip surat jalan yang bisa dicari. Perusahaan meminta aplikasi dibangun dengan Laravel.

## 2. Latar belakang dan masalah

| Masalah di lapangan | Dampak |
| --- | --- |
| Batch reclaimer dicatat manual per shift | Rekap lambat, angka recovery sulit dibandingkan antar shift |
| Residu tidak ditimbang per proses | Selisih bahan hanya bisa diperkirakan dan tidak terdokumentasi rapi |
| Masa simpan limbah B3 dipantau manual | Risiko pallet melewati batas simpan tanpa disadari |
| Surat jalan, nomor manifest, dan dokumen penutup tersebar | Sulit dicari saat audit ISO 14001 |
| Mesin masuk maintenance tanpa kabar ke HSE | Proses dan penyerahan residu terhenti tanpa diketahui |

Ide awal ada dua, yaitu Digital Material Balance & Reclaimer Tracking serta Digital B3 Waste Manifest & Compliance Tracker. Pihak lapangan merekomendasikan keduanya digabung menjadi satu aplikasi.

## 3. Tujuan dan indikator keberhasilan

Angka target di bawah adalah usulan dan perlu disepakati bersama supervisor dan HSE.

| Tujuan | Indikator | Usulan target |
| --- | --- | --- |
| Pencatatan batch digital penuh | Persentase batch yang tercatat di aplikasi | 100% |
| Input cepat | Waktu operator mengisi satu batch | Kurang dari 1 menit |
| Tidak ada limbah lewat batas simpan | Jumlah pallet melewati batas simpan | 0 |
| Visibilitas recovery | Alert recovery di bawah target sampai ke supervisor | Hari yang sama |
| Audit siap | Waktu menelusuri satu pallet dari batch sampai surat jalan | Beberapa menit, tanpa rekap manual |
| Laporan otomatis | Laporan harian sampai tahunan tanpa susun manual | Ekspor Excel dan PDF |

## 4. Ruang lingkup

**Termasuk:**

- Form input batch reclaimer dan perhitungan material balance otomatis.
- Saldo solvent di penyimpanan.
- Pencatatan pallet limbah B3 (1 pallet = 3 drum), timer masa simpan, dan pengingat bertingkat.
- Surat jalan limbah, arsip, nomor manifest, dan dokumen penutup.
- Status maintenance mesin reclaimer.
- Dashboard, laporan, jejak audit, data master, parameter, serta pengguna dan hak akses.

**Tidak termasuk (versi pertama):**

- Nominal uang, seperti penghematan Rupiah dan harga solvent. Disiapkan untuk tampilan Manajemen di fase berikutnya.
- Integrasi otomatis dengan alat timbang.
- Pembuatan nomor manifest resmi. Nomor ini terbit dari pengelola dan hanya dicatat.
- Lebih dari satu bahan baku, satu jenis limbah B3, dan satu mesin reclaimer.

## 5. Pengguna dan role

| Role | Tugas utama |
| --- | --- |
| Operator | Mengisi form batch, mencatat pallet penuh |
| Supervisor | Meninjau alert recovery, mengubah target recovery |
| HSE | Mengelola limbah B3, pengingat, surat jalan, arsip manifest, dan dokumen penutup |
| Manajemen | Melihat dashboard, laporan, dan jejak audit |
| Admin | Mengelola pengguna dan data master (usulan, menyusul matriks final) |

Matriks hak akses memakai lima tingkat: **Kelola** (tambah, ubah, atur), **Isi** (input data), **Tinjau** (memeriksa dan menindaklanjuti), **Lihat** (hanya baca), dan tanda strip untuk tanpa akses.

### 5.1 Menu dan hak akses per role

| Menu | Operator | Supervisor | HSE | Manajemen | Admin |
| --- | --- | --- | --- | --- | --- |
| Dashboard dan alert recovery | Lihat | Tinjau | Lihat | Lihat | - |
| Form input batch | Isi | Lihat | Lihat | - | - |
| Limbah B3 dan timer simpan | - | Lihat | Kelola | Lihat | - |
| Input drum B3 (pallet penuh) | Isi | Lihat | Kelola | - | - |
| Pengingat pengangkutan | - | Lihat | Kelola | - | - |
| Surat jalan limbah | - | Lihat | Kelola | Lihat | - |
| Arsip manifest dan dokumen penutup | - | Lihat | Kelola | Lihat | - |
| Laporan dan ekspor | - | Lihat | Lihat | Lihat | Lihat |
| Jejak audit | - | Lihat | Lihat | Lihat | Lihat |
| Parameter | - | Kelola | Kelola | - | - |
| Data master dan pengguna | - | Lihat | Kelola | - | Kelola |

Catatan untuk tabel di atas:

- Matriks mengikuti prototipe. Role Admin adalah usulan baru.
- Di prototipe, HSE memegang Kelola untuk data master dan pengguna. Bila Admin dipakai, pengelolaan pengguna sebaiknya pindah ke Admin dan HSE cukup mengelola master limbah B3 dan vendor.
- Notifikasi: HSE menerima pengingat masa simpan dan status maintenance mesin, Supervisor menerima alert recovery di bawah target.
- Halaman Kelengkapan fitur hanya ada di prototipe untuk peninjauan dan tidak masuk menu sistem final.

## 6. Alur kerja utama

1. **Operator mencatat batch.** Isi tanggal, shift, regu, operator, berat Tinta X yang masuk ke mesin, solvent dipakai, hasil recovery, suhu oli, suhu uap, dan keterangan. Sistem langsung menampilkan selisih bahan, recovery rate, residu rate, dan saldo akhir solvent.
2. **Residu tertampung otomatis.** Sisa Tinta X yang tidak menjadi solvent masuk drum. Tiga drum penuh dicatat sebagai satu pallet, tanpa input berat per proses.
3. **HSE memantau masa simpan.** Tiap pallet punya hitung mundur. Pengingat naik tingkat dan dikirim ke HSE setiap hari pukul 07.00.
4. **HSE mengonfirmasi pengangkutan,** lalu mencatat surat jalan: memilih pallet, vendor, tanggal, nomor surat jalan, dan mengunggah bukti. Status menjadi menunggu dokumen penutup.
5. **HSE melengkapi arsip.** Nomor manifest dan dokumen penutup diisi belakangan. Setelah dokumen penutup terunggah, status menjadi ditutup.
6. **Supervisor dan Manajemen** membaca dashboard dan laporan, dan auditor menelusuri jejak lewat halaman Jejak audit.

## 7. Kebutuhan fungsional

Prioritas: P0 wajib untuk rilis pertama, P1 penting, P2 bisa menyusul.

### 7.1 Input batch reclaimer

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| FR-01 | Form batch dengan nomor batch otomatis, tanggal, shift, regu (opsional), operator, dan bahan Tinta X | P0 |
| FR-02 | Input berat Tinta X yang masuk ke mesin, solvent dipakai, dan hasil recovery dalam kg | P0 |
| FR-03 | Input Oil Temperature dan Vapour Temperature. Di luar rentang normal (oli 100 sampai 160 °C, uap 40 sampai 60 °C) tampil peringatan, data tetap bisa disimpan dan ditandai untuk ditinjau | P0 |
| FR-04 | Hasil perhitungan tampil langsung: selisih bahan, recovery rate, residu rate (estimasi), dan saldo akhir solvent | P0 |
| FR-05 | Opsi batch lanjutan dari shift sebelumnya (tanpa Tinta baru). Recovery rate dihitung gabungan dengan batch awal | P0 |
| FR-06 | Validasi: recovery tidak melebihi bahan masuk, solvent dipakai tidak melebihi saldo, operator dan suhu wajib diisi | P0 |
| FR-07 | Keterangan bebas dengan tombol isi cepat (Start Pkl, Stop Pkl, Lanjutan, Kuras residu) | P1 |
| FR-08 | Sumber berat: input manual.

### 7.2 Saldo solvent

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| FR-10 | Saldo berkurang saat solvent dipakai dan bertambah dari hasil recovery, dicatat sebagai buku besar mutasi | P0 |
| FR-11 | Saldo akhir tampil per batch, hari, minggu, bulan, dan periode laporan | P0 |
| FR-12 | Penyesuaian saldo manual dengan alasan wajib | P1 |

### 7.3 Status maintenance mesin

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| FR-20 | Aktifkan dan akhiri status maintenance mesin dengan alasan | P1 |
| FR-21 | Saat maintenance aktif, notifikasi wajib terkirim ke HSE agar tahu proses dan penyerahan residu terhenti | P1 |
| FR-22 | Form batch menampilkan peringatan saat mesin berstatus maintenance | P1 |

### 7.4 Limbah B3, pallet, dan pengingat

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| FR-25 | Catat pallet dengan tanggal mulai terisi dan tanggal penuh (1 pallet = 3 drum) | P0 |
| FR-26 | Hitung batas simpan dan sisa masa simpan eksak dalam hari per pallet, tampil di dashboard dan daftar B3 | P0 |
| FR-27 | Pengingat bertingkat: awal (sisa 30 hari), mendesak (14), kritis (7). Level kritis dikirim ulang tiap hari sampai pengangkutan dikonfirmasi | P0 |
| FR-28 | Notifikasi memakai sisa hari yang eksak, bukan deskripsi seperti sepertiga batas simpan | P0 |
| FR-29 | Tombol Konfirmasi pengangkutan yang menghentikan pengingat harian dan mencatat vendor serta tanggal terjadwal | P0 |
| FR-30 | Log notifikasi yang bisa dilihat HSE | P0 |
| FR-31 | Penyerahan residu ke vendor dibatasi maksimal 3 minggu (hasil diskusi HSE), titik awal hitungan perlu dikonfirmasi | P1 |
| FR-32 | Pengiriman notifikasi sungguhan ke HSE. Kanal (email atau pesan) belum ditentukan | P1 |

### 7.5 Surat jalan dan arsip manifest

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| FR-35 | Surat jalan limbah: pilih pallet, vendor, tanggal serah terima, nomor surat jalan, berat drum (opsional), petugas vendor (opsional) | P0 |
| FR-36 | Syarat minimal jumlah pallet per pengangkutan (usulan 1 pallet). Surat jalan ditolak bila belum terpenuhi | P0 |
| FR-37 | Bukti surat jalan (PDF, JPG, PNG) wajib diunggah sebelum simpan | P0 |
| FR-38 | Status surat jalan: menunggu dokumen penutup, lalu ditutup setelah dokumen penutup terunggah | P0 |
| FR-39 | Nomor manifest opsional dan boleh menyusul, karena baru terbit setelah pengelola menyetujui | P0 |
| FR-40 | Arsip dengan pencarian per nomor surat jalan, nomor manifest, pallet, nama berkas, vendor, status, dan bulan | P0 |

Dokumen manifest dihapus dari tahap serah terima dan diganti surat jalan karena nomor manifest belum ada saat limbah diserahkan. Penggantian ini masih menunggu persetujuan.

### 7.6 Dashboard

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| FR-45 | Bagian Perlu ditindak: pallet mendesak dan kritis, serta batch recovery di bawah target dalam 14 hari terakhir | P0 |
| FR-46 | Empat angka utama: bahan masuk, residu (estimasi), recovery rate, saldo solvent | P0 |
| FR-47 | Pilih periode harian (geser atau kalender), bulanan, dan tahunan | P0 |
| FR-48 | Grafik tren recovery dan residu dengan garis target, dan perbandingan dengan tahun sebelumnya | P1 |
| FR-49 | Perbandingan per shift dan tabel batch atau per minggu atau per bulan sesuai periode | P0 |
| FR-50 | Daftar pallet B3 aktif dengan meter masa simpan dan filter Perlu tindakan | P0 |

### 7.7 Laporan, audit, dan pengaturan

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| FR-55 | Laporan harian, mingguan, bulanan, dan tahunan: ringkasan, per shift, rincian per batch atau hari atau minggu atau bulan, solvent, dan limbah B3 | P0 |
| FR-56 | Ekspor Excel dan PDF di semua periode laporan | P0 |
| FR-57 | Jejak audit dari batch reclaimer sampai surat jalan dan dokumen penutup (ISO 14001) | P0 |
| FR-58 | Data master: bahan, jenis limbah B3, shift, vendor (dikelola langsung di aplikasi) | P0 |
| FR-59 | Parameter: target recovery, minimal pallet per pengangkutan, drum per pallet, batas masa simpan, jenjang pengingat, dan penerima pengingat | P0 |
| FR-60 | Pengguna, role, dan hak akses per fitur | P0 |
| FR-61 | Impor baseline catatan manual (CSV atau Excel) untuk uji coba | P2 |

## 8. Aturan bisnis dan parameter awal

| Parameter | Nilai awal | Status |
| --- | --- | --- |
| Target recovery | 35% | Usulan, dikonfirmasi supervisor dan HSE |
| Batas masa simpan limbah B3 | 90 hari, dihitung sejak pallet mulai terisi | Perlu konfirmasi HSE |
| Jenjang pengingat | Sisa 30, 14, 7 hari | Usulan |
| Minimal pallet per pengangkutan | 1 pallet | Usulan |
| Isi satu pallet | 3 drum | Hasil diskusi lapangan |
| Batas penyerahan ke vendor | Maksimal 3 minggu | Hasil diskusi HSE, titik awal perlu dikonfirmasi |
| Jam pengingat harian | 07.00 | Usulan |

Rumus: input total = Tinta X + solvent dipakai. Residu (estimasi) = input total dikurangi recovery. Recovery rate = recovery dibagi input total. Untuk periode apa pun, recovery rate dihitung dari jumlah total, bukan rata-rata persentase batch.

## 9. Kebutuhan non fungsional

- **Teknologi:** Laravel, dengan database relasional (MySQL atau MariaDB). Rancangan tabel ada di dokumen Rancangan Database.
- **Kinerja:** halaman dashboard dan laporan terbuka dalam hitungan detik untuk data beberapa tahun dari satu mesin.
- **Keamanan:** login per pengguna, hak akses per fitur, data transaksi tidak dihapus permanen (soft delete), dan setiap perubahan dicatat di jejak audit.
- **Integritas data:** penyimpanan batch dan mutasi saldo solvent dalam satu transaksi, dengan penguncian saldo agar dua operator tidak saling menimpa.
- **Tampilan:** responsif di desktop dan layar kecil, mendukung mode terang dan gelap, bahasa antarmuka Indonesia.
- **Dokumen:** unggahan bukti dan dokumen penutup dibatasi PDF, JPG, dan PNG.
- **Ketersediaan data:** laporan dapat diekspor untuk keperluan audit kapan saja.

## 10. Prototipe

Prototipe HTML interaktif sudah dibuat dengan data contoh (12 Oktober 2026) dan memuat halaman: Dashboard, Form input batch, Pengingat pengangkutan, Input drum B3, Surat jalan limbah, Arsip manifest, Laporan, Jejak audit, Data master, Parameter, serta Pengguna dan hak akses. Fungsi yang masih berupa tampilan contoh: pengiriman notifikasi sungguhan, ekspor Excel dan PDF, dan impor baseline.

## 11. Rencana rilis

| Fase | Isi | Hasil |
| --- | --- | --- |
| 1 | Akses dan pengguna, data master, parameter, form batch, saldo solvent, dashboard dasar | Operator bisa mencatat batch digital |
| 2 | Pallet B3, timer, pengingat, surat jalan, arsip manifest | HSE bisa mengawasi masa simpan sampai penyerahan |
| 3 | Laporan lengkap dengan ekspor, jejak audit | Siap untuk audit ISO 14001 |
| 4 | Status maintenance mesin, notifikasi sungguhan, impor baseline | Pengawasan lapangan lebih lengkap |

Urutan fase adalah usulan dan bisa disesuaikan dengan prioritas HSE dan supervisor.

## 12. Risiko dan pertanyaan terbuka

| Hal | Keterangan |
| --- | --- |
| Awal hitungan masa simpan | Dari pallet mulai terisi atau dari pallet penuh. Perlu keputusan HSE, termasuk langkah bila batas habis sebelum pallet penuh |
| Batas 3 minggu ke vendor | Apakah dihitung sejak pallet penuh dan apakah menggantikan atau melengkapi batas 90 hari |
| Toleransi selisih bahan | Tidak dipakai karena residu tidak ditimbang per batch. Perlu diputuskan apakah ada pengganti |
| Kapasitas dan toleransi mesin | Menunggu spesifikasi dari penanggung jawab mesin agar mesin tidak overwork |
| Alat timbang | Saat ini input manual. Opsi terhubung otomatis belum dikonfirmasi |
| Kanal notifikasi | Email atau pesan belum ditentukan |
| Surat jalan pengganti manifest | Menunggu persetujuan |
| Kode limbah B3 resmi | Diisi HSE, belum tersedia |
| Tanggal batch Shift 3 | Perlu disepakati apakah memakai tanggal mulai shift |
| Role final | Matriks hak akses dan penanggung jawab tiap tahap masih usulan |
| Risiko adopsi | Operator terbiasa mencatat manual. Form harus tetap singkat dan jelas agar tidak ditinggalkan |
| Kualitas data awal | Tanpa data baseline, perbandingan dengan periode sebelumnya belum bisa dilakukan |

## 13. Kriteria penerimaan rilis pertama

1. Operator menyimpan batch lengkap dengan suhu dan hasil recovery, dan angka selisih, recovery rate, serta saldo solvent tampil benar.
2. Batch ditolak bila recovery melebihi bahan masuk atau solvent melebihi saldo.
3. Pallet baru muncul di daftar B3 dengan sisa hari yang eksak dan level pengingat yang benar.
4. HSE menerima pengingat tiap pukul 07.00 sesuai level, dan pengingat kritis berhenti setelah pengangkutan dikonfirmasi.
5. Surat jalan tidak bisa disimpan tanpa pallet cukup dan bukti terunggah, dan statusnya berubah ke ditutup setelah dokumen penutup diunggah.
6. Laporan harian sampai tahunan bisa diekspor ke Excel dan PDF dengan angka yang sama dengan dashboard.
7. Setiap pengguna hanya melihat dan mengubah fitur sesuai role-nya.
