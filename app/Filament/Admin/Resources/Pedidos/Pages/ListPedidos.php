<?php

namespace App\Filament\Admin\Resources\Pedidos\Pages;

use App\Filament\Admin\Resources\Pedidos\PedidoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Models\Pedido;
use App\Models\TipoStatus;
use Filament\Actions;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;
use App\Services\PedidoService;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use Livewire\Attributes\On;

class ListPedidos extends ListRecords
{
    protected static string $resource = PedidoResource::class;

    /**
     * Listeners para atualizar badges quando filtros são aplicados/removidos
     */
    #[On('filament_tables::filter.applied')]
    #[On('filament_tables::filter.removed')]
    public function refreshBadges(): void
    {
        // Força recalcular os tabs quando filtro muda
        $this->dispatch('refreshComponent');
    }

    protected function getHeaderActions(): array
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        return [
            Actions\CreateAction::make()
                ->label('Novo Pedido'),

            Actions\Action::make('feedbacks')
                ->label('Feedbacks')
                ->icon('heroicon-o-star')
                ->visible(fn() => $user->hasPermissionTo('Visualizar Feedback de Pedidos'))
                ->color('warning')
                ->url(fn() => route('filament.admin.pages.feedback-pedidos'))
        ];
    }

    protected function getDefaultTableSortColumn(): ?string
    {
        return 'created_at';
    }

    protected function getDefaultTableSortDirection(): ?string
    {
        return 'desc';
    }

    public function getTabs(): array
    {
        $tabs = [];

        /** @var \App\Models\User */
        $user = Auth::user();

        if (! $user?->hasPermissionTo('Visualizar Pedidos por Status')) {
            return [];
        }

        $tableQuery = $this->getTableQuery();

        // =========================
        // TAB TODOS
        // =========================
        $tabs['todos'] = Tab::make('Todos')
            ->modifyQueryUsing(function ($query) {
                $query->reorder()->orderByDesc('updated_at');
            })
            ->badge(fn() => (clone $tableQuery)->count())
            ->extraAttributes([
                'style' => "
            --tab-color: #6b7280;
            background-color: #e5e7eb;
            border: 1px solid #d1d5db;
        ",
            ]);
        // =========================
        // TABS POR STATUS
        // =========================
        $prioridade = $user?->setor?->nome === 'Obras'
            ? 'Encaminhado ao Setor'
            : 'Em Aberto';
            

        $ordemStatus = [
            'Em Aberto',
            'Reaberto',
            'Em Análise',
            'Encaminhado ao Setor',
            'Enviado para Empresa',
            'Em Andamento',
            'Em Manutenção',
            'Cancelado',
            'Concluído',
        ];

        $statuses = TipoStatus::where('ativo', true)
            ->get()
            ->sortBy(
                fn($s) => array_search($s->nome, $ordemStatus) !== false
                    ? array_search($s->nome, $ordemStatus)
                    : 999
            );

        foreach ($statuses as $status) {

            if (
                $user?->setor?->nome === 'Obras' &&
                $status->nome === 'Em Aberto'
            ) {
                continue;
            }

            // 🔴 Contar com base na query com FILTROS
            $query = (clone $tableQuery)
                ->where('tipo_status_id', $status->id);

            if (
                $status->nome === 'Em Aberto' &&
                filled($user?->setor_id)
            ) {
                $query->where('setor_id', $user->setor_id);
            }

            $count = $query->count();

            // 🔴 PULAR TABS COM ZERO
            if ($count === 0) {
                continue;
            }

            $hex = substr(ltrim($status->cor, '#'), 0, 6);

            $tabs[$status->id] = Tab::make($status->nome)
                ->modifyQueryUsing(function ($query) use ($status, $user) {
                    $query->where('tipo_status_id', $status->id);

                    if (
                        $status->nome === 'Em Aberto' &&
                        filled($user?->setor_id)
                    ) {
                        $query->where('setor_id', $user->setor_id);
                    }

                    return $query;
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
        $user = Auth::user();

        if ($user?->setor?->nome === 'Obras') {
            $id = TipoStatus::where('nome', 'Encaminhado ao Setor')->value('id');

            $tem = Pedido::where('ativo', true)
                ->where('tipo_status_id', $id)
                ->exists();

            return $tem ? (string) $id : 'todos';
        }

        $id = TipoStatus::where('nome', 'Em Aberto')->value('id');

        if (!$id) {
            return 'todos';
        }

        $query = Pedido::where('ativo', true)
            ->where('tipo_status_id', $id);

        if (filled($user?->setor_id)) {
            $query->where('setor_id', $user->setor_id);
        }

        return $query->exists() ? (string) $id : 'todos';
    }
    public function getTitle(): string|HtmlString
    {
        /** @var \App\Models\User */
        $user = Auth::user();

        if (! $user?->hasPermissionTo('Visualizar Pedidos por Status')) {
            return 'Pedidos';
        }

        $activeTab = $this->activeTab;

        if (!$activeTab || $activeTab === 'todos') {
            $label = $activeTab === 'todos' ? 'Todos' : null;
            return $label
                ? new HtmlString(
                    '<span style="
                            display:inline-block;
                            padding:2px 10px;
                            border-radius:5px;
                            font-weight:600;
                            line-height:1.6;
                            background-color:#e5e7eb;
                            color:#374151;
                            border:1px solid #d1d5db">' . $label . '</span>'
                )
                : 'Pedidos';
        }

        $status = TipoStatus::find($activeTab);

        if (!$status) {
            return 'Pedidos';
        }

        $hex = '#' . ltrim($status->cor, '#');

        return new HtmlString(
            '<span style="'
                . "display:inline-block;"
                . "padding:2px 10px;"
                . "border-radius:5px;"
                . "font-weight:600;"
                . "line-height:1.6;"
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
