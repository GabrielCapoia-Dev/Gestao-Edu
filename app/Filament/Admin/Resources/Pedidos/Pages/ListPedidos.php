<?php

namespace App\Filament\Admin\Resources\Pedidos\Pages;

use App\Filament\Admin\Resources\Pedidos\PedidoResource;
use App\Models\Pedido;
use App\Models\TipoStatus;
use App\Models\User;
use App\Services\PedidoService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
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
        $user = Auth::user();

        if (! $user?->hasPermissionTo('Visualizar Pedidos por Status')) {
            return 'Pedidos';
        }

        $activeTab = $this->activeTab;

        if (! $activeTab || $activeTab === 'todos') {
            return new HtmlString(
                '<span style="
                display:inline-block;
                padding:2px 10px;
                border-radius:5px;
                font-weight:600;
                line-height:1.6;
                background-color:#e5e7eb;
                color:#374151;
                border:1px solid #d1d5db;
            ">Todos</span>'
            );
        }

        $status = TipoStatus::find($activeTab);

        if (! $status) {
            return 'Pedidos';
        }

        $hex = '#' . ltrim($status->cor, '#');

        return new HtmlString(
            '<span style="'
                . 'display:inline-block;'
                . 'padding:2px 10px;'
                . 'border-radius:5px;'
                . 'font-weight:600;'
                . 'line-height:1.6;'
                . "background-color:{$hex}20;"
                . "color:{$hex};"
                . "border:1px solid {$hex}50;"
                . '">' . e($status->nome) . '</span>'
        );
    }

    protected function getHeaderActions(): array
    {
        /** @var User|null $user */
        $user = Auth::user();

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
        $user = Auth::user();

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
                    'style' => '
                        --tab-color: #6b7280;
                        background-color: #e5e7eb;
                        border: 1px solid #d1d5db;
                    ',
                ]),
        ];

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

            $hex = substr(ltrim($status->cor, '#'), 0, 6);

            $tabs[(string) $status->id] = Tab::make($status->nome)
                ->modifyQueryUsing(function ($query) use ($status) {
                    return $query
                        ->where('tipo_status_id', $status->id)
                        ->where('is_pedido_adicional', false);
                })
                ->badge($count)
                ->extraAttributes([
                    'style' => "
                        --tab-color: #{$hex};
                        background-color: #{$hex}20;
                        border: 1px solid #{$hex}50;
                    ",
                ]);
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): ?string
    {
        /** @var User|null $user */
        $user = Auth::user();
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
        $user = Auth::user();

        if (! $user?->hasPermissionTo('Visualizar Pedidos por Status')) {
            return 'Pedidos';
        }

        $activeTab = $this->activeTab;

        if (! $activeTab || $activeTab === 'todos') {
            return new HtmlString(
                '<span style="
                    display:inline-block;
                    padding:2px 10px;
                    border-radius:5px;
                    font-weight:600;
                    line-height:1.6;
                    background-color:#e5e7eb;
                    color:#374151;
                    border:1px solid #d1d5db">Todos</span>'
            );
        }

        $status = TipoStatus::find($activeTab);

        if (! $status) {
            return 'Pedidos';
        }

        $hex = '#' . ltrim($status->cor, '#');

        return new HtmlString(
            '<span style="'
                . 'display:inline-block;'
                . 'padding:2px 10px;'
                . 'border-radius:5px;'
                . 'font-weight:600;'
                . 'line-height:1.6;'
                . "background-color:{$hex}20;"
                . "color:{$hex};"
                . "border:1px solid {$hex}50;"
                . '">' . e($status->nome) . '</span>'
        );
    }

    protected function getTableQuery(): Builder
    {
        /** @var User|null $user */
        $user = Auth::user();
        $service = app(PedidoService::class);

        if (! $user?->hasPermissionTo('Visualizar Pedidos por Status')) {
            return $service->queryTabTodos($user);
        }

        if (request()->query('activeTab') === 'todos') {
            return $service->queryTabTodos($user);
        }

        return $service->queryTabela($user);
    }
}
