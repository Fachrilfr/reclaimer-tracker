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

];
