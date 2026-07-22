<?php

namespace App\Livewire\Transporte;

use App\Models\EventoCalendario;
use App\Models\Pessoa;
use App\Models\Servidor;
use App\Models\User;
use App\Services\Dashboard\MotoristaTransporteService;
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

class MotoristasTransporteTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function mount(): void
    {
        abort_unless($this->podeGerenciar(), 403);
    }

    public static function canView(): bool
    {
        $user = app(ProfilePreviewService::class)->effectiveUser();

        return $user instanceof User
            && Gate::forUser($user)->allows('manageTransport', EventoCalendario::class);
    }

    public function table(Table $table): Table
    {
        $user = $this->usuarioEfetivo();

        return $table
            ->query($this->service()->query($user))
            ->heading('Motoristas cadastrados')
            ->description('Pessoas disponíveis para serem vinculadas aos eventos de transporte.')
            ->headerActions([
                CreateAction::make('novoMotorista')
                    ->label('Novo motorista')
                    ->icon('heroicon-o-plus')
                    ->authorize(fn (): bool => $this->podeGerenciar())
                    ->modalHeading('Novo motorista')
                    ->modalDescription('Informe somente os dados necessários para identificar o motorista.')
                    ->modalWidth('lg')
                    ->closeModalByClickingAway(false)
                    ->schema($this->formSchema())
                    ->using(fn (array $data): Servidor => $this->service()->criar(
                        $this->usuarioEfetivo(),
                        $data,
                    ))
                    ->successNotificationTitle('Motorista cadastrado'),
            ])
            ->columns([
                TextColumn::make('nome')
                    ->label('Motorista')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap(),
                TextColumn::make('cpf')
                    ->label('CPF')
                    ->formatStateUsing(fn (?string $state): string => Pessoa::formatarCpf($state) ?? '—')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('telefone')
                    ->label('Telefone')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('motorista_ativo')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => (bool) $state ? 'Ativo' : 'Inativo')
                    ->color(fn ($state): string => (bool) $state ? 'success' : 'gray'),
                TextColumn::make('eventos_transporte_count')
                    ->label('Eventos em uso')
                    ->numeric(locale: 'pt_BR')
                    ->alignCenter(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make('editar')
                        ->label('Editar')
                        ->icon('heroicon-o-pencil-square')
                        ->authorize(fn (): bool => $this->podeGerenciar())
                        ->modalHeading('Editar motorista')
                        ->modalWidth('lg')
                        ->closeModalByClickingAway(false)
                        ->schema($this->formSchema())
                        ->fillForm(fn (Servidor $record): array => [
                            'nome' => $record->nome,
                            'cpf' => Pessoa::formatarCpf($record->cpf),
                            'telefone' => $record->telefone,
                        ])
                        ->using(fn (Servidor $record, array $data): Servidor => $this->service()->atualizar(
                            $this->usuarioEfetivo(),
                            $record,
                            $data,
                        ))
                        ->successNotificationTitle('Motorista atualizado'),
                    Action::make('desativar')
                        ->label('Desativar')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->visible(fn (Servidor $record): bool => (bool) $record->motorista_ativo)
                        ->authorize(fn (): bool => $this->podeGerenciar())
                        ->requiresConfirmation()
                        ->modalHeading('Desativar este motorista?')
                        ->modalDescription('Ele deixará de aparecer para novos vínculos, sem alterar o histórico dos eventos.')
                        ->modalSubmitActionLabel('Desativar')
                        ->action(function (Servidor $record): void {
                            $this->service()->desativar($this->usuarioEfetivo(), $record);

                            Notification::make()
                                ->title('Motorista desativado')
                                ->success()
                                ->send();
                        }),
                    Action::make('ativar')
                        ->label('Ativar')
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->visible(fn (Servidor $record): bool => ! (bool) $record->motorista_ativo)
                        ->authorize(fn (): bool => $this->podeGerenciar())
                        ->requiresConfirmation()
                        ->modalHeading('Ativar este motorista?')
                        ->modalSubmitActionLabel('Ativar')
                        ->action(function (Servidor $record): void {
                            $this->service()->ativar($this->usuarioEfetivo(), $record);

                            Notification::make()
                                ->title('Motorista ativado')
                                ->success()
                                ->send();
                        }),
                ])
                    ->label('Ações')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button(),
            ])
            ->defaultSort('nome')
            ->queryStringIdentifier('motoristas-transporte')
            ->searchPlaceholder('Buscar por nome, CPF ou telefone')
            ->emptyStateIcon('heroicon-o-identification')
            ->emptyStateHeading('Nenhum motorista cadastrado')
            ->emptyStateDescription('Cadastre um motorista para utilizá-lo nos eventos de transporte.')
            ->paginated([10, 25])
            ->defaultPaginationPageOption(10);
    }

    /** @return array<int, TextInput> */
    private function formSchema(): array
    {
        return [
            TextInput::make('nome')
                ->label('Nome')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            TextInput::make('cpf')
                ->label('CPF')
                ->mask('999.999.999-99')
                ->required()
                ->maxLength(14),
            TextInput::make('telefone')
                ->label('Telefone')
                ->tel()
                ->maxLength(255),
        ];
    }

    private function usuarioEfetivo(): User
    {
        $user = app(ProfilePreviewService::class)->effectiveUser();

        abort_unless($user instanceof User, 403);
        abort_unless(Gate::forUser($user)->allows('manageTransport', EventoCalendario::class), 403);

        return $user;
    }

    private function podeGerenciar(): bool
    {
        return static::canView();
    }

    private function service(): MotoristaTransporteService
    {
        return app(MotoristaTransporteService::class);
    }
}
