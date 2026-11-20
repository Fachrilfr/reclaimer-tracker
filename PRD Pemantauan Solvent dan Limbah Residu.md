# Pemantauan Solvent dan Limbah Residu

Dokumen kebutuhan produk · 20 November 2026

## 1. Tujuan dan pengguna

Aplikasi mencatat pengolahan Tinta X pada mesin pemulih pelarut (reclaimer), menghitung hasil pelarut dan perkiraan residu, serta menelusuri palet Limbah Residu sampai diserahkan kepada vendor. Operator mencatat kegiatan produksi. Supervisor meninjau hasil proses. Tim HSE memantau penyimpanan dan pengangkutan residu. Manajemen menggunakan dasbor dan laporan. Super Admin mengelola akun dan hak akses. Menu dan tindakan yang tersedia bagi setiap pengguna mengikuti pengaturan akses per fitur.

## 2. Pencatatan produksi

| Menu pada aplikasi | Kebutuhan yang dipenuhi |
|---|---|
| Dashboard | Menampilkan ringkasan produksi, perbandingan antarshift, serta rangkaian input yang perlu ditinjau. Pengguna dengan akses tinjau dapat mencatat hasil peninjauan. |
| Input reclaimer | Mencatat input baru atau lanjutan, shift, hasil pelarut, suhu oli, suhu uap, dan keterangan. Berat Tinta X untuk input baru ditetapkan 335 kg dalam konfigurasi. Input lanjutan meneruskan pengolahan bahan yang telah dicatat. |
| Pemakaian solvent | Mencatat pengambilan pelarut dari persediaan dan mengurangi saldo. Hasil pelarut dari mesin menambah saldo. |
| Status mesin | Mencatat awal dan akhir perawatan serta riwayatnya. Perubahan status menghasilkan pemberitahuan dalam aplikasi bagi HSE, Supervisor, Manajemen, dan Super Admin. |

### Perhitungan neraca bahan

- Perkiraan residu per input (kg) = berat Tinta X yang masuk pada input tersebut − berat pelarut yang dihasilkan. Nilai per input lanjutan dapat negatif karena bahan awal telah dicatat pada shift sebelumnya. Perkiraan residu untuk satu proses dibaca dari total rangkaian.
- Persentase hasil pelarut = total hasil pelarut ÷ total Tinta X × 100%. Persentase residu = (total Tinta X − total hasil pelarut) ÷ total Tinta X × 100%. Sistem memakai total rangkaian untuk input lanjutan dan total periode untuk laporan. Jika pembagi nol, persentase bernilai nol.
- Total hasil pelarut dalam satu rangkaian maksimal sebesar total bahan masuk. Perhitungan neraca mesin terpisah dari transaksi pengambilan pelarut. Saldo persediaan berasal dari saldo awal, hasil mesin, pemakaian, dan penyesuaian tercatat.
- Rentang suhu acuan ialah 100–160 °C untuk oli dan 40–60 °C untuk uap. Sistem menandai pembacaan di luar rentang untuk ditinjau dan tetap mengizinkan penyimpanan input.

## 3. Pengelolaan Limbah Residu

1. **Input pallet residu.** Operator mencatat saat palet mulai diisi. Sistem menyimpan batas masa simpan yang berlaku pada saat palet dibuat.
2. **Penerimaan di tempat penyimpanan sementara (TPS).** Ketika operator menandai palet penuh, sistem langsung mencatat palet masuk TPS. Tanggal kedaluwarsa dihitung dari tanggal palet ditandai penuh ditambah batas masa simpan palet tersebut.
3. **Pemantauan.** Menu Residu menampilkan inventaris palet, sisa masa simpan, dan riwayat pengingat. Dashboard HSE merangkum residu masuk TPS, residu yang diangkut vendor, dan palet di TPS. Penjadwal memeriksa pengingat HSE setiap hari pukul 07.00 WIB apabila layanan penjadwal berjalan.
4. **Surat jalan.** Pengguna memilih palet dari TPS, mencatat vendor, tanggal, nomor surat jalan dan berat, serta menyertakan bukti pengangkutan. Jumlah minimal palet per surat jalan mengikuti parameter sistem.
5. **Arsip surat jalan.** Surat jalan menjadi dokumen penutup pengangkutan. Pengguna dapat mencari riwayat pengiriman dan mengunduh bukti dari arsip.

## 4. Laporan

| Menu | Isi laporan |
|---|---|
| Laporan reclaimer | Neraca bahan, saldo pelarut, dan catatan operasional selama periode yang dipilih. |
| Laporan residu | Berat residu yang diangkut vendor, surat jalan, daftar palet yang diangkut, dan persediaan palet. |

Kedua laporan menyediakan tampilan dokumen serta keluaran Excel dan PDF.

## 5. Pengaturan dan koreksi

| Menu | Fungsi |
|---|---|
| Koreksi data | Memperbaiki atau menghapus input produksi, pemakaian pelarut, palet, surat jalan, dan catatan perawatan sesuai aturan transaksi. Alasan wajib dicatat. Riwayat koreksi menyimpan nilai sebelumnya. Koreksi yang memengaruhi saldo pelarut menghasilkan penyesuaian saldo. |
| Parameter | Mengatur toleransi hasil pelarut, batas masa simpan di TPS, jenjang pengingat, dan jumlah minimal palet per surat jalan. |
| Data master | Mengelola saldo awal pelarut, shift, dan vendor. |
| Pengguna | Mengelola akun, peran, status aktif, pengaturan ulang kata sandi, dan akses per fitur. |

Pengguna masuk menggunakan NIK dan kata sandi, dapat mengganti kata sandi sendiri, serta dapat membaca atau menandai pemberitahuan melalui lonceng aplikasi.
