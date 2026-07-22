<?php

namespace App\Filament\Admin\Resources\EventosCalendario\Tables;

use App\Filament\Admin\Resources\EventosCalendario\Schemas\EventoCalendarioForm;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\Enums\EventoCalendarioStatus;
use App\Models\EventoCalendario;
use App\Models\User;
use App\Services\Dashboard\EventoCalendarioService;
use App\Services\Dashboard\EventoCalendarioWorkflowService;
use App\Services\Dashboard\PublicoAlvoOptionsService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EventosCalendarioTable
{
    public static function configure(Table $table, ?User $user): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')->label('Título')->searchable()->sortable()->wrap(),
                TextColumn::make('categoria')->label('Categoria')->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state),
                TextColumn::make('data_inicio')->label('Data e horário')
                    ->formatStateUsing(fn (EventoCalendario $record): string => sprintf(
                        '%s, %s–%s',
                        $record->data_inicio->format('d/m/Y'),
                        $record->data_inicio->format('H:i'),
                        $record->data_fim->format('H:i'),
                    ))
                    ->sortable(),
                TextColumn::make('distribuicao_escolas')
                    ->label('Distribuição')
                    ->getStateUsing(function (EventoCalendario $record): string {
                        if ($record->enviar_todas_escolas) {
                            return 'Todas as escolas do escopo';
                        }

                        return $record->escolasAgendadas
                            ->pluck('escola.nome')
                            ->filter()
                            ->join(', ');
                    })
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state)
                    ->color(fn ($state): string => $state?->color() ?? 'gray'),
                TextColumn::make('criadoPor.name')->label('Criado por')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('atualizadoPor.name')->label('Alterado por')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(EventoCalendarioStatus::cases())->mapWithKeys(
                        fn (EventoCalendarioStatus $status): array => [$status->value => $status->label()],
                    )->all()),
                SelectFilter::make('categoria')->label('Categoria')
                    ->options(collect(EventoCalendarioCategoria::cases())->mapWithKeys(
                        fn ($item): array => [$item->value => $item->label()],
                    )->all()),
                SelectFilter::make('escola_agendada_id')->label('Escola')
                    ->options(fn (): array => $user ? app(PublicoAlvoOptionsService::class)->escolas($user) : [])
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $escolaId = $data['value'] ?? null;

                        return $query->when($escolaId, fn (Builder $eventos): Builder => $eventos
                            ->where(function (Builder $distribuicao) use ($escolaId): void {
                                $distribuicao
                                    ->where('enviar_todas_escolas', true)
                                    ->orWhereHas(
                                        'escolasAgendadas',
                                        fn (Builder $escolas): Builder => $escolas->where('escola_id', $escolaId),
                                    );
                            }));
                    }),
                Filter::make('periodo')->label('Período')
                    ->schema([
                        DatePicker::make('inicio')->label('De'),
                        DatePicker::make('fim')->label('Até'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['inicio'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('data_fim', '>=', $date))
                        ->when($data['fim'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('data_inicio', '<=', $date))),
                Filter::make('realizados')->label('Realizados')
                    ->query(fn (Builder $query): Builder => $query->where('data_fim', '<', now())),
            ])
            ->recordActions([
                Action::make('detalhes')
                    ->label('Detalhes')
                    ->icon('heroicon-o-eye')
                    ->authorize(fn (EventoCalendario $record): bool => $user && Gate::forUser($user)->allows('view', $record))
                    ->modalHeading(fn (EventoCalendario $record): string => $record->titulo)
                    ->modalContent(fn (EventoCalendario $record) => view(
                        'filament.admin.resources.eventos-calendario.partials.detalhes',
                        ['evento' => $record],
                    ))
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),
                EditAction::make()
                    ->label('Editar')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->closeModalByClickingAway(false)
                    ->schema(fn (): array => EventoCalendarioForm::components($user))
                    ->fillForm(fn (EventoCalendario $record): array => EventoCalendarioForm::dadosParaEdicao(
                        $record,
                        $record->attributesToArray(),
                    ))
                    ->authorize(fn (EventoCalendario $record): bool => $user && Gate::forUser($user)->allows('update', $record))
                    ->using(function (EventoCalendario $record, array $data) use ($user): EventoCalendario {
                        abort_unless($user, 403);

                        return app(EventoCalendarioService::class)->atualizar($record, $data, [], $user);
                    })
                    ->successNotificationTitle('Evento atualizado'),
                Action::make('publicar')
                    ->label('Publicar')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn (EventoCalendario $record): bool => $record->status !== EventoCalendarioStatus::PUBLICADO
                        && $user
                        && Gate::forUser($user)->allows('publish', $record))
                    ->authorize(fn (EventoCalendario $record): bool => $user && Gate::forUser($user)->allows('publish', $record))
                    ->requiresConfirmation()
                    ->action(function (EventoCalendario $record) use ($user): void {
                        abort_unless($user, 403);
                        app(EventoCalendarioWorkflowService::class)->publicar($record, $user);
                        Notification::make()->title('Evento publicado')->success()->send();
                    }),
                Action::make('desativar')
                    ->label('Desativar')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (EventoCalendario $record): bool => $record->status === EventoCalendarioStatus::PUBLICADO
                        && $user
                        && Gate::forUser($user)->allows('deactivate', $record))
                    ->authorize(fn (EventoCalendario $record): bool => $user && Gate::forUser($user)->allows('deactivate', $record))
                    ->requiresConfirmation()
                    ->action(function (EventoCalendario $record) use ($user): void {
                        abort_unless($user, 403);
                        app(EventoCalendarioWorkflowService::class)->desativar($record, $user);
                        Notification::make()->title('Evento desativado')->success()->send();
                    }),
                Action::make('rejeitar')
                    ->label('Rejeitar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (EventoCalendario $record): bool => $record->status === EventoCalendarioStatus::PENDENTE_APROVACAO
                        && $user
                        && Gate::forUser($user)->allows('reject', $record))
                    ->authorize(fn (EventoCalendario $record): bool => $user && Gate::forUser($user)->allows('reject', $record))
                    ->modalHeading('Rejeitar evento de transporte')
                    ->modalDescription('O evento permanecerá registrado e não será exibido na agenda.')
                    ->modalSubmitActionLabel('Rejeitar evento')
                    ->schema([
                        Textarea::make('motivo')
                            ->label('Motivo da rejeição')
                            ->helperText('Opcional. Informe uma justificativa para registrar a decisão.')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->action(function (EventoCalendario $record, array $data) use ($user): void {
                        abort_unless($user, 403);
                        app(EventoCalendarioWorkflowService::class)->rejeitar(
                            $record,
                            $user,
                            filled($data['motivo'] ?? null) ? trim((string) $data['motivo']) : null,
                        );
                        Notification::make()->title('Evento rejeitado')->success()->send();
                    }),
            ])
            ->groupedBulkActions([
                BulkAction::make('publicar')
                    ->label('Publicar selecionados')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn (): bool => $user && Gate::forUser($user)->allows('publish', EventoCalendario::class))
                    ->requiresConfirmation()
                    ->modalHeading('Publicar eventos selecionados')
                    ->modalDescription('Todos os eventos selecionados precisam estar dentro do seu escopo de publicação.')
                    ->action(function ($records) use ($user): void {
                        abort_unless($user, 403);

                        foreach ($records as $record) {
                            Gate::forUser($user)->authorize('publish', $record);
                        }

                        DB::transaction(function () use ($records, $user): void {
                            foreach ($records as $record) {
                                app(EventoCalendarioWorkflowService::class)->publicar($record, $user);
                            }
                        });

                        Notification::make()
                            ->title("{$records->count()} evento(s) publicado(s)")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('desativar')
                    ->label('Desativar selecionados')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (): bool => $user && Gate::forUser($user)->allows('deactivate', EventoCalendario::class))
                    ->requiresConfirmation()
                    ->modalHeading('Desativar eventos selecionados')
                    ->modalDescription('Os eventos selecionados deixarão de aparecer na agenda. Esta ação não exclui nenhum registro.')
                    ->action(function ($records) use ($user): void {
                        abort_unless($user, 403);

                        foreach ($records as $record) {
                            Gate::forUser($user)->authorize('deactivate', $record);
                        }

                        DB::transaction(function () use ($records, $user): void {
                            foreach ($records as $record) {
                                app(EventoCalendarioWorkflowService::class)->desativar($record, $user);
                            }
                        });

                        Notification::make()
                            ->title("{$records->count()} evento(s) desativado(s)")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('data_inicio')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
