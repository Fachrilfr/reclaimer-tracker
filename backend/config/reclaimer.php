<?php

return [

    // Rentang suhu normal mesin IRAC (FR-03). Di luar rentang ini batch tetap
    // bisa disimpan, tapi ditandai untuk ditinjau supervisor.
    'temperature' => [
        'oil' => ['min' => 100, 'max' => 160],
        'vapour' => ['min' => 40, 'max' => 60],
    ],

    // Batch lanjutan (FR-05) hanya bisa disambungkan ke batch awal yang
    // tanggalnya paling lama sekian hari sebelum tanggal batch lanjutan.
    'continuation_max_days' => 3,

];
