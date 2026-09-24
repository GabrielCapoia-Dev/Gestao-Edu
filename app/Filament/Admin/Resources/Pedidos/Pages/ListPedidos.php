<?php

namespace App\Filament\Admin\Resources\Pedidos\Pages;

use App\Filament\Admin\Resources\Pedidos\PedidoResource;
use App\Filament\Admin\Resources\Pedidos\Tables\PedidosTable;
use App\Models\Pedido;
use App\Models\TipoStatus;
use App\Models\User;
use App\Services\PessoaScopeService;
use App\Services\PedidoService;
use App\Services\ProfilePreviewService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

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

        if (! Gate::forUser($user)->allows('viewByStatus', Pedido::class)) {
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
                ->visible(fn () => Gate::forUser($user)->allows('viewFeedback', Pedido::class))
                ->color('warning')
                ->url(fn () => route('filament.admin.pages.feedback-pedidos')),
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

    protected function applySortingToTableQuery(Builder $query): Builder
    {
        $user = $this->usuarioEfetivo();

        if (
            app(PessoaScopeService::class)->ehEquipeGestora($user)
            && (blank($this->activeTab) || $this->activeTab === 'todos')
        ) {
            return PedidosTable::ordenarParaEquipeGestora($query->reorder(), $user)
                ->orderByDesc('updated_at');
        }

        return parent::applySortingToTableQuery($query);
    }

    public function getTabs(): array
    {
        /** @var User|null $user */
        $user = $this->usuarioEfetivo();

        if (! Gate::forUser($user)->allows('viewByStatus', Pedido::class)) {
            return [];
        }

        $tableQuery = $this->getTableQuery();
        $service = app(PedidoService::class);

        $tabs = [
            'todos' => Tab::make('Todos')
                ->modifyQueryUsing(function (Builder $query) use ($user): Builder {
                    $query
                        ->where('is_pedido_adicional', false)
                        ->reorder();

                    return PedidosTable::ordenarParaEquipeGestora($query, $user)
                        ->orderByDesc('updated_at');
                })
                ->badge(fn () => (clone $tableQuery)->where('is_pedido_adicional', false)->count())
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
            ->map(fn (string $nome) => $service->statusPorNome($nome))
            ->filter()
            ->pluck('id')
            ->all();

        $statuses = TipoStatus::query()
            ->where('ativo', true)
            ->get()
            ->sortBy(fn (TipoStatus $status): int => array_search($status->id, $statusIdsOrdenados, true) !== false
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
                ->extraAttributes($this->tabAttributesForStatus($status));

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

        if (! Gate::forUser($user)->allows('viewByStatus', Pedido::class)) {
            return 'Pedidos';
        }

        return $this->renderActiveTabPill();
    }

    protected function getTableQuery(): Builder
    {
        /** @var User|null $user */
        $user = $this->usuarioEfetivo();
        $service = app(PedidoService::class);

        if (! Gate::forUser($user)->allows('viewByStatus', Pedido::class)) {
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
                'style' => $presentation['pill_style'],
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
                'pill_style' => null,
                'tab_class' => $this->tabClassesForAll(),
            ];
        }

        if ($activeTab === 'adicionais') {
            return [
                'label' => 'Pedidos adicionais',
                'pill_class' => 'gi-status-pill gi-status-pill--adicionais',
                'pill_style' => null,
                'tab_class' => $this->tabClassesForAdditionals(),
            ];
        }

        $status = TipoStatus::find($activeTab);

        if (! $status) {
            return [
                'label' => 'Pedidos',
                'pill_class' => 'gi-status-pill gi-status-pill--fallback',
                'pill_style' => null,
                'tab_class' => 'gi-tab-pill gi-tab-pill--fallback',
            ];
        }

        return [
            'label' => $status->nome,
            'pill_class' => 'gi-status-pill',
            'pill_style' => $this->statusPillStyle($status),
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
        $modifier = $this->statusModifier($status);

        return trim(sprintf(
            'gi-tab-pill %s',
            $modifier ? "gi-tab-pill--{$modifier}" : '',
        ));
    }

    private function tabAttributesForStatus(TipoStatus $status): array
    {
        $attributes = [
            'class' => $this->tabClassesForStatus($status),
        ];

        $style = $this->statusPillStyle($status);

        if ($style) {
            $attributes['style'] = $style;
        }

        return $attributes;
    }

    private function statusModifier(TipoStatus $status): string
    {
        $slug = Str::slug(Str::ascii($status->nome));

        return $slug !== '' ? $slug : 'fallback';
    }

    private function statusPillStyle(TipoStatus $status): ?string
    {
        $hex = $this->normalizeHexColor($status->cor);

        if (! $hex) {
            return null;
        }

        [$red, $green, $blue] = $this->hexToRgb($hex);

        return sprintf(
            'border-color: rgba(%d, %d, %d, 0.28); background: rgba(%d, %d, %d, 0.12); color: %s;',
            $red,
            $green,
            $blue,
            $red,
            $green,
            $blue,
            $hex,
        );
    }

    private function normalizeHexColor(?string $color): ?string
    {
        $value = trim((string) $color);

        if (! preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $value)) {
            return null;
        }

        if (strlen($value) === 4) {
            return sprintf(
                '#%s%s%s%s%s%s',
                $value[1],
                $value[1],
                $value[2],
                $value[2],
                $value[3],
                $value[3],
            );
        }

        return strtoupper($value);
    }

    private function hexToRgb(string $hex): array
    {
        $value = ltrim($hex, '#');

        return [
            hexdec(substr($value, 0, 2)),
            hexdec(substr($value, 2, 2)),
            hexdec(substr($value, 4, 2)),
        ];
    }
}
