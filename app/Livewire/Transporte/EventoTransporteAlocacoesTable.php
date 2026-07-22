<?php

namespace App\Livewire\Transporte;

use App\Models\EventoCalendario;
use App\Models\EventoCalendarioTransporteAlocacao;
use App\Models\Pessoa;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use App\Services\Dashboard\EventoCalendarioListQueryService;
use App\Services\Dashboard\EventoTransporteAlocacaoService;
use App\Services\ProfilePreviewService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Gate;

class EventoTransporteAlocacoesTable extends TableWidget
{
    protected string $view = 'livewire.transporte.evento-transporte-alocacoes-table';

    protected int|string|array $columnSpan = 'full';

    public int $eventoId;

    private ?EventoCalendario $eventoResolvido = null;

    public function mount(int $eventoId): void
    {
        $this->eventoId = $eventoId;

        $this->evento();
    }

    public function table(Table $table): Table
    {
        $user = $this->usuarioEfetivo();
        $evento = $this->evento();

        return $table
            ->query($this->service()->queryAtivas($user, $evento))
            ->heading('Veículos e motoristas')
            ->description('Acompanhe a capacidade disponível para este evento de transporte.')
            ->headerActions([
                CreateAction::make('adicionarVeiculo')
                    ->label('Adicionar veículo')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => $this->podeGerenciar())
                    ->authorize(fn (): bool => $this->podeGerenciar())
                    ->modalHeading('Adicionar veículo ao evento')
                    ->modalDescription('Escolha um veículo e o motorista responsável.')
                    ->modalWidth('lg')
                    ->closeModalByClickingAway(false)
                    ->schema([
                        Select::make('veiculo_id')
                            ->label('Veículo')
                            ->options(fn (): array => $this->service()->veiculoOptions(
                                $this->usuarioEfetivo(),
                                $this->evento(),
                            ))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('motorista_id')
                            ->label('Motorista')
                            ->options(fn (): array => $this->service()->motoristaOptions(
                                $this->usuarioEfetivo(),
                                $this->evento(),
                            ))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('turma_ids')
                            ->label('Turmas atendidas por este veículo')
                            ->options(fn (): array => $this->service()->turmaOptions(
                                $this->usuarioEfetivo(),
                                $this->evento(),
                            ))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Cada turma pode ser vinculada a somente um veículo neste evento.'),
                    ])
                    ->using(function (array $data): EventoCalendarioTransporteAlocacao {
                        return $this->service()->adicionar(
                            $this->usuarioEfetivo(),
                            $this->evento(),
                            (int) $data['veiculo_id'],
                            (int) $data['motorista_id'],
                            $data['turma_ids'] ?? [],
                        );
                    })
                    ->after(function (): void {
                        $this->resetTable();
                    })
                    ->successNotificationTitle('Veículo adicionado ao evento'),
            ])
            ->columns([
                TextColumn::make('veiculo.placa')
                    ->label('Veículo')
                    ->description(fn (EventoCalendarioTransporteAlocacao $record): string => $record->veiculo?->identificacao ?: 'Sem identificação')
                    ->weight('bold')
                    ->wrap(),
                TextColumn::make('veiculo.capacidade_passageiros')
                    ->label('Capacidade')
                    ->numeric(locale: 'pt_BR')
                    ->suffix(' lugares')
                    ->alignCenter(),
                TextColumn::make('motorista.nome')
                    ->label('Motorista')
                    ->placeholder('Não informado')
                    ->wrap(),
                TextColumn::make('turmas_resumo')
                    ->label('Turmas')
                    ->state(fn (EventoCalendarioTransporteAlocacao $record): array => $record->turmas
                        ->map(fn ($turma): string => trim(($turma->serie?->nome ? $turma->serie->nome.' ' : '').$turma->nome))
                        ->values()->all())
                    ->listWithLineBreaks()
                    ->wrap(),
                TextColumn::make('recursos_ativos')
                    ->label('Situação')
                    ->state(fn (EventoCalendarioTransporteAlocacao $record): string => $this->recursosAtivos($record)
                        ? 'Disponíveis'
                        : 'Recurso inativo')
                    ->badge()
                    ->color(fn (EventoCalendarioTransporteAlocacao $record): string => $this->recursosAtivos($record)
                        ? 'success'
                        : 'warning'),
            ])
            ->recordActions([
                Action::make('remover')
                    ->label('Remover')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (): bool => $this->podeGerenciar())
                    ->authorize(fn (): bool => $this->podeGerenciar())
                    ->requiresConfirmation()
                    ->modalHeading('Remover este veículo?')
                    ->modalDescription('A alocação será removida apenas deste evento.')
                    ->modalSubmitActionLabel('Remover')
                    ->action(function (EventoCalendarioTransporteAlocacao $record): void {
                        $this->service()->remover($this->usuarioEfetivo(), $record);
                        $this->resetTable();

                        Notification::make()
                            ->title('Veículo removido do evento')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('id')
            ->queryStringIdentifier('alocacoes-transporte-'.$this->eventoId)
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateHeading('Nenhum veículo alocado')
            ->emptyStateDescription($this->podeGerenciar()
                ? 'Adicione os veículos que atenderão este evento.'
                : 'Ainda não há veículos atribuídos a este evento.')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }

    /**
     * @return array{estudantes: int, capacidade: int, diferenca: int, capacidade_insuficiente: bool, possui_recursos_inativos: bool, alocacoes: \Illuminate\Support\Collection<int, EventoCalendarioTransporteAlocacao>}
     */
    public function resumo(): array
    {
        return $this->service()->resumo($this->usuarioEfetivo(), $this->evento());
    }

    private function evento(): EventoCalendario
    {
        if ($this->eventoResolvido instanceof EventoCalendario) {
            return $this->eventoResolvido;
        }

        $evento = app(EventoCalendarioListQueryService::class)->detalhes(
            $this->usuarioEfetivo(),
            $this->eventoId,
        );

        abort_unless($evento->possuiTransporte(), 404);

        return $this->eventoResolvido = $evento;
    }

    private function usuarioEfetivo(): User
    {
        $user = app(ProfilePreviewService::class)->effectiveUser();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function podeGerenciar(): bool
    {
        return Gate::forUser($this->usuarioEfetivo())->allows('manageTransport', $this->evento());
    }

    private function service(): EventoTransporteAlocacaoService
    {
        return app(EventoTransporteAlocacaoService::class);
    }

    private function recursosAtivos(EventoCalendarioTransporteAlocacao $alocacao): bool
    {
        return (bool) $alocacao->veiculo?->ativo
            && $alocacao->motorista?->status === Pessoa::STATUS_ATIVO
            && ($alocacao->motorista?->servidorFuncoes?->contains(
                'status',
                ServidorFuncaoAdministrativa::STATUS_ATIVO,
            ) ?? false);
    }
}
