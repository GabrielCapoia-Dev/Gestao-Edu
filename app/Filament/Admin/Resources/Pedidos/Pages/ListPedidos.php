<?php

namespace App\Filament\Admin\Resources\Pedidos\Pages;

use App\Filament\Admin\Resources\Pedidos\PedidoResource;
use App\Models\Pedido;
use App\Models\TipoStatus;
use App\Models\User;
use App\Services\PedidoService;
use App\Services\ProfilePreviewService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Illuminate\Contracts\View\View;

class ListPedidos extends ListRecords
{
    protected static string $resource = PedidoResource::class;

    #[On('filament_tables::filter.applied')]
    #[On('filament_tables::filter.removed')]
    public function refreshBadges(): void
    {
        $this->dispatch('refreshComponent');
    }


    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Manutenção',
            'title' => $this->getStatusTitle(),
            'description' => 'Gerencie os pedidos de manutenção, acompanhe seus status e mantenha um histórico detalhado para cada um.',
        ]);
    }

    private function getStatusTitle(): string|HtmlString
    {
        /** @var User|null $user */
        $user = $this->usuarioEfetivo();

        if (! $user?->hasPermissionTo('Visualizar Pedidos por Status')) {
            return 'Pedidos';
        }

        return $this->renderActiveTabPill();
    }

    protected function getHeaderActions(): array
    {
        /** @var User|null $user */
        $user = $this->usuarioEfetivo();

        return [
            Actions\CreateAction::make()
                ->label('Novo Pedido'),

            Actions\Action::make('feedbacks')
                ->label('Feedbacks')
                ->icon('heroicon-o-star')
                ->visible(fn() => $user?->hasPermissionTo('Visualizar Feedback de Pedidos') ?? false)
                ->color('warning')
                ->url(fn() => route('filament.admin.pages.feedback-pedidos')),
        ];
    }

    protected function getDefaultTableSortColumn(): ?string
    {
        return 'updated_at';
    }

    protected function getDefaultTableSortDirection(): ?string
    {
        return 'desc';
    }

    public function getTabs(): array
    {
        /** @var User|null $user */
        $user = $this->usuarioEfetivo();

        if (! $user?->hasPermissionTo('Visualizar Pedidos por Status')) {
            return [];
        }

        $tableQuery = $this->getTableQuery();
        $service = app(PedidoService::class);

        $tabs = [
            'todos' => Tab::make('Todos')
                ->modifyQueryUsing(fn($query) => $query
                    ->where('is_pedido_adicional', false)
                    ->reorder()
                    ->orderByDesc('updated_at'))
                ->badge(fn() => (clone $tableQuery)->where('is_pedido_adicional', false)->count())
                ->extraAttributes([
                    'class' => $this->tabClassesForAll(),
                ]),
        ];

        $quantidadeAdicionais = (clone $tableQuery)
            ->where('is_pedido_adicional', true)
            ->count();

        $tabAdicionais = null;

        if ($quantidadeAdicionais > 0) {
            $tabAdicionais = Tab::make('Adicionais')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('is_pedido_adicional', true)
                    ->reorder()
                    ->orderByDesc('updated_at'))
                ->badge($quantidadeAdicionais)
                ->extraAttributes([
                    'class' => $this->tabClassesForAdditionals(),
                ]);
        }

        $adicionaisInseridos = false;

        $ordemStatus = [
            'Em Aberto',
            'Reaberto',
            'Em Análise',
            'Em Manutenção',
            'Encaminhado ao Setor',
            'Enviado para Empresa',
            'Cancelado',
            'Concluído',
        ];

        $statusIdsOrdenados = collect($ordemStatus)
            ->map(fn(string $nome) => $service->statusPorNome($nome))
            ->filter()
            ->pluck('id')
            ->all();

        $statuses = TipoStatus::query()
            ->where('ativo', true)
            ->get()
            ->sortBy(fn(TipoStatus $status): int => array_search($status->id, $statusIdsOrdenados, true) !== false
                ? array_search($status->id, $statusIdsOrdenados, true)
                : 999);
        $statusManutencao = $service->statusPorNome($ordemStatus[3]);

        foreach ($statuses as $status) {
            if ($service->statusPorNome('Pedido Adicional')?->is($status)) {
                continue;
            }

            $query = (clone $tableQuery)
                ->where('tipo_status_id', $status->id)
                ->where('is_pedido_adicional', false);

            $count = $query->count();

            if ($count === 0) {
                continue;
            }

            $tabs[(string) $status->id] = Tab::make($status->nome)
                ->modifyQueryUsing(function ($query) use ($status) {
                    return $query
                        ->where('tipo_status_id', $status->id)
                        ->where('is_pedido_adicional', false);
                })
                ->badge($count)
                ->extraAttributes([
                    'class' => $this->tabClassesForStatus($status),
                ]);

            if (
                $tabAdicionais
                && ! $adicionaisInseridos
                && $statusManutencao?->is($status)
            ) {
                $tabs['adicionais'] = $tabAdicionais;
                $adicionaisInseridos = true;
            }
        }

        if ($tabAdicionais && ! $adicionaisInseridos) {
            $tabs['adicionais'] = $tabAdicionais;
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): ?string
    {
        /** @var User|null $user */
        $user = $this->usuarioEfetivo();
        $service = app(PedidoService::class);
        $statusAberto = $service->statusPorNome('Em Aberto');

        if (! $statusAberto) {
            return 'todos';
        }

        $query = $service->queryTabela($user)
            ->where('tipo_status_id', $statusAberto->id)
            ->where('is_pedido_adicional', false);

        return $query->exists() ? (string) $statusAberto->id : 'todos';
    }

    public function getTitle(): string|HtmlString
    {
        /** @var User|null $user */
        $user = $this->usuarioEfetivo();

        if (! $user?->hasPermissionTo('Visualizar Pedidos por Status')) {
            return 'Pedidos';
        }

        return $this->renderActiveTabPill();
    }

    protected function getTableQuery(): Builder
    {
        /** @var User|null $user */
        $user = $this->usuarioEfetivo();
        $service = app(PedidoService::class);

        if (! $user?->hasPermissionTo('Visualizar Pedidos por Status')) {
            return $service->queryTabTodos($user)
                ->where('is_pedido_adicional', false);
        }

        if (request()->query('activeTab') === 'todos') {
            return $service->queryTabTodos($user);
        }

        return $service->queryTabela($user);
    }

    private function usuarioEfetivo(): ?User
    {
        return app(ProfilePreviewService::class)->effectiveUser();
    }

    private function renderActiveTabPill(): HtmlString
    {
        $presentation = $this->activeTabPresentation();

        return new HtmlString(
            view('filament.admin.resources.pedidos.partials.status-pill', [
                'label' => $presentation['label'],
                'class' => $presentation['pill_class'],
            ])->render()
        );
    }

    private function activeTabPresentation(): array
    {
        $activeTab = $this->activeTab;

        if (! $activeTab || $activeTab === 'todos') {
            return [
                'label' => 'Todos',
                'pill_class' => 'gi-status-pill gi-status-pill--todos',
                'tab_class' => $this->tabClassesForAll(),
            ];
        }

        if ($activeTab === 'adicionais') {
            return [
                'label' => 'Pedidos adicionais',
                'pill_class' => 'gi-status-pill gi-status-pill--adicionais',
                'tab_class' => $this->tabClassesForAdditionals(),
            ];
        }

        $status = TipoStatus::find($activeTab);

        if (! $status) {
            return [
                'label' => 'Pedidos',
                'pill_class' => 'gi-status-pill gi-status-pill--fallback',
                'tab_class' => 'gi-tab-pill gi-tab-pill--fallback',
            ];
        }

        return [
            'label' => $status->nome,
            'pill_class' => sprintf('gi-status-pill gi-status-pill--%s', $this->statusModifier($status)),
            'tab_class' => $this->tabClassesForStatus($status),
        ];
    }

    private function tabClassesForAll(): string
    {
        return 'gi-tab-pill gi-tab-pill--todos';
    }

    private function tabClassesForAdditionals(): string
    {
        return 'gi-tab-pill gi-tab-pill--adicionais';
    }

    private function tabClassesForStatus(TipoStatus $status): string
    {
        return sprintf('gi-tab-pill gi-tab-pill--%s', $this->statusModifier($status));
    }

    private function statusModifier(TipoStatus $status): string
    {
        return match (Str::lower(Str::ascii($status->nome))) {
            'em aberto' => 'em-aberto',
            'reaberto' => 'reaberto',
            'em analise' => 'em-analise',
            'em manutencao' => 'em-manutencao',
            'encaminhado ao setor' => 'encaminhado-ao-setor',
            'enviado para empresa' => 'enviado-para-empresa',
            'cancelado' => 'cancelado',
            'concluido' => 'concluido',
            default => 'fallback',
        };
    }
}
