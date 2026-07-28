<?php

namespace App\Filament\Admin\Resources\ReservasVeiculos\Tables;

use App\Filament\Admin\Resources\ReservasVeiculos\Schemas\ReservaVeiculoForm;
use App\Models\Enums\ReservaVeiculoStatus;
use App\Models\ReservaVeiculo;
use App\Models\User;
use App\Services\Dashboard\ReservaVeiculoService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class ReservasVeiculosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('veiculo.cor')
                    ->label('Cor')
                    ->alignCenter(),

                TextColumn::make('data_inicio')
                    ->label('Data e horário')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (ReservaVeiculo $record): string => 'até '.$record->data_fim->format('H:i'))
                    ->sortable(),

                TextColumn::make('veiculo.identificacao')
                    ->label('Veículo')
                    ->formatStateUsing(fn (?string $state, ReservaVeiculo $record): string => $state ?: $record->veiculo?->placa ?: 'Não informado')
                    ->description(fn (ReservaVeiculo $record): ?string => $record->veiculo?->identificacao
                        ? $record->veiculo?->placa
                        : null)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('usuario.name')
                    ->label('Servidor')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('local_nome')
                    ->label('Destino')
                    ->description(fn (ReservaVeiculo $record): string => $record->escola_id
                        ? 'Escola ou CMEI'
                        : 'Outro local')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('atividade')
                    ->label('Atividade')
                    ->limit(90)
                    ->tooltip(fn (ReservaVeiculo $record): string => $record->atividade)
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ReservaVeiculoStatus $state): string => $state->label())
                    ->color(fn (ReservaVeiculoStatus $state): string => match ($state) {
                        ReservaVeiculoStatus::ATIVA => 'success',
                        ReservaVeiculoStatus::CONCLUIDA => 'info',
                        ReservaVeiculoStatus::CANCELADA => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        ReservaVeiculoStatus::ATIVA->value => ReservaVeiculoStatus::ATIVA->label(),
                        ReservaVeiculoStatus::CONCLUIDA->value => ReservaVeiculoStatus::CONCLUIDA->label(),
                        ReservaVeiculoStatus::CANCELADA->value => ReservaVeiculoStatus::CANCELADA->label(),
                    ])
                    ->default(ReservaVeiculoStatus::ATIVA->value),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make('editar')
                        ->label('Editar')
                        ->visible(fn (ReservaVeiculo $record): bool => Gate::allows('update', $record))
                        ->authorize(fn (ReservaVeiculo $record): bool => Gate::allows('update', $record))
                        ->modalHeading('Editar reserva')
                        ->modalDescription('A alteração afeta somente esta data, mesmo quando a reserva foi criada para vários dias.')
                        ->modalWidth('3xl')
                        ->closeModalByClickingAway(false)
                        ->fillForm(fn (ReservaVeiculo $record): array => [
                            'data' => $record->data_inicio->format('Y-m-d'),
                            'hora_inicio' => $record->data_inicio->format('H:i'),
                            'hora_fim' => $record->data_fim->format('H:i'),
                            'tipo_local' => $record->escola_id ? 'escola' : 'outros',
                            'escola_id' => $record->escola_id,
                            'local_outro' => $record->escola_id ? null : $record->local_nome,
                            'atividade' => $record->atividade,
                            'veiculo_transporte_id' => $record->veiculo_transporte_id,
                        ])
                        ->schema(fn (ReservaVeiculo $record): array => ReservaVeiculoForm::edicao(
                            self::usuario(),
                            $record,
                        ))
                        ->using(fn (ReservaVeiculo $record, array $data): ReservaVeiculo => app(ReservaVeiculoService::class)
                            ->atualizar(self::usuario(), $record, $data))
                        ->successNotificationTitle('Reserva atualizada'),

                    Action::make('cancelar')
                        ->label('Cancelar')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (ReservaVeiculo $record): bool => Gate::allows('cancel', $record))
                        ->authorize(fn (ReservaVeiculo $record): bool => Gate::allows('cancel', $record))
                        ->requiresConfirmation()
                        ->modalHeading('Cancelar esta reserva?')
                        ->modalDescription('O horário será liberado para uma nova reserva. O registro continuará disponível no histórico.')
                        ->modalSubmitActionLabel('Cancelar reserva')
                        ->schema([
                            Textarea::make('motivo')
                                ->label('Motivo do cancelamento')
                                ->maxLength(500)
                                ->rows(3),
                        ])
                        ->action(function (ReservaVeiculo $record, array $data): void {
                            app(ReservaVeiculoService::class)->cancelar(
                                self::usuario(),
                                $record,
                                $data['motivo'] ?? null,
                            );

                            Notification::make()
                                ->title('Reserva cancelada')
                                ->success()
                                ->send();
                        }),
                ])
                    ->label('Ações')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button(),
            ])
            ->defaultSort('data_inicio', 'desc')
            ->searchPlaceholder('Buscar por veículo, servidor, destino ou atividade')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->emptyStateHeading('Nenhuma reserva encontrada')
            ->emptyStateDescription('Crie a primeira reserva para disponibilizá-la na agenda.')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25);
    }

    private static function usuario(): User
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }
}
