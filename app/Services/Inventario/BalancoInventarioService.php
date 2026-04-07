<?php

namespace App\Services\Inventario;

use App\Models\BalancoInventario;
use App\Models\BalancoInventarioEvento;
use App\Models\BalancoInventarioItem;
use App\Models\ContratoItem;
use App\Models\Enums\BalancoInventarioEventoTipo;
use App\Models\Enums\BalancoInventarioStatus;
use App\Models\Inventario;
use App\Models\InventarioEstoque;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BalancoInventarioService
{
    public function __construct(
        protected BalancoInventarioBloqueioService $bloqueioService,
    ) {}

    public function agendar(Inventario $inventario, array $data, User $user): BalancoInventario
    {
        return DB::transaction(function () use ($inventario, $data, $user) {
            $balanco = BalancoInventario::query()->create([
                'inventario_id' => $inventario->getKey(),
                'status' => BalancoInventarioStatus::Agendado,
                'data_agendada' => $data['data_agendada'],
                'observacao_inicial' => $this->normalizarTexto($data['observacao_inicial'] ?? null),
                'criado_por_id' => $user->getKey(),
            ]);

            $this->registrarEvento(
                $balanco,
                BalancoInventarioEventoTipo::Criado,
                "Balanço agendado para {$balanco->data_agendada?->format('d/m/Y H:i')}.",
                $user,
            );

            return $balanco->fresh(['inventario.escola', 'criadoPor']);
        });
    }

    public function adiar(BalancoInventario $balanco, CarbonInterface|string $novaData, string $motivo, User $user): BalancoInventario
    {
        $this->garantirStatus($balanco, BalancoInventarioStatus::Agendado, 'Apenas balanços agendados podem ser adiados.');

        $motivo = $this->textoObrigatorio($motivo, 'Informe o motivo do adiamento.');
        $dataAnterior = $balanco->data_agendada;

        $balanco->forceFill([
            'data_agendada' => $novaData,
        ])->save();

        $this->registrarEvento(
            $balanco,
            BalancoInventarioEventoTipo::Adiado,
            $motivo,
            $user,
            [
                'data_anterior' => $dataAnterior?->toDateTimeString(),
                'data_nova' => $balanco->data_agendada?->toDateTimeString(),
            ],
        );

        return $balanco->fresh();
    }

    public function iniciar(BalancoInventario $balanco, array $itemIdsSelecionados, User $user): BalancoInventario
    {
        $this->garantirStatus($balanco, BalancoInventarioStatus::Agendado, 'Apenas balanços agendados podem ser iniciados.');

        $balanco->loadMissing('inventario');

        if (! $balanco->inventario) {
            throw new DomainException('O inventário do balanço não foi encontrado.');
        }

        $elegiveis = $this->itensElegiveis($balanco->inventario);
        $elegiveisPorId = $elegiveis->keyBy('item_id');
        $selecionados = collect($itemIdsSelecionados)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selecionados->isEmpty()) {
            throw new DomainException('Selecione pelo menos um item para iniciar o balanço.');
        }

        $invalidos = $selecionados->reject(fn (int $itemId): bool => $elegiveisPorId->has($itemId));

        if ($invalidos->isNotEmpty()) {
            throw new DomainException('Há itens inválidos na seleção do balanço.');
        }

        $conflitos = $this->bloqueioService->buscarConflitos(
            (int) $balanco->inventario_id,
            $selecionados->all(),
            $balanco->getKey(),
        );

        if ($conflitos->isNotEmpty()) {
            $itens = $conflitos
                ->map(fn (BalancoInventarioItem $registro): string => ($registro->item?->nome ?? 'Item') . ' (' . ($registro->balanco?->codigo ?? 'N/A') . ')')
                ->unique()
                ->implode(', ');

            throw new DomainException("Os seguintes itens já estão em outro balanço em andamento: {$itens}.");
        }

        DB::transaction(function () use ($balanco, $elegiveis, $selecionados, $user) {
            $balanco->forceFill([
                'status' => BalancoInventarioStatus::EmAndamento,
                'iniciado_em' => now(),
                'iniciado_por_id' => $user->getKey(),
            ])->save();

            foreach ($elegiveis as $item) {
                $balanco->itens()->create([
                    'inventario_estoque_id' => $item['inventario_estoque_id'],
                    'item_id' => $item['item_id'],
                    'incluido_na_contagem' => $selecionados->contains($item['item_id']),
                    'saldo_sistema_antes' => $item['saldo_sistema'],
                    'valor_unitario_referencia' => $this->buscarValorUnitarioReferencia($item['item_id']),
                    'valor_impacto' => 0,
                ]);
            }

            $this->registrarEvento(
                $balanco,
                BalancoInventarioEventoTipo::Iniciado,
                "Balanço iniciado com {$selecionados->count()} item(ns) selecionado(s).",
                $user,
            );
        });

        return $balanco->fresh(['inventario.escola', 'itens.item', 'eventos.usuario', 'iniciadoPor']);
    }

    public function registrarContagem(BalancoInventarioItem $balancoItem, float $quantidadeContada, ?string $observacao, User $user): BalancoInventarioItem
    {
        $balancoItem->loadMissing(['balanco', 'item']);
        $balanco = $balancoItem->balanco;

        if (! $balanco || ! $balanco->isEmAndamento()) {
            throw new DomainException('Só é possível registrar contagem em balanços em andamento.');
        }

        if (! $balancoItem->incluido_na_contagem) {
            throw new DomainException('Este item está fora do balanço e não aceita contagem.');
        }

        if ($quantidadeContada < 0) {
            throw new DomainException('A quantidade contada não pode ser negativa.');
        }

        $quantidadeContada = $this->normalizarQuantidade($quantidadeContada);
        $saldoAntes = $this->normalizarQuantidade((float) $balancoItem->saldo_sistema_antes);
        $diferenca = $this->normalizarQuantidade($quantidadeContada - $saldoAntes);
        $valorUnitario = $this->normalizarValorMonetario(
            (float) ($balancoItem->valor_unitario_referencia ?: $this->buscarValorUnitarioReferencia($balancoItem->item_id))
        );

        $balancoItem->forceFill([
            'quantidade_contada' => $quantidadeContada,
            'saldo_final' => $quantidadeContada,
            'diferenca' => $diferenca,
            'valor_unitario_referencia' => $valorUnitario,
            'valor_impacto' => $this->normalizarValorMonetario($diferenca * $valorUnitario),
            'observacao_contagem' => $this->normalizarTexto($observacao),
            'contado_em' => now(),
            'contado_por_id' => $user->getKey(),
        ])->save();

        return $balancoItem->fresh(['item', 'contadoPor']);
    }

    public function manterSaldoAtual(BalancoInventarioItem $balancoItem, User $user, ?string $observacao = null): BalancoInventarioItem
    {
        return $this->registrarContagem(
            $balancoItem,
            (float) $balancoItem->saldo_sistema_antes,
            $observacao ?? 'Mantido saldo atual',
            $user,
        );
    }

    public function manterSaldoAtualEmLote(iterable $itens, User $user, ?string $observacao = null): int
    {
        return DB::transaction(function () use ($itens, $user, $observacao): int {
            $processados = 0;

            foreach ($itens as $item) {
                if (! $item instanceof BalancoInventarioItem) {
                    continue;
                }

                $this->manterSaldoAtual($item, $user, $observacao);
                $processados++;
            }

            return $processados;
        });
    }

    public function cancelar(BalancoInventario $balanco, string $motivo, User $user): BalancoInventario
    {
        if ($balanco->isConcluido() || $balanco->isCancelado()) {
            throw new DomainException('Este balanço não pode mais ser cancelado.');
        }

        $motivo = $this->textoObrigatorio($motivo, 'Informe o motivo do cancelamento.');

        $balanco->forceFill([
            'status' => BalancoInventarioStatus::Cancelado,
            'cancelado_em' => now(),
            'cancelado_por_id' => $user->getKey(),
        ])->save();

        $this->registrarEvento($balanco, BalancoInventarioEventoTipo::Cancelado, $motivo, $user);

        return $balanco->fresh();
    }

    public function concluir(BalancoInventario $balanco, User $user): BalancoInventario
    {
        $this->garantirStatus($balanco, BalancoInventarioStatus::EmAndamento, 'Apenas balanços em andamento podem ser concluídos.');

        DB::transaction(function () use ($balanco, $user) {
            /** @var EloquentCollection<int, BalancoInventarioItem> $itens */
            $itens = BalancoInventarioItem::query()
                ->where('balanco_inventario_id', $balanco->getKey())
                ->where('incluido_na_contagem', true)
                ->lockForUpdate()
                ->get();

            $pendentes = $itens->filter(fn (BalancoInventarioItem $item): bool => $item->quantidade_contada === null);

            if ($pendentes->isNotEmpty()) {
                throw new DomainException('Todos os itens selecionados precisam ter contagem registrada antes da conclusão.');
            }

            foreach ($itens as $itemBalanco) {
                $inventarioEstoque = InventarioEstoque::query()->firstOrCreate(
                    [
                        'inventario_id' => $balanco->inventario_id,
                        'item_id' => $itemBalanco->item_id,
                    ],
                    [
                        'quantidade' => 0,
                    ],
                );

                $quantidadeContada = $this->normalizarQuantidade((float) $itemBalanco->quantidade_contada);
                $saldoAntes = $this->normalizarQuantidade((float) $itemBalanco->saldo_sistema_antes);
                $diferenca = $this->normalizarQuantidade($quantidadeContada - $saldoAntes);
                $valorUnitario = $this->normalizarValorMonetario(
                    (float) ($itemBalanco->valor_unitario_referencia ?: $this->buscarValorUnitarioReferencia($itemBalanco->item_id))
                );
                $observacao = "Reajustado via Balanço de Inventário #{$balanco->codigo}";

                if ($diferenca > 0) {
                    $inventarioEstoque->entrada($diferenca, null, $observacao, $balanco->getKey());
                } elseif ($diferenca < 0) {
                    $inventarioEstoque->saida(abs($diferenca), null, $observacao, $balanco->getKey());
                }

                $inventarioEstoque->refresh();

                $itemBalanco->forceFill([
                    'saldo_final' => $this->normalizarQuantidade((float) $inventarioEstoque->quantidade),
                    'diferenca' => $diferenca,
                    'valor_unitario_referencia' => $valorUnitario,
                    'valor_impacto' => $this->normalizarValorMonetario($diferenca * $valorUnitario),
                ])->save();
            }

            $balanco->forceFill([
                'status' => BalancoInventarioStatus::Concluido,
                'concluido_em' => now(),
                'concluido_por_id' => $user->getKey(),
            ])->save();

            $impactoTotal = $this->normalizarValorMonetario((float) $itens->sum('valor_impacto'));

            $this->registrarEvento(
                $balanco,
                BalancoInventarioEventoTipo::Concluido,
                'Balanço concluído com reajuste dos itens contados.',
                $user,
                [
                    'impacto_financeiro_total' => $impactoTotal,
                ],
            );
        });

        return $balanco->fresh(['inventario.escola', 'itens.item', 'eventos.usuario', 'concluidoPor']);
    }

    public function itensElegiveis(Inventario $inventario): Collection
    {
        return InventarioEstoque::query()
            ->with('item')
            ->where('inventario_id', $inventario->getKey())
            ->get()
            ->filter(fn (InventarioEstoque $estoque): bool => $estoque->item !== null)
            ->sortBy(fn (InventarioEstoque $estoque): string => mb_strtolower((string) $estoque->item?->nome))
            ->map(function (InventarioEstoque $estoque): array {
                return [
                    'inventario_estoque_id' => $estoque->getKey(),
                    'item_id' => (int) $estoque->item_id,
                    'item' => $estoque->item,
                    'saldo_sistema' => $this->normalizarQuantidade((float) $estoque->quantidade),
                ];
            })
            ->values();
    }

    public function itensDisponiveisParaInicio(BalancoInventario $balanco): Collection
    {
        $balanco->loadMissing('inventario');

        if (! $balanco->inventario) {
            return collect();
        }

        $itens = $this->itensElegiveis($balanco->inventario);
        $conflitos = $this->bloqueioService
            ->buscarConflitos((int) $balanco->inventario_id, $itens->pluck('item_id')->all(), $balanco->getKey())
            ->keyBy('item_id');

        return $itens->map(function (array $item) use ($conflitos): array {
            /** @var BalancoInventarioItem|null $conflito */
            $conflito = $conflitos->get($item['item_id']);

            return [
                ...$item,
                'bloqueado' => $conflito !== null,
                'balanco_codigo' => $conflito?->balanco?->codigo,
            ];
        });
    }

    protected function buscarValorUnitarioReferencia(int $itemId): float
    {
        $preco = ContratoItem::query()
            ->where('item_id', $itemId)
            ->whereHas('contrato', fn ($query) => $query->where('ativo', true))
            ->latest('updated_at')
            ->latest('id')
            ->value('preco_unitario');

        if ($preco === null) {
            $preco = ContratoItem::query()
                ->where('item_id', $itemId)
                ->latest('updated_at')
                ->latest('id')
                ->value('preco_unitario');
        }

        return $this->normalizarValorMonetario((float) ($preco ?? 0));
    }

    protected function registrarEvento(
        BalancoInventario $balanco,
        BalancoInventarioEventoTipo $tipo,
        string $descricao,
        ?User $usuario,
        array $dados = [],
    ): BalancoInventarioEvento {
        return $balanco->eventos()->create([
            'tipo' => $tipo,
            'descricao' => $descricao,
            'dados' => $dados === [] ? null : $dados,
            'usuario_id' => $usuario?->getKey(),
        ]);
    }

    protected function garantirStatus(BalancoInventario $balanco, BalancoInventarioStatus $status, string $mensagem): void
    {
        if ($balanco->status !== $status) {
            throw new DomainException($mensagem);
        }
    }

    protected function textoObrigatorio(?string $texto, string $mensagem): string
    {
        $texto = $this->normalizarTexto($texto);

        if (! filled($texto)) {
            throw new DomainException($mensagem);
        }

        return $texto;
    }

    protected function normalizarTexto(?string $texto): ?string
    {
        $texto = trim((string) $texto);

        return $texto !== '' ? $texto : null;
    }

    protected function normalizarQuantidade(float $quantidade): float
    {
        $normalizada = round($quantidade, 3);

        return abs($normalizada) < 0.0005 ? 0.0 : $normalizada;
    }

    protected function normalizarValorMonetario(float $valor): float
    {
        $normalizado = round($valor, 2);

        return abs($normalizado) < 0.005 ? 0.0 : $normalizado;
    }
}
