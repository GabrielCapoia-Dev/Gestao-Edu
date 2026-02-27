<?php

namespace App\Filament\Resources\PedidoResource\Pages;

use App\Filament\Resources\PedidoResource;
use App\Models\Pedido;
use App\Models\TipoStatus;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;
use App\Services\PedidoService;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

class ListPedidos extends ListRecords
{
    protected static string $resource = PedidoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Novo Pedido'),
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

        // 🚫 Se não tiver permissão → sem tabs
        if (! $user?->hasPermissionTo('Visualizar Pedidos por Status')) {
            return [];
        }

        $service = app(PedidoService::class);

        // Query base respeitando permissão
        $baseQuery = $service->queryTabela($user);

        // =========================
        // TAB TODOS
        // =========================
        $tabs['todos'] = Tab::make('Todos')
            ->modifyQueryUsing(function ($query) {
                $query->reorder()->orderByDesc('updated_at');
            })
            ->badge(fn() => $service->queryTabTodos($user)->count());


        // =========================
        // TABS POR STATUS
        // =========================
        $prioridade = $user?->setor?->nome === 'Obras'
            ? 'Encaminhado ao Setor'
            : 'Em Aberto';

        $statuses = TipoStatus::where('ativo', true)
            ->orderByRaw("nome = ? DESC", [$prioridade])
            ->orderBy('nome')
            ->get();

        foreach ($statuses as $status) {

            // 🔵 Se usuário for Obras → ocultar tab Em Aberto
            if (
                $user?->setor?->nome === 'Obras' &&
                $status->nome === 'Em Aberto'
            ) {
                continue;
            }

            $hex = substr(ltrim($status->cor, '#'), 0, 6);

            $tabs[$status->id] = Tab::make($status->nome)

                ->modifyQueryUsing(function ($query) use ($status, $user) {

                    $query->where('tipo_status_id', $status->id);

                    // Em Aberto continua filtrando por setor
                    if (
                        $status->nome === 'Em Aberto' &&
                        filled($user?->setor_id)
                    ) {
                        $query->where('setor_id', $user->setor_id);
                    }

                    return $query;
                })

                ->badge(function () use ($baseQuery, $status, $user) {

                    $query = (clone $baseQuery)
                        ->where('tipo_status_id', $status->id);

                    if (
                        $status->nome === 'Em Aberto' &&
                        filled($user?->setor_id)
                    ) {
                        $query->where('setor_id', $user->setor_id);
                    }

                    return $query->count();
                })

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

        // Se usuário for Obras → default = Encaminhado ao Setor
        if ($user?->setor?->nome === 'Obras') {
            return TipoStatus::where('nome', 'Encaminhado ao Setor')
                ->value('id');
        }

        // Caso contrário → Em Aberto
        return TipoStatus::where('nome', 'Em Aberto')
            ->value('id');
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
