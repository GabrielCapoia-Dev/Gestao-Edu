<?php

namespace App\Livewire\Transporte;

use App\Models\User;
use App\Models\VeiculoTransporte;
use App\Services\Dashboard\VeiculoTransporteService;
use App\Services\ProfilePreviewService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Gate;

class VeiculosTransporteTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function mount(): void
    {
        abort_unless($this->podeListar(), 403);
    }

    public static function canView(): bool
    {
        $user = app(ProfilePreviewService::class)->effectiveUser();

        return $user instanceof User
            && Gate::forUser($user)->allows('viewAny', VeiculoTransporte::class);
    }

    public function table(Table $table): Table
    {
        $user = $this->usuarioEfetivo();

        return $table
            ->query($this->service()->query($user, null))
            ->heading('Veículos cadastrados')
            ->description('Frota disponível para as reservas da Assessoria Pedagógica.')
            ->headerActions([
                CreateAction::make('novoVeiculo')
                    ->label('Novo veículo')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => $this->podeCriar())
                    ->authorize(fn (): bool => $this->podeCriar())
                    ->modalHeading('Novo veículo')
                    ->modalDescription('Informe a placa e uma identificação para o veículo.')
                    ->modalWidth('lg')
                    ->closeModalByClickingAway(false)
                    ->schema($this->formSchema())
                    ->using(fn (array $data): VeiculoTransporte => $this->service()->criar(
                        $this->usuarioEfetivo(),
                        $data,
                    ))
                    ->successNotificationTitle('Veículo cadastrado'),
            ])
            ->columns([
                TextColumn::make('placa')
                    ->label('Placa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('identificacao')
                    ->label('Identificação')
                    ->searchable()
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('ativo')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Ativo' : 'Inativo')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('reservas_ativas_count')
                    ->label('Próximas reservas')
                    ->numeric(locale: 'pt_BR')
                    ->alignCenter(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make('editar')
                        ->label('Editar')
                        ->icon('heroicon-o-pencil-square')
                        ->visible(fn (VeiculoTransporte $record): bool => $this->podeAtualizar($record))
                        ->authorize(fn (VeiculoTransporte $record): bool => $this->podeAtualizar($record))
                        ->modalHeading('Editar veículo')
                        ->modalWidth('lg')
                        ->closeModalByClickingAway(false)
                        ->schema($this->formSchema())
                        ->using(fn (VeiculoTransporte $record, array $data): VeiculoTransporte => $this->service()->atualizar(
                            $this->usuarioEfetivo(),
                            $record,
                            $data,
                        ))
                        ->successNotificationTitle('Veículo atualizado'),
                    Action::make('desativar')
                        ->label('Desativar')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->visible(fn (VeiculoTransporte $record): bool => $record->ativo
                            && $this->podeDesativar($record))
                        ->authorize(fn (VeiculoTransporte $record): bool => $this->podeDesativar($record))
                        ->requiresConfirmation()
                        ->modalHeading('Desativar este veículo?')
                        ->modalDescription('Ele deixará de aparecer para novas reservas, sem alterar o histórico existente.')
                        ->modalSubmitActionLabel('Desativar')
                        ->action(function (VeiculoTransporte $record): void {
                            try {
                                $this->service()->desativar($this->usuarioEfetivo(), $record);
                            } catch (\DomainException $exception) {
                                Notification::make()
                                    ->title('Não foi possível desativar o veículo')
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Veículo desativado')
                                ->success()
                                ->send();
                        }),
                    Action::make('ativar')
                        ->label('Ativar')
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->visible(fn (VeiculoTransporte $record): bool => ! $record->ativo
                            && $this->podeAtivar($record))
                        ->authorize(fn (VeiculoTransporte $record): bool => $this->podeAtivar($record))
                        ->requiresConfirmation()
                        ->modalHeading('Ativar este veículo?')
                        ->modalSubmitActionLabel('Ativar')
                        ->action(function (VeiculoTransporte $record): void {
                            $this->service()->ativar($this->usuarioEfetivo(), $record);

                            Notification::make()
                                ->title('Veículo ativado')
                                ->success()
                                ->send();
                        }),
                ])
                    ->label('Ações')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button(),
            ])
            ->defaultSort('placa')
            ->queryStringIdentifier('veiculos-transporte')
            ->searchPlaceholder('Buscar por placa ou identificação')
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateHeading('Nenhum veículo cadastrado')
            ->emptyStateDescription('Cadastre um veículo para disponibilizá-lo nas reservas.')
            ->paginated([10, 25])
            ->defaultPaginationPageOption(10);
    }

    /** @return array<int, TextInput> */
    private function formSchema(): array
    {
        return [
            TextInput::make('placa')
                ->label('Placa')
                ->required()
                ->maxLength(7)
                ->placeholder('ABC1D23')
                ->helperText('Informe somente letras e números.'),
            TextInput::make('identificacao')
                ->label('Identificação')
                ->placeholder('Ex.: Micro-ônibus 01')
                ->maxLength(120)
                ->columnSpanFull(),
        ];
    }

    private function usuarioEfetivo(): User
    {
        $user = app(ProfilePreviewService::class)->effectiveUser();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function podeListar(): bool
    {
        return static::canView();
    }

    private function podeCriar(): bool
    {
        $user = $this->usuarioEfetivo();

        return Gate::forUser($user)->allows('create', VeiculoTransporte::class);
    }

    private function podeAtualizar(VeiculoTransporte $record): bool
    {
        return Gate::forUser($this->usuarioEfetivo())->allows('update', $record);
    }

    private function podeAtivar(VeiculoTransporte $record): bool
    {
        return Gate::forUser($this->usuarioEfetivo())->allows('activate', $record);
    }

    private function podeDesativar(VeiculoTransporte $record): bool
    {
        return Gate::forUser($this->usuarioEfetivo())->allows('deactivate', $record);
    }

    private function service(): VeiculoTransporteService
    {
        return app(VeiculoTransporteService::class);
    }
}
