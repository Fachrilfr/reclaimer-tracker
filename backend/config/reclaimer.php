<?php

return [

    // Rentang suhu normal mesin IRAC (FR-03). Di luar rentang ini batch tetap
    // bisa disimpan, tapi ditandai untuk ditinjau supervisor.
    'temperature' => [
        'oil' => ['min' => 100, 'max' => 160],
        'vapour' => ['min' => 40, 'max' => 60],
    ],

    // Penerima pemberitahuan di aplikasi saat mesin mulai dan selesai maintenance (FR-21)
    'maintenance_notify_roles' => ['hse', 'supervisor', 'management', 'admin'],

    // Penerima pengingat masa simpan limbah B3 (PRD §5.1 catatan notifikasi)
    'b3_reminder_roles' => ['hse'],

];
