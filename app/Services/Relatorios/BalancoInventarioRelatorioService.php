<?php

namespace App\Services\Relatorios;

use App\Models\BalancoInventario;
use App\Models\BalancoInventarioEvento;
use App\Models\BalancoInventarioItem;
use App\Models\User;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class BalancoInventarioRelatorioService
{
    public function __construct(
        protected RelatorioPdfRenderer $renderer,
    ) {}

    public function gerarPdf(BalancoInventario $balanco, ?User $usuario): Response
    {
        $balanco->loadMissing([
            'inventario.escola',
            'criadoPor',
            'iniciadoPor',
            'concluidoPor',
            'canceladoPor',
            'eventos.usuario',
            'itens.item',
            'itens.contadoPor',
        ]);

        $itensContagem = $balanco->itens
            ->where('incluido_na_contagem', true)
            ->values()
            ->map(fn (BalancoInventarioItem $item): array => $this->mapearItem($item));

        $eventos = $balanco->eventos
            ->sortBy('created_at')
            ->values()
            ->map(fn (BalancoInventarioEvento $evento): array => [
                'data' => $evento->created_at?->format('d/m/Y H:i') ?? '-',
                'tipo' => $evento->tipo?->label() ?? (string) $evento->tipo,
                'responsavel' => $evento->usuario?->name ?? 'Sistema',
                'descricao' => $evento->descricao,
            ]);

        $impactoPositivo = round((float) $itensContagem->filter(fn (array $item): bool => $item['valor_impacto'] > 0)->sum('valor_impacto'), 2);
        $impactoNegativo = round((float) $itensContagem->filter(fn (array $item): bool => $item['valor_impacto'] < 0)->sum('valor_impacto'), 2);

        $metricas = (object) [
            'itens_selecionados' => $itensContagem->count(),
            'itens_pendentes' => $itensContagem->where('status', 'Pendente')->count(),
            'divergencias' => $itensContagem->filter(fn (array $item): bool => ((float) ($item['diferenca'] ?? 0)) !== 0.0)->count(),
            'impacto_financeiro_total' => round((float) $itensContagem->sum('valor_impacto'), 2),
            'impacto_financeiro_positivo' => $impactoPositivo,
            'impacto_financeiro_negativo' => $impactoNegativo,
        ];

        return $this->renderer->download('relatorios.Inventario.balanco-inventario', [
            'balanco' => $balanco,
            'metricas' => $metricas,
            'eventos' => $eventos,
            'itensContagem' => $itensContagem,
            'reportTitle' => 'Relatório de Balanço de Inventário',
            'reportSubtitle' => 'Resumo completo das divergências e do impacto financeiro do inventário escolar',
            'reportFilters' => $this->filtrosDoBalanco($balanco),
            'usuarioExportacao' => $usuario,
            'dataExportacao' => now(),
            'orientation' => 'landscape',
        ], 'relatório-balanço-inventário-' . $this->slugBalanco($balanco) . '.pdf');
    }

    protected function filtrosDoBalanco(BalancoInventario $balanco): array
    {
        return array_filter([
            'codigo' => $balanco->codigo,
            'status' => $balanco->status?->label(),
            'escola' => $balanco->inventario?->escola?->nome,
            'inventario' => $balanco->inventario?->nome,
            'agendado para' => $balanco->data_agendada?->format('d/m/Y H:i'),
            'criado por' => $balanco->criadoPor?->name,
            'iniciado por' => $balanco->iniciadoPor?->name,
            'concluido por' => $balanco->concluidoPor?->name,
            'cancelado por' => $balanco->canceladoPor?->name,
        ], fn (mixed $value): bool => filled($value));
    }

    protected function mapearItem(BalancoInventarioItem $item): array
    {
        return [
            'tipo' => $item->item?->tipo_item?->label() ?? 'Sem tipo definido',
            'item_nome' => $item->item?->nome ?? 'Item removido',
            'unidade' => strtoupper($item->item?->unidade_medida?->value ?? '-'),
            'saldo_sistema_antes' => (float) $item->saldo_sistema_antes,
            'quantidade_contada' => $item->quantidade_contada !== null ? (float) $item->quantidade_contada : null,
            'saldo_final' => $item->saldo_final !== null ? (float) $item->saldo_final : null,
            'diferenca' => $item->diferenca !== null ? (float) $item->diferenca : null,
            'valor_unitario_referencia' => (float) ($item->valor_unitario_referencia ?? 0),
            'valor_impacto' => (float) ($item->valor_impacto ?? 0),
            'status' => $item->status_contagem_label,
            'contado_por' => $item->contadoPor?->name ?? '-',
            'contado_em' => $item->contado_em?->format('d/m/Y H:i') ?? '-',
            'observacao' => $item->observacao_contagem ?: '-',
        ];
    }

    protected function slugBalanco(BalancoInventario $balanco): string
    {
        return Str::slug($balanco->codigo ?? ('balanco-inventario-' . $balanco->getKey()));
    }
}
