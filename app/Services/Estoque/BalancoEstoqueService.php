<?php

namespace App\Services\Estoque;

use App\Models\BalancoEstoque;
use App\Models\BalancoEstoqueEvento;
use App\Models\BalancoEstoqueItem;
use App\Models\Enums\BalancoEstoqueEventoTipo;
use App\Models\Enums\BalancoEstoqueStatus;
use App\Models\Item;
use App\Models\User;
use App\Models\Estoque;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BalancoEstoqueService
{
    public function __construct(
        protected BalancoEstoqueBloqueioService $bloqueioService,
    ) {}

    public function agendar(array $data, User $user): BalancoEstoque
    {
        return DB::transaction(function () use ($data, $user) {
            $balanco = BalancoEstoque::query()->create([
                'status' => BalancoEstoqueStatus::Agendado,
                'data_agendada' => $data['data_agendada'],
                'observacao_inicial' => $this->normalizarTexto($data['observacao_inicial'] ?? null),
                'criado_por_id' => $user->getKey(),
            ]);

            $this->registrarEvento(
                $balanco,
                BalancoEstoqueEventoTipo::Criado,
                "Balanço agendado para {$balanco->data_agendada?->format('d/m/Y H:i')}.",
                $user,
            );

            return $balanco->fresh(['criadoPor']);
        });
    }

    public function adiar(BalancoEstoque $balanco, CarbonInterface|string $novaData, string $motivo, User $user): BalancoEstoque
    {
        $this->garantirStatus($balanco, BalancoEstoqueStatus::Agendado, 'Apenas balanços agendados podem ser adiados.');

        $motivo = $this->textoObrigatorio($motivo, 'Informe o motivo do adiamento.');
        $dataAnterior = $balanco->data_agendada;

        $balanco->forceFill([
            'data_agendada' => $novaData,
        ])->save();

        $this->registrarEvento(
            $balanco,
            BalancoEstoqueEventoTipo::Adiado,
            $motivo,
            $user,
            [
                'data_anterior' => $dataAnterior?->toDateTimeString(),
                'data_nova' => $balanco->data_agendada?->toDateTimeString(),
            ],
        );

        return $balanco->fresh();
    }

    public function iniciar(BalancoEstoque $balanco, array $itemIdsSelecionados, User $user): BalancoEstoque
    {
        $this->garantirStatus($balanco, BalancoEstoqueStatus::Agendado, 'Apenas balanços agendados podem ser iniciados.');

        $elegiveis = $this->itensElegiveis();
        $elegiveisPorId = $elegiveis->keyBy('item_id');
        $selecionados = collect($itemIdsSelecionados)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selecionados->isEmpty()) {
            throw new DomainException('Selecione pelo menos um item para iniciar o balanço.');
        }

        $invalidos = $selecionados->reject(fn (int $itemId) => $elegiveisPorId->has($itemId));

        if ($invalidos->isNotEmpty()) {
            throw new DomainException('Há itens inválidos na seleção do balanço.');
        }

        $conflitos = $this->bloqueioService->buscarConflitos($selecionados->all(), $balanco->getKey());

        if ($conflitos->isNotEmpty()) {
            $itens = $conflitos
                ->map(fn (BalancoEstoqueItem $registro) => ($registro->item?->nome ?? 'Item') . ' (' . ($registro->balanco?->codigo ?? 'N/A') . ')')
                ->unique()
                ->implode(', ');

            throw new DomainException("Os seguintes itens já estão em outro balanço em andamento: {$itens}.");
        }

        DB::transaction(function () use ($balanco, $elegiveis, $selecionados, $user) {
            $balanco->forceFill([
                'status' => BalancoEstoqueStatus::EmAndamento,
                'iniciado_em' => now(),
                'iniciado_por_id' => $user->getKey(),
            ])->save();

            foreach ($elegiveis as $item) {
                $balanco->itens()->create([
                    'item_id' => $item['item_id'],
                    'incluido_na_contagem' => $selecionados->contains($item['item_id']),
                    'saldo_sistema_antes' => $item['saldo_sistema'],
                ]);
            }

            $this->registrarEvento(
                $balanco,
                BalancoEstoqueEventoTipo::Iniciado,
                "Balanço iniciado com {$selecionados->count()} item(ns) selecionado(s).",
                $user,
            );
        });

        return $balanco->fresh(['itens.item', 'eventos.usuario', 'iniciadoPor']);
    }

    public function registrarContagem(BalancoEstoqueItem $balancoItem, float $quantidadeContada, ?string $observacao, User $user): BalancoEstoqueItem
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
            throw new DomainException('A quantidade contada nÃ£o pode ser negativa.');
        }

        $quantidadeContada = $this->normalizarQuantidade($quantidadeContada);
        $saldoAntes = $this->normalizarQuantidade((float) $balancoItem->saldo_sistema_antes);
        $diferenca = $this->normalizarQuantidade($quantidadeContada - $saldoAntes);

        $balancoItem->forceFill([
            'quantidade_contada' => $quantidadeContada,
            'saldo_final' => $quantidadeContada,
            'diferenca' => $diferenca,
            'observacao_contagem' => $this->normalizarTexto($observacao),
            'contado_em' => now(),
            'contado_por_id' => $user->getKey(),
        ])->save();

        return $balancoItem->fresh(['item', 'contadoPor']);
    }

    public function cancelar(BalancoEstoque $balanco, string $motivo, User $user): BalancoEstoque
    {
        if ($balanco->isConcluido() || $balanco->isCancelado()) {
            throw new DomainException('Este balanço não pode mais ser cancelado.');
        }

        $motivo = $this->textoObrigatorio($motivo, 'Informe o motivo do cancelamento.');

        $balanco->forceFill([
            'status' => BalancoEstoqueStatus::Cancelado,
            'cancelado_em' => now(),
            'cancelado_por_id' => $user->getKey(),
        ])->save();

        $this->registrarEvento($balanco, BalancoEstoqueEventoTipo::Cancelado, $motivo, $user);

        return $balanco->fresh();
    }

    public function concluir(BalancoEstoque $balanco, User $user): BalancoEstoque
    {
        $this->garantirStatus($balanco, BalancoEstoqueStatus::EmAndamento, 'Apenas balanços em andamento podem ser concluídos.');

        DB::transaction(function () use ($balanco, $user) {
            /** @var EloquentCollection<int, BalancoEstoqueItem> $itens */
            $itens = BalancoEstoqueItem::query()
                ->where('balanco_estoque_id', $balanco->getKey())
                ->where('incluido_na_contagem', true)
                ->lockForUpdate()
                ->get();

            $pendentes = $itens->filter(fn (BalancoEstoqueItem $item) => $item->quantidade_contada === null);

            if ($pendentes->isNotEmpty()) {
                throw new DomainException('Todos os itens selecionados precisam ter contagem registrada antes da conclusão.');
            }

            foreach ($itens as $itemBalanco) {
                $estoque = Estoque::query()->firstOrCreate(
                    ['item_id' => $itemBalanco->item_id],
                    ['quantidade' => 0],
                );

                $quantidadeContada = $this->normalizarQuantidade((float) $itemBalanco->quantidade_contada);
                $saldoAntes = $this->normalizarQuantidade((float) $itemBalanco->saldo_sistema_antes);
                $diferenca = $this->normalizarQuantidade($quantidadeContada - $saldoAntes);
                $observacao = "Reajustado via Balanço #{$balanco->codigo}";

                if ($diferenca > 0) {
                    $estoque->entrada($diferenca, null, $observacao, $balanco->getKey());
                } elseif ($diferenca < 0) {
                    $estoque->saida(abs($diferenca), null, $observacao, $balanco->getKey());
                }

                $estoque->refresh();

                $itemBalanco->forceFill([
                    'saldo_final' => $this->normalizarQuantidade((float) $estoque->quantidade),
                    'diferenca' => $diferenca,
                ])->save();
            }

            $balanco->forceFill([
                'status' => BalancoEstoqueStatus::Concluido,
                'concluido_em' => now(),
                'concluido_por_id' => $user->getKey(),
            ])->save();

            $this->registrarEvento(
                $balanco,
                BalancoEstoqueEventoTipo::Concluido,
                'Balanço concluído com reajuste dos itens contados.',
                $user,
            );
        });

        return $balanco->fresh(['itens.item', 'eventos.usuario', 'concluidoPor']);
    }

    public function itensElegiveis(): Collection
    {
        $saldos = Estoque::query()
            ->get(['item_id', 'quantidade'])
            ->keyBy('item_id');

        $idsComEstoque = $saldos->keys()->all();

        return Item::query()
            ->where(function ($query) use ($idsComEstoque) {
                $query->where('ativo', true);

                if ($idsComEstoque !== []) {
                    $query->orWhereIn('id', $idsComEstoque);
                }
            })
            ->orderBy('nome')
            ->get()
            ->map(function (Item $item) use ($saldos): array {
                $saldo = $saldos->get($item->getKey());

                return [
                    'item_id' => $item->getKey(),
                    'item' => $item,
                    'saldo_sistema' => $this->normalizarQuantidade((float) ($saldo?->quantidade ?? 0)),
                ];
            })
            ->values();
    }

    public function itensDisponiveisParaInicio(BalancoEstoque $balanco): Collection
    {
        $itens = $this->itensElegiveis();
        $conflitos = $this->bloqueioService
            ->buscarConflitos($itens->pluck('item_id')->all(), $balanco->getKey())
            ->keyBy('item_id');

        return $itens->map(function (array $item) use ($conflitos): array {
            /** @var BalancoEstoqueItem|null $conflito */
            $conflito = $conflitos->get($item['item_id']);

            return [
                ...$item,
                'bloqueado' => $conflito !== null,
                'balanco_codigo' => $conflito?->balanco?->codigo,
            ];
        });
    }

    protected function registrarEvento(
        BalancoEstoque $balanco,
        BalancoEstoqueEventoTipo $tipo,
        string $descricao,
        ?User $usuario,
        array $dados = [],
    ): BalancoEstoqueEvento {
        return $balanco->eventos()->create([
            'tipo' => $tipo,
            'descricao' => $descricao,
            'dados' => $dados === [] ? null : $dados,
            'usuario_id' => $usuario?->getKey(),
        ]);
    }

    protected function garantirStatus(BalancoEstoque $balanco, BalancoEstoqueStatus $status, string $mensagem): void
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
}
