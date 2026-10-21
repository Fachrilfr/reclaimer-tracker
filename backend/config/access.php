<?php

/*
| Matriks hak akses dari PRD §5.1. Role yang tidak tercantum di sebuah
| fitur berarti tanpa akses.
|
| Tingkat: kelola (tambah, ubah, atur), isi (input data), tinjau (memeriksa
| dan menindaklanjuti), lihat (hanya baca). Kelola mencakup isi, tinjau,
| dan lihat. Isi dan tinjau masing-masing mencakup lihat.
|
| Ubah PRD dulu, baru file ini. Frontend membaca hasilnya lewat
| /api/auth/me, jadi menu ikut berubah tanpa perlu diubah terpisah.
*/

return [

    'features' => [
        'dashboard' => ['operator' => 'lihat', 'supervisor' => 'tinjau', 'hse' => 'lihat', 'management' => 'lihat'],
        'batch' => ['operator' => 'isi', 'supervisor' => 'lihat', 'hse' => 'lihat'],
        // Pemakaian solvent dari saldo hasil recovery, dicatat terpisah dari batch.
        // Belum ada di matriks PRD §5.1: operator mencatat, supervisor memantau.
        'solvent' => ['operator' => 'isi', 'supervisor' => 'lihat'],
        // Status maintenance mesin (FR-20). Belum ada di matriks PRD §5.1: operator
        // melapor, supervisor mengelola, penerima pemberitahuan bisa melihat.
        'mesin' => ['operator' => 'isi', 'supervisor' => 'kelola', 'hse' => 'lihat', 'management' => 'lihat', 'admin' => 'lihat'],
        'limbah_b3' => ['supervisor' => 'lihat', 'hse' => 'kelola', 'management' => 'lihat'],
        'drum_b3' => ['operator' => 'isi', 'supervisor' => 'lihat', 'hse' => 'kelola'],
        'pengingat' => ['supervisor' => 'lihat', 'hse' => 'kelola'],
        'surat_jalan' => ['supervisor' => 'lihat', 'hse' => 'kelola', 'management' => 'lihat'],
        'arsip' => ['supervisor' => 'lihat', 'hse' => 'kelola', 'management' => 'lihat'],
        'laporan' => ['supervisor' => 'lihat', 'hse' => 'lihat', 'management' => 'lihat', 'admin' => 'lihat'],
        'audit' => ['supervisor' => 'lihat', 'hse' => 'lihat', 'management' => 'lihat', 'admin' => 'lihat'],
        'parameter' => ['supervisor' => 'kelola', 'hse' => 'kelola'],
        'data_master' => ['supervisor' => 'lihat', 'hse' => 'kelola', 'admin' => 'kelola'],

        // Catatan PRD §5.1: bila role Admin dipakai, pengelolaan pengguna
        // pindah ke Admin, dan HSE cukup mengelola master limbah B3 dan vendor.
        'pengguna' => ['admin' => 'kelola'],
    ],

    'levels' => [
        'kelola' => ['kelola', 'isi', 'tinjau', 'lihat'],
        'isi' => ['isi', 'lihat'],
        'tinjau' => ['tinjau', 'lihat'],
        'lihat' => ['lihat'],
    ],

    // Halaman pertama setelah login, sesuai tugas utama tiap role (PRD §5).
    'home' => [
        'operator' => 'batch',
        'supervisor' => 'dashboard',
        'hse' => 'limbah_b3',
        'management' => 'dashboard',
        'admin' => 'pengguna',
    ],

];
