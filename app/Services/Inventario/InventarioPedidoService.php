<?php

namespace App\Services\Inventario;

use App\Models\Enums\InventarioPedidoItemStatus;
use App\Models\Enums\InventarioPedidoStatus;
use App\Models\Estoque;
use App\Models\InventarioEstoque;
use App\Models\InventarioPedido;
use App\Models\InventarioRomaneio;
use App\Models\Item;
use App\Models\User;
use App\Services\UserSetorAccessService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventarioPedidoService
{
    public function __construct(
        protected InventarioContextService $contextService,
    ) {}

    public function queryTabela(?User $user): Builder
    {
        $query = InventarioPedido::query()
            ->with([
                'escola',
                'inventario.escola',
                'romaneio',
                'solicitadoPor',
                'aprovadoPor',
                'entreguePor',
            ])
            ->withCount('itens')
            ->orderByDesc('updated_at');

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $access = app(UserSetorAccessService::class);

        if ($access->hasGlobalAccess($user)) {
            return $query;
        }

        $setorIds = $access->visibleSetorIds($user);

        if ($setorIds !== []) {
            return $query->whereHas('inventario', function (Builder $inventario) use ($setorIds): void {
                $inventario->whereIn('setor_id', $setorIds)
                    ->orWhere(function (Builder $legacy) use ($setorIds): void {
                        $legacy->whereNull('setor_id')
                            ->whereHas('escola', fn (Builder $escola): Builder => $escola->whereIn('setor_id', $setorIds));
                    });
            });
        }

        $inventario = $this->contextService->inventarioDoUsuario($user);

        if (! $inventario) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('inventario_id', $inventario->getKey());
    }

    public function criarPedido(User $user, array $data): InventarioPedido
    {
        $inventario = $this->contextService->inventarioDoUsuario($user);

        // Impacto: este guard evita que uma escola solicite itens em inventario de outra unidade. Afeta pedidos, romaneios e recebimento no estoque escolar.
        if (! $inventario || blank($user->id_escola) || (int) $inventario->escola_id !== (int) $user->id_escola) {
            throw new DomainException('O usuario nao possui inventario escolar disponivel para solicitar itens.');
        }

        // Impacto: remover este bloqueio permite varios romaneios simultaneos para a mesma escola, dificultando conferencia e podendo duplicar reservas da matriz.
        if ($this->escolaPossuiPedidoEmAndamento($user)) {
            throw new DomainException('Pedido Em Andamento aguardando confirmação de Recebimento,  confirme o recebimento do pedido em andamento para realizar um novo pedido');
        }

        $itens = $this->normalizarItensSolicitados($data['itens'] ?? []);

        if ($itens->isEmpty()) {
            throw new DomainException('Informe ao menos um item com quantidade solicitada.');
        }

        return DB::transaction(function () use ($inventario, $user, $data, $itens): InventarioPedido {
            $pedido = InventarioPedido::query()->create([
                'inventario_id' => $inventario->getKey(),
                'escola_id' => $inventario->escola_id,
                'status' => InventarioPedidoStatus::Pendente,
                'observacao_escola' => $this->texto($data['observacao_escola'] ?? null),
                'solicitado_por_id' => $user->getKey(),
            ]);

            foreach ($itens as $item) {
                $pedido->itens()->create([
                    'item_id' => $item['item_id'],
                    'quantidade_solicitada' => $item['quantidade_solicitada'],
                    'observacao_solicitacao' => $item['observacao_solicitacao'],
                    'status' => InventarioPedidoItemStatus::Pendente,
                ]);
            }

            return $pedido->fresh(['itens.item', 'inventario.escola', 'solicitadoPor']);
        });
    }

    public function aprovarPedido(InventarioPedido $pedido, array $itensData, ?string $observacaoGestor, User $user): InventarioPedido
    {
        if (! $this->contextService->ehGestorGeral($user)) {
            throw new DomainException('Somente o gestor geral pode analisar pedidos de inventario.');
        }

        if (! app(UserSetorAccessService::class)->hasGlobalAccess($user)
            && ! app(UserSetorAccessService::class)->canAccessSetor($user, $pedido->inventario?->setor_id)) {
            throw new DomainException('O usuario nao tem permissao para analisar este pedido de inventario.');
        }

        if (! $pedido->isPendente()) {
            throw new DomainException('Apenas pedidos pendentes podem ser analisados.');
        }

        $mapaItens = collect($itensData)
            ->map(fn (array $item): array => [
                'item_id' => (int) ($item['item_id'] ?? 0),
                'quantidade_aprovada' => round((float) ($item['quantidade_aprovada'] ?? 0), 3),
                'observacao_aprovacao' => $this->texto($item['observacao_aprovacao'] ?? null),
            ])
            ->keyBy('item_id');

        return DB::transaction(function () use ($pedido, $user, $mapaItens, $observacaoGestor): InventarioPedido {
            $aprovados = 0;

            foreach ($pedido->itens()->with('item')->get() as $pedidoItem) {
                $payload = $mapaItens->get($pedidoItem->item_id);
                $quantidadeSolicitada = round((float) $pedidoItem->quantidade_solicitada, 3);
                $quantidadeAprovada = round((float) ($payload['quantidade_aprovada'] ?? 0), 3);

                if ($quantidadeAprovada < 0) {
                    throw new DomainException('Nao e permitido aprovar quantidade negativa.');
                }

                // Impacto: aprovar acima do solicitado muda a base do romaneio e pode reservar saldo que a escola nao pediu.
                if ($quantidadeAprovada > $quantidadeSolicitada) {
                    throw new DomainException("A quantidade aprovada do item {$pedidoItem->item?->nome} nao pode exceder a solicitada.");
                }

                $statusItem = $quantidadeAprovada > 0
                    ? InventarioPedidoItemStatus::Aprovado
                    : InventarioPedidoItemStatus::Recusado;

                if ($statusItem === InventarioPedidoItemStatus::Aprovado) {
                    $aprovados++;
                }

                $pedidoItem->update([
                    'quantidade_aprovada' => $quantidadeAprovada,
                    'observacao_aprovacao' => $payload['observacao_aprovacao'] ?? null,
                    'status' => $statusItem,
                ]);
            }

            $pedido->update([
                'status' => $aprovados > 0 ? InventarioPedidoStatus::Aprovado : InventarioPedidoStatus::Recusado,
                'observacao_gestor' => $this->texto($observacaoGestor),
                'aprovado_por_id' => $user->getKey(),
                'aprovado_em' => now(),
            ]);

            return $pedido->fresh([
                'itens.item',
                'inventario.escola',
                'solicitadoPor',
                'aprovadoPor',
            ]);
        });
    }

    public function gerarRomaneio(array $pedidoIds, ?string $observacoes, User $user): InventarioRomaneio
    {
        if (! $this->contextService->ehGestorGeral($user)) {
            throw new DomainException('Somente o gestor geral pode gerar romaneios.');
        }

        $pedidoIds = collect($pedidoIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($pedidoIds->isEmpty()) {
            throw new DomainException('Selecione ao menos um pedido aprovado para gerar o romaneio.');
        }

        $pedidosQuery = InventarioPedido::query()
            ->with(['itens.item', 'escola', 'inventario'])
            ->whereIn('id', $pedidoIds)
            ->where('status', InventarioPedidoStatus::Aprovado);

        if (! app(UserSetorAccessService::class)->hasGlobalAccess($user)) {
            $setorIds = app(UserSetorAccessService::class)->visibleSetorIds($user);

            $pedidosQuery->whereHas('inventario', function (Builder $inventario) use ($setorIds): void {
                $inventario->whereIn('setor_id', $setorIds)
                    ->orWhere(function (Builder $legacy) use ($setorIds): void {
                        $legacy->whereNull('setor_id')
                            ->whereHas('escola', fn (Builder $escola): Builder => $escola->whereIn('setor_id', $setorIds));
                    });
            });
        }

        $pedidos = $pedidosQuery->get();

        if ($pedidos->count() !== $pedidoIds->count()) {
            throw new DomainException('Todos os pedidos selecionados precisam estar com status aprovado.');
        }

        $totaisPorItem = $this->agruparTotaisDoRomaneio($pedidos);

        // Impacto: a pre-validacao evita criar romaneio parcial. Se removida, alguns itens podem ser reservados antes da falha de outro item.
        foreach ($totaisPorItem as $itemId => $quantidade) {
            $estoque = Estoque::query()->where('item_id', $itemId)->first();
            $itemNome = Item::query()->whereKey($itemId)->value('nome') ?? 'Item';

            if (! $estoque || $quantidade > $estoque->quantidade_disponivel) {
                throw new DomainException("Saldo insuficiente no estoque da matriz para o item {$itemNome}.");
            }
        }

        return DB::transaction(function () use ($pedidos, $totaisPorItem, $observacoes, $user): InventarioRomaneio {
            foreach ($totaisPorItem as $itemId => $quantidade) {
                $estoque = Estoque::query()->where('item_id', $itemId)->lockForUpdate()->firstOrFail();
                $itemNome = $estoque->item?->nome ?? 'Item';

                // Impacto: esta reserva compromete saldo da matriz ate a conferencia. Trocar por saida direta quebraria a logica de divergencia na entrega.
                $estoque->reservar(
                    $quantidade,
                    "Reserva para romaneio de inventario - {$itemNome}"
                );
            }

            $romaneio = InventarioRomaneio::query()->create([
                'observacoes' => $this->texto($observacoes),
                'gerado_por_id' => $user->getKey(),
                'gerado_em' => now(),
            ]);

            foreach ($pedidos as $pedido) {
                $pedido->update([
                    'status' => InventarioPedidoStatus::EmAndamento,
                    'inventario_romaneio_id' => $romaneio->getKey(),
                    'em_andamento_em' => now(),
                ]);
            }

            return $romaneio->fresh(['pedidos.itens.item', 'pedidos.escola', 'geradoPor']);
        });
    }

    public function conferirEntrega(InventarioPedido $pedido, array $itensData, ?string $observacaoConferencia, User $user): InventarioPedido
    {
        if (! $pedido->isEmAndamento()) {
            throw new DomainException('Somente pedidos em andamento podem ser conferidos.');
        }

        if (
            ! app(UserSetorAccessService::class)->hasGlobalAccess($user)
            && (int) $pedido->escola_id !== (int) $user->id_escola
            && ! app(UserSetorAccessService::class)->canAccessSetor($user, $pedido->inventario?->setor_id)
        ) {
            throw new DomainException('O usuario nao tem permissao para conferir este pedido.');
        }

        $mapaItens = collect($itensData)
            ->map(fn (array $item): array => [
                'item_id' => (int) ($item['item_id'] ?? 0),
                'quantidade_recebida' => round((float) ($item['quantidade_recebida'] ?? 0), 3),
                'observacao_conferencia' => $this->texto($item['observacao_conferencia'] ?? null),
            ])
            ->keyBy('item_id');

        $observacaoConferencia = $this->texto($observacaoConferencia);

        return DB::transaction(function () use ($pedido, $mapaItens, $observacaoConferencia, $user): InventarioPedido {
            $houveDivergencia = false;
            $pedido->loadMissing(['itens.item', 'inventario.escola', 'romaneio']);

            foreach ($pedido->itens as $pedidoItem) {
                if ($pedidoItem->status === InventarioPedidoItemStatus::Recusado) {
                    continue;
                }

                $payload = $mapaItens->get($pedidoItem->item_id, [
                    'item_id' => $pedidoItem->item_id,
                    'quantidade_recebida' => (float) ($pedidoItem->quantidade_aprovada ?? 0),
                    'observacao_conferencia' => null,
                ]);

                $quantidadeAprovada = round((float) ($pedidoItem->quantidade_aprovada ?? 0), 3);
                $quantidadeRecebida = round((float) ($payload['quantidade_recebida'] ?? 0), 3);

                if ($quantidadeRecebida < 0) {
                    throw new DomainException('A quantidade recebida nao pode ser negativa.');
                }

                // Impacto: divergencia exige justificativa para manter auditoria entre romaneio, baixa da matriz e entrada no inventario escolar.
                if ($quantidadeRecebida !== $quantidadeAprovada) {
                    $houveDivergencia = true;
                }

                $pedidoItem->update([
                    'quantidade_recebida' => $quantidadeRecebida,
                    'observacao_conferencia' => $payload['observacao_conferencia'] ?? null,
                    'status' => InventarioPedidoItemStatus::Conferido,
                ]);

                $estoqueMatriz = Estoque::query()
                    ->where('item_id', $pedidoItem->item_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $estoqueMatriz->confirmarEntregaReservada(
                    $quantidadeAprovada,
                    $quantidadeRecebida,
                    'Entrega para pedido de inventario #' . $pedido->getKey() . ' - ' . ($pedido->escola?->nome ?? 'Escola'),
                );

                // Impacto: somente o recebido entra no inventario escolar; mudar para aprovado faria o estoque da escola divergir da conferencia fisica.
                if ($quantidadeRecebida > 0) {
                    $inventarioEstoque = InventarioEstoque::query()->firstOrCreate(
                        [
                            'inventario_id' => $pedido->inventario_id,
                            'item_id' => $pedidoItem->item_id,
                        ],
                        [
                            'quantidade' => 0,
                        ],
                    );

                    $inventarioEstoque->entrada(
                        $quantidadeRecebida,
                        $pedido->getKey(),
                        'Recebimento do romaneio ' . ($pedido->romaneio?->codigo ?? 'sem codigo'),
                    );
                }
            }

            if ($houveDivergencia && ! filled($observacaoConferencia)) {
                throw new DomainException('Informe a observacao da conferencia quando houver divergencia entre romaneio e entrega.');
            }

            $pedido->update([
                'status' => InventarioPedidoStatus::Entregue,
                'observacao_conferencia' => $observacaoConferencia,
                'entregue_por_id' => $user->getKey(),
                'entregue_em' => now(),
            ]);

            return $pedido->fresh([
                'itens.item',
                'inventario.escola',
                'romaneio',
                'solicitadoPor',
                'aprovadoPor',
                'entreguePor',
            ]);
        });
    }

    public function pedidosAguardandoRomaneio(): Collection
    {
        return InventarioPedido::query()
            ->with(['escola', 'inventario', 'itens.item'])
            ->where('status', InventarioPedidoStatus::Aprovado)
            ->orderBy('created_at')
            ->get();
    }

    public function escolaPossuiPedidoEmAndamento(User $user): bool
    {
        if ($this->contextService->ehGestorGeral($user) || blank($user->id_escola)) {
            return false;
        }

        return InventarioPedido::query()
            ->where('escola_id', $user->id_escola)
            ->where('status', InventarioPedidoStatus::EmAndamento)
            ->exists();
    }

    protected function normalizarItensSolicitados(array $itens): Collection
    {
        return collect($itens)
            ->map(function (array $item): array {
                return [
                    'item_id' => (int) ($item['item_id'] ?? 0),
                    'quantidade_solicitada' => round((float) ($item['quantidade_solicitada'] ?? 0), 3),
                    'observacao_solicitacao' => $this->texto($item['observacao_solicitacao'] ?? null),
                ];
            })
            ->filter(fn (array $item): bool => $item['item_id'] > 0 && $item['quantidade_solicitada'] > 0)
            ->unique('item_id')
            ->values();
    }

    protected function agruparTotaisDoRomaneio(Collection $pedidos): Collection
    {
        return $pedidos
            ->flatMap(fn (InventarioPedido $pedido) => $pedido->itens)
            ->filter(fn ($item) => (float) ($item->quantidade_aprovada ?? 0) > 0)
            ->groupBy('item_id')
            ->map(fn (Collection $itens): float => round((float) $itens->sum('quantidade_aprovada'), 3));
    }

    protected function texto(?string $texto): ?string
    {
        $texto = trim((string) $texto);

        return $texto !== '' ? $texto : null;
    }
}
