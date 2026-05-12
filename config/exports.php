<?php

use App\Services\Exports\Handlers\AvaliacaoDocumentoExportHandler;
use App\Services\Exports\Handlers\EstoqueRelatorioExportHandler;
use App\Services\Exports\Handlers\InventarioRelatorioExportHandler;
use App\Services\Exports\Handlers\PedidoRelatorioGeralExportHandler;

return [
    'disk' => env('EXPORTS_DISK', 'local'),
    'queue' => env('EXPORTS_QUEUE', 'exports'),
    'expiration_days' => (int) env('EXPORTS_EXPIRATION_DAYS', 7),
    'max_active_per_user' => (int) env('EXPORTS_MAX_ACTIVE_PER_USER', 3),
    'job_timeout' => (int) env('EXPORTS_JOB_TIMEOUT', 900),
    'lock_expiration' => (int) env('EXPORTS_LOCK_EXPIRATION', 1200),

    'handlers' => [
        'avaliacao_documento' => AvaliacaoDocumentoExportHandler::class,
        'estoque_geral' => EstoqueRelatorioExportHandler::class,
        'estoque_item' => EstoqueRelatorioExportHandler::class,
        'inventario_geral' => InventarioRelatorioExportHandler::class,
        'inventario_rede' => InventarioRelatorioExportHandler::class,
        'inventario_item' => InventarioRelatorioExportHandler::class,
        'pedido_relatorio_geral' => PedidoRelatorioGeralExportHandler::class,
    ],
];
