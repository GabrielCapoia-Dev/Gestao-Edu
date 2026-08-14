<?php

use App\Services\Exports\Handlers\AvaliacaoDocumentoExportHandler;
use App\Services\Exports\Handlers\EscolaXlsxExportHandler;
use App\Services\Exports\Handlers\EstoqueRelatorioExportHandler;
use App\Services\Exports\Handlers\FeedbackPedidoExportHandler;
use App\Services\Exports\Handlers\InventarioRelatorioExportHandler;
use App\Services\Exports\Handlers\LotacaoXlsxExportHandler;
use App\Services\Exports\Handlers\PedidoRelatorioGeralExportHandler;
use App\Services\Exports\Handlers\PedidoRelatorioSimplificadoExportHandler;
use App\Services\Exports\Handlers\SelectedRecordsXlsxExportHandler;

return [
    'disk' => env('EXPORTS_DISK', 'local'),
    'queue' => env('EXPORTS_QUEUE', 'exports'),
    'expiration_days' => (int) env('EXPORTS_EXPIRATION_DAYS', 7),
    'max_active_per_user' => (int) env('EXPORTS_MAX_ACTIVE_PER_USER', 3),
    'job_timeout' => (int) env('EXPORTS_JOB_TIMEOUT', 900),
    'xlsx_memory_limit' => env('EXPORTS_XLSX_MEMORY_LIMIT', '512M'),
    'lock_expiration' => (int) env('EXPORTS_LOCK_EXPIRATION', 1200),
    'stalled_queued_after_minutes' => (int) env('EXPORTS_STALLED_QUEUED_AFTER_MINUTES', 30),
    'stalled_running_after_minutes' => (int) env('EXPORTS_STALLED_RUNNING_AFTER_MINUTES', 45),
    'stalled_max_recovery_attempts' => (int) env('EXPORTS_STALLED_MAX_RECOVERY_ATTEMPTS', 3),

    'handlers' => [
        'avaliacao_documento' => AvaliacaoDocumentoExportHandler::class,
        'estoque_geral' => EstoqueRelatorioExportHandler::class,
        'estoque_item' => EstoqueRelatorioExportHandler::class,
        'inventario_geral' => InventarioRelatorioExportHandler::class,
        'inventario_rede' => InventarioRelatorioExportHandler::class,
        'inventario_item' => InventarioRelatorioExportHandler::class,
        'pedido_relatorio_geral' => PedidoRelatorioGeralExportHandler::class,
        'pedido_relatorio_simplificado' => PedidoRelatorioSimplificadoExportHandler::class,
        'feedback_pedido_relatorio' => FeedbackPedidoExportHandler::class,
        'escolas_xlsx' => EscolaXlsxExportHandler::class,
        'lotacoes_xlsx' => LotacaoXlsxExportHandler::class,
        'turmas_selecionadas' => SelectedRecordsXlsxExportHandler::class,
        'turmas_selecionadas_detalhado' => SelectedRecordsXlsxExportHandler::class,
        'servidores_selecionados' => SelectedRecordsXlsxExportHandler::class,
        'alunos_selecionados' => SelectedRecordsXlsxExportHandler::class,
    ],
];
