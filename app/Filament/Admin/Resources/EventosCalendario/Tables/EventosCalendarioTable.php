<?php

namespace App\Filament\Admin\Resources\EventosCalendario\Tables;

use App\Models\Enums\DashboardPrioridade;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\EventoCalendario;
use App\Models\User;
use App\Services\Dashboard\EventoCalendarioService;
use App\Services\Dashboard\PublicoAlvoOptionsService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                TextColumn::make('prioridade')->label('Prioridade')->badge()
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
                    ->label('Escolas')
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
                IconColumn::make('ativo')->label('Publicado')->boolean(),
                TextColumn::make('criadoPor.name')->label('Criado por')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('atualizadoPor.name')->label('Alterado por')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('ativo')->label('Publicação')
                    ->trueLabel('Publicados')->falseLabel('Não publicados')->placeholder('Todos'),
                SelectFilter::make('categoria')->label('Categoria')
                    ->options(collect(EventoCalendarioCategoria::cases())->mapWithKeys(
                        fn ($item): array => [$item->value => $item->label()],
                    )->all()),
                SelectFilter::make('prioridade')->label('Prioridade')->options(DashboardPrioridade::options()),
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
                SelectFilter::make('publico_alvo_tipo')->label('Público-alvo')
                    ->options(['todos' => 'Todos os usuários do escopo', 'segmentado' => 'Público segmentado'])
                    ->visible($user && Gate::forUser($user)->allows('manageAudience', EventoCalendario::class))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $filtered, string $value): Builder => $filtered->whereHas(
                                'publicoAlvo',
                                fn (Builder $publico): Builder => $publico->where('todos_usuarios', $value === 'todos'),
                            ),
                        );
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
                    ->modalHeading(fn (EventoCalendario $record): string => $record->titulo)
                    ->modalContent(fn (EventoCalendario $record) => view(
                        'filament.admin.resources.eventos-calendario.partials.detalhes',
                        ['evento' => $record],
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),
                EditAction::make(),
                Action::make('publicar')
                    ->label(fn (EventoCalendario $record): string => $record->ativo ? 'Desativar' : 'Publicar')
                    ->icon(fn (EventoCalendario $record): string => $record->ativo ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (EventoCalendario $record): string => $record->ativo ? 'gray' : 'success')
                    ->authorize(fn (EventoCalendario $record): bool => $user && Gate::forUser($user)->allows('publish', $record))
                    ->requiresConfirmation()
                    ->action(function (EventoCalendario $record) use ($user): void {
                        app(EventoCalendarioService::class)->alternarPublicacao($record, $user);
                        Notification::make()->title('Publicação atualizada')->success()->send();
                    }),
                Action::make('duplicar')
                    ->label('Duplicar')
                    ->icon('heroicon-o-square-2-stack')
                    ->authorize(fn (EventoCalendario $record): bool => $user && Gate::forUser($user)->allows('duplicate', $record))
                    ->action(function (EventoCalendario $record) use ($user): void {
                        app(EventoCalendarioService::class)->duplicar($record, $user);
                        Notification::make()->title('Evento duplicado como não publicado')->success()->send();
                    }),
                DeleteAction::make(),
            ])
            ->defaultSort('data_inicio')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
