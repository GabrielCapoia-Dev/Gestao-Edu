<?php

namespace App\Services\Relatorios;

use App\Models\BalancoEstoque;
use App\Models\BalancoEstoqueEvento;
use App\Models\BalancoEstoqueItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class BalancoEstoqueRelatorioService
{
    public function __construct(
        protected RelatorioPdfRenderer $renderer,
    ) {}

    public function gerarPdf(BalancoEstoque $balanco, ?User $usuario): Response
    {
        $balanco->loadMissing([
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
            ->map(fn (BalancoEstoqueItem $item): array => $this->mapearItem($item));

        $itensFora = $balanco->itens
            ->where('incluido_na_contagem', false)
            ->values()
            ->map(fn (BalancoEstoqueItem $item): array => $this->mapearItemFora($item));

        $eventos = $balanco->eventos
            ->sortBy('created_at')
            ->values()
            ->map(fn (BalancoEstoqueEvento $evento): array => [
                'data' => $evento->created_at?->format('d/m/Y H:i') ?? '-',
                'tipo' => $evento->tipo?->label() ?? (string) $evento->tipo,
                'responsavel' => $evento->usuario?->name ?? 'Sistema',
                'descricao' => $evento->descricao,
            ]);

        $metricas = (object) [
            'itens_selecionados' => $itensContagem->count(),
            'itens_fora' => $itensFora->count(),
            'itens_pendentes' => $itensContagem->where('status', 'Pendente')->count(),
            'divergencias' => $itensContagem->filter(fn (array $item): bool => ((float) ($item['diferenca'] ?? 0)) !== 0.0)->count(),
        ];

        return $this->renderer->download('relatorios.Estoque.balanco-estoque', [
            'balanco' => $balanco,
            'metricas' => $metricas,
            'eventos' => $eventos,
            'itensContagem' => $itensContagem,
            'itensFora' => $itensFora,
            'reportTitle' => 'Relatorio de Balanco de Estoque',
            'reportSubtitle' => 'Resumo completo de contagens, divergencias e historico do balanco',
            'reportFilters' => $this->filtrosDoBalanco($balanco),
            'usuarioExportacao' => $usuario,
            'dataExportacao' => now(),
            'orientation' => 'landscape',
        ], 'relatorio-balanco-estoque-' . $this->slugBalanco($balanco) . '.pdf');
    }

    protected function filtrosDoBalanco(BalancoEstoque $balanco): array
    {
        return array_filter([
            'codigo' => $balanco->codigo,
            'status' => $balanco->status?->label(),
            'agendado para' => $balanco->data_agendada?->format('d/m/Y H:i'),
            'criado por' => $balanco->criadoPor?->name,
            'iniciado por' => $balanco->iniciadoPor?->name,
            'concluido por' => $balanco->concluidoPor?->name,
            'cancelado por' => $balanco->canceladoPor?->name,
        ], fn (mixed $value): bool => filled($value));
    }

    protected function mapearItem(BalancoEstoqueItem $item): array
    {
        return [
            'tipo' => $item->item?->tipo_item?->label() ?? 'Sem tipo definido',
            'item_nome' => $item->item?->nome ?? 'Item removido',
            'unidade' => strtoupper($item->item?->unidade_medida?->value ?? '-'),
            'saldo_sistema_antes' => (float) $item->saldo_sistema_antes,
            'quantidade_contada' => $item->quantidade_contada !== null ? (float) $item->quantidade_contada : null,
            'saldo_final' => $item->saldo_final !== null ? (float) $item->saldo_final : null,
            'diferenca' => $item->diferenca !== null ? (float) $item->diferenca : null,
            'status' => $item->status_contagem_label,
            'contado_por' => $item->contadoPor?->name ?? '-',
            'contado_em' => $item->contado_em?->format('d/m/Y H:i') ?? '-',
            'observacao' => $item->observacao_contagem ?: '-',
        ];
    }

    protected function mapearItemFora(BalancoEstoqueItem $item): array
    {
        return [
            'tipo' => $item->item?->tipo_item?->label() ?? 'Sem tipo definido',
            'item_nome' => $item->item?->nome ?? 'Item removido',
            'unidade' => strtoupper($item->item?->unidade_medida?->value ?? '-'),
            'saldo_sistema_antes' => (float) $item->saldo_sistema_antes,
        ];
    }

    protected function slugBalanco(BalancoEstoque $balanco): string
    {
        return Str::slug($balanco->codigo ?? ('balanco-' . $balanco->getKey()));
    }
}
