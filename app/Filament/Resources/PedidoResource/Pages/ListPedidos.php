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

class ListPedidos extends ListRecords
{
    protected static string $resource = PedidoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [];

        /** @var \App\Models\User */
        $user = Auth::user();

        $service = app(PedidoService::class);

        // Query base respeitando permissão
        $baseQuery = $service->queryTabela($user);

        // =========================
        // TAB TODOS
        // =========================
        $tabs['todos'] = Tab::make('Todos')
            ->badge(fn() => (clone $baseQuery)->count())
            ->extraAttributes([
                'class' => 'tab-todos',
            ]);

        // =========================
        // TABS POR STATUS
        // =========================
        $statuses = TipoStatus::where('ativo', true)
            ->orderByRaw("nome = 'Em Aberto' DESC")
            ->orderBy('nome')
            ->get();

        foreach ($statuses as $status) {

            // Ocultar se não tiver permissão
            if (
                $status->nome === 'Encaminhado ao Setor' &&
                ! $user?->hasPermissionTo('Visualizar Status: Encaminhado ao Setor')
            ) {
                continue;
            }

            $hex = substr(ltrim($status->cor, '#'), 0, 6);

            $tabs[$status->id] = Tab::make($status->nome)
                ->modifyQueryUsing(
                    fn($query) => $query->where('tipo_status_id', $status->id)
                )
                ->badge(
                    fn() => (clone $baseQuery)
                        ->where('tipo_status_id', $status->id)
                        ->count()
                )
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
        return TipoStatus::where('nome', 'Em Aberto')
            ->value('id');
    }

    public function getTitle(): string|HtmlString
    {
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
}
