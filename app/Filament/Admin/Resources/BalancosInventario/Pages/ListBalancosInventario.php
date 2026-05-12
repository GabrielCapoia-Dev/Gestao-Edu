<?php

namespace App\Filament\Admin\Resources\BalancosInventario\Pages;

use App\Filament\Admin\Resources\BalancosInventario\BalancoInventarioResource;
use App\Models\Inventario;
use App\Services\Inventario\BalancoInventarioService;
use App\Services\Inventario\InventarioContextService;
use DomainException;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListBalancosInventario extends ListRecords
{
    protected static string $resource = BalancoInventarioResource::class;

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.resources.turmas.pages.manage-turmas-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Alimentação Escolar',
            'title' => 'Balanços de Inventário',
            'description' => 'Gerencie os balanços de inventário, agende novos balanços e mantenha um registro atualizado das informações.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Agendar Balanço')
                ->modalHeading('Agendar balanço de inventário')
                ->modalSubmitActionLabel('Agendar')
                ->createAnother(false)
                ->schema($this->schemaAgendamento())
                ->using(function (array $data) {
                    return $this->service()->agendar(
                        $this->resolverInventarioSelecionado($data),
                        $data,
                        Auth::user(),
                    );
                })
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Balanço agendado com sucesso.')
                ),
        ];
    }

    protected function schemaAgendamento(): array
    {
        $ehGestorGeral = $this->contextService()->ehGestorGeral(Auth::user());

        return [
            Select::make('inventario_id')
                ->label('Inventário')
                ->options(BalancoInventarioResource::opcoesInventario())
                ->searchable()
                ->preload()
                ->required($ehGestorGeral)
                ->visible($ehGestorGeral),
            DateTimePicker::make('data_agendada')
                ->label('Data agendada')
                ->required()
                ->seconds(false)
                ->native(false),
            Textarea::make('observacao_inicial')
                ->label('Observação inicial')
                ->rows(4)
                ->maxLength(1500),
        ];
    }

    protected function resolverInventarioSelecionado(array $data): Inventario
    {
        $user = Auth::user();

        if ($this->contextService()->ehGestorGeral($user)) {
            $inventario = $this->contextService()
                ->queryInventariosVisiveis($user)
                ->find((int) ($data['inventario_id'] ?? 0));

            if (! $inventario) {
                throw new DomainException('Selecione um inventário válido para o agendamento.');
            }

            return $inventario;
        }

        $inventario = $this->contextService()->inventarioDoUsuario($user);

        if (! $inventario) {
            throw new DomainException('O usuário não possui inventário escolar disponível.');
        }

        return $inventario;
    }

    protected function service(): BalancoInventarioService
    {
        return app(BalancoInventarioService::class);
    }

    protected function contextService(): InventarioContextService
    {
        return app(InventarioContextService::class);
    }
}
