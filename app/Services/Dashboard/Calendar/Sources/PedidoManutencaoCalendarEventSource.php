<?php

namespace App\Services\Dashboard\Calendar\Sources;

use App\Contracts\Dashboard\CalendarEventSource;
use App\Filament\Admin\Resources\Pedidos\PedidoResource;
use App\Models\Enums\DashboardPrioridade;
use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\Pedido;
use App\Services\PedidoService;
use App\Services\Dashboard\SetorPathLabelService;
use App\Support\Dashboard\Calendar\CalendarEventData;
use App\Support\Dashboard\Calendar\CalendarEventDetailData;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

class PedidoManutencaoCalendarEventSource implements CalendarEventSource
{
    public function __construct(
        private readonly PedidoService $pedidos,
        private readonly SetorPathLabelService $setorLabels,
    ) {}

    public function key(): string
    {
        return 'pedidos_manutencao';
    }

    public function supports(CalendarQueryContext $context): bool
    {
        return (bool) config('dashboard.calendar.sources.pedidos_manutencao', true)
            && Gate::forUser($context->user)->allows('viewAny', Pedido::class);
    }

    public function events(CalendarQueryContext $context): iterable
    {
        $query = $this->visibleQuery($context)
            ->whereBetween('data_prevista', [$context->inicio->toDateString(), $context->fim->toDateString()])
            ->with([
                'tipoStatus:id,nome,cor,finaliza_pedido,cancela_pedido',
                'tipoManutencao:id,nome',
                'escola:id,nome',
                'setor:id,nome,path',
            ])
            ->orderBy('data_prevista')
            ->limit(max(1, (int) config('dashboard.calendar.max_events', 500)) + 1);

        $pedidos = $query->get();
        $setorLabels = $this->setorLabels->labels($pedidos->pluck('setor'));

        foreach ($pedidos as $pedido) {
            yield $this->map(
                $pedido,
                $context,
                detail: false,
                setorLabel: $setorLabels[(int) $pedido->setor_id] ?? null,
            );
        }
    }

    public function detail(CalendarQueryContext $context, string $reference): ?CalendarEventDetailData
    {
        $id = filter_var($reference, FILTER_VALIDATE_INT);

        if (! $id) {
            return null;
        }

        $pedido = $this->visibleQuery($context)
            ->whereBetween('data_prevista', [
                $context->inicio->toDateString(),
                $context->fim->toDateString(),
            ])
            ->with(['tipoStatus', 'tipoManutencao', 'escola', 'setor'])
            ->find($id);

        if (! $pedido) {
            return null;
        }

        return new CalendarEventDetailData(
            event: $this->map(
                $pedido,
                $context,
                detail: true,
                setorLabel: $this->setorLabels->labels([$pedido->setor])[(int) $pedido->setor_id] ?? null,
            ),
            descricao: $pedido->descricao_pedido,
            metadata: [
                'Protocolo' => $pedido->numero_protocolo,
                'Tipo' => $pedido->tipoManutencao?->nome,
                'Prioridade' => $pedido->nivel_prioridade?->label(),
            ],
        );
    }

    private function visibleQuery(CalendarQueryContext $context): Builder
    {
        $query = $this->pedidos
            ->queryPorPerfil(Pedido::query(), $context->user)
            ->where('pedidos.ativo', true)
            ->where('is_pedido_adicional', false)
            ->whereNull('data_entrega')
            ->whereNotNull('data_prevista')
            ->whereHas('tipoStatus', fn (Builder $status): Builder => $status
                ->where('finaliza_pedido', false)
                ->where('cancela_pedido', false));

        if ($context->escolaId) {
            $query->where('escola_id', $context->escolaId);
        }

        if ($context->setorId) {
            $query->where(function (Builder $setores) use ($context): void {
                $setores
                    ->where('setor_id', $context->setorId)
                    ->orWhere('setor_origem_id', $context->setorId);
            });
        }

        return $query;
    }

    private function map(
        Pedido $pedido,
        CalendarQueryContext $context,
        bool $detail,
        ?string $setorLabel = null,
    ): CalendarEventData
    {
        $date = CarbonImmutable::parse($pedido->data_prevista);
        $statusKey = Str::slug((string) ($pedido->tipoStatus?->nome ?? 'pendente'), '_');

        return new CalendarEventData(
            id: $this->key().':'.$pedido->getKey(),
            source: $this->key(),
            reference: (string) $pedido->getKey(),
            titulo: 'Pedido '.$pedido->numero_protocolo,
            resumo: Str::limit((string) ($pedido->descricao_pedido ?: $pedido->tipoManutencao?->nome), 180),
            inicio: $date->startOfDay(),
            fim: $date->endOfDay(),
            diaInteiro: true,
            categoria: 'manutencao',
            categoriaLabel: 'Manutenção',
            assunto: $pedido->tipoManutencao?->nome,
            status: $statusKey,
            statusLabel: $pedido->tipoStatus?->nome ?? 'Pendente',
            prioridade: $this->priority($pedido),
            progresso: null,
            cor: $this->priority($pedido) === DashboardPrioridade::Urgente ? 'vermelho' : 'ambar',
            escolaId: $pedido->escola_id ? (int) $pedido->escola_id : null,
            escola: $pedido->escola?->nome,
            setorId: $pedido->setor_id ? (int) $pedido->setor_id : null,
            setor: $setorLabel ?? $pedido->setor?->nome,
            origem: 'Pedidos de manutenção',
            actionUrl: $actionUrl = $this->actionUrl($pedido, $context, $detail),
            actionLabel: ! $actionUrl
                ? null
                : ($detail && Gate::forUser($context->user)->allows('update', $pedido)
                    ? 'Gerenciar pedido'
                    : 'Ver pedidos'),
        );
    }

    private function priority(Pedido $pedido): DashboardPrioridade
    {
        return match ($pedido->nivel_prioridade) {
            NivelEmergenciaPedido::EMERGENCIAL => DashboardPrioridade::Urgente,
            NivelEmergenciaPedido::CORRETIVO => DashboardPrioridade::Alta,
            NivelEmergenciaPedido::PREVENTIVO => DashboardPrioridade::Normal,
            default => DashboardPrioridade::Baixa,
        };
    }

    private function actionUrl(Pedido $pedido, CalendarQueryContext $context, bool $detail): ?string
    {
        try {
            if ($detail && Gate::forUser($context->user)->allows('update', $pedido)) {
                return PedidoResource::getUrl('edit', ['record' => $pedido]);
            }

            return PedidoResource::getUrl('index');
        } catch (Throwable) {
            return null;
        }
    }
}
