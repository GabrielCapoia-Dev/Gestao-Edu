<?php

return [
    'calendar' => [
        'default_days' => 5,
        'max_days' => 31,
        'period_options' => [5, 10, 15, 20, 25, 30],
        'max_events' => 500,
        'timezone' => env('APP_TIMEZONE', 'America/Sao_Paulo'),
        'sources' => [
            'manual' => true,
            'avaliacoes' => true,
            'pedidos_manutencao' => true,
            'reservas_veiculos' => true,
        ],
    ],

    'imports' => [
        'disk' => 'local',
        'directory' => 'imports/eventos-calendario',
        'max_file_size_kb' => 5_120,
        'max_rows' => 1_000,
    ],
];
