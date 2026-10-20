<?php

return [

    // Rentang suhu normal mesin IRAC (FR-03). Di luar rentang ini batch tetap
    // bisa disimpan, tapi ditandai untuk ditinjau supervisor.
    'temperature' => [
        'oil' => ['min' => 100, 'max' => 160],
        'vapour' => ['min' => 40, 'max' => 60],
    ],

];
