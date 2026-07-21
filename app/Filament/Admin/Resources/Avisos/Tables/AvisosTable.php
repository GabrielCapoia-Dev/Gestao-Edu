<?php

namespace App\Filament\Admin\Resources\Avisos\Tables;

use App\Models\Aviso;
use App\Models\Enums\DashboardPrioridade;
use App\Models\User;
use App\Services\Dashboard\AvisoService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class AvisosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->label('Aviso')
                    ->description(fn (Aviso $record): string => (string) str($record->descricao)->limit(90))
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('status_exibicao')
                    ->label('Status')
                    ->state(fn (Aviso $record): string => $record->statusExibicao())
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ativo' => 'Ativo',
                        'agendado' => 'Agendado',
                        'expirado' => 'Expirado',
                        default => 'Inativo',
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ativo' => 'success',
                        'agendado' => 'info',
                        'expirado' => 'gray',
                        default => 'warning',
                    }),

                TextColumn::make('prioridade')
                    ->label('Prioridade')
                    ->formatStateUsing(fn (DashboardPrioridade|string|null $state): string => $state instanceof DashboardPrioridade
                        ? $state->label()
                        : (DashboardPrioridade::tryFrom((string) $state)?->label() ?? 'Normal'))
                    ->badge()
                    ->color(fn (DashboardPrioridade|string|null $state): string => match ($state instanceof DashboardPrioridade ? $state : DashboardPrioridade::tryFrom((string) $state)) {
                        DashboardPrioridade::Urgente => 'danger',
                        DashboardPrioridade::Alta => 'warning',
                        DashboardPrioridade::Baixa => 'gray',
                        default => 'info',
                    })
                    ->sortable(),

                TextColumn::make('publico_alvo_resumo')
                    ->label('Público-alvo')
                    ->state(fn (Aviso $record): string => self::resumoPublicoAlvo($record))
                    ->wrap(),

                TextColumn::make('inicio_exibicao')
                    ->label('Início')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('fim_exibicao')
                    ->label('Fim')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('leituras_count')
                    ->label('Leituras')
                    ->numeric()
                    ->badge()
                    ->color('info')
                    ->tooltip('Total histórico de confirmações de leitura.'),

                TextColumn::make('criadoPor.name')
                    ->label('Criado por')
                    ->placeholder('Usuário removido')
                    ->toggleable(),

                TextColumn::make('atualizadoPor.name')
                    ->label('Alterado por')
                    ->placeholder('Sem alteração')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status_exibicao')
                    ->label('Status')
                    ->options([
                        'ativo' => 'Ativo',
                        'agendado' => 'Agendado',
                        'inativo' => 'Inativo',
                        'expirado' => 'Expirado',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => self::filtrarStatus(
                        $query,
                        $data['value'] ?? null,
                    )),

                SelectFilter::make('prioridade')
                    ->label('Prioridade')
                    ->options(DashboardPrioridade::options()),

                SelectFilter::make('tipo_publico_alvo')
                    ->label('Público-alvo')
                    ->options([
                        'todos' => 'Todos do escopo',
                        'usuarios' => 'Usuários específicos',
                        'roles' => 'Níveis de acesso',
                        'permissoes' => 'Permissões',
                        'funcoes' => 'Cargos e funções',
                        'escolas' => 'Escolas',
                        'setores' => 'Setores',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => self::filtrarPublicoAlvo(
                        $query,
                        $data['value'] ?? null,
                    )),

                Filter::make('periodo_exibicao')
                    ->label('Período de exibição')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('data_inicio')
                            ->label('De'),
                        DatePicker::make('data_fim')
                            ->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['data_inicio'] ?? null),
                                fn (Builder $builder): Builder => $builder->whereDate('fim_exibicao', '>=', $data['data_inicio']),
                            )
                            ->when(
                                filled($data['data_fim'] ?? null),
                                fn (Builder $builder): Builder => $builder->whereDate('inicio_exibicao', '<=', $data['data_fim']),
                            );
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('visualizar')
                        ->label('Pré-visualizar')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->visible(fn (Aviso $record): bool => Gate::allows('view', $record))
                        ->modalHeading(fn (Aviso $record): string => $record->titulo)
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Fechar')
                        ->modalWidth('3xl')
                        ->modalContent(fn (Aviso $record) => view('filament.admin.resources.avisos.preview', [
                            'aviso' => $record,
                        ])),

                    Action::make('leituras')
                        ->label('Quem leu')
                        ->icon('heroicon-o-user-group')
                        ->color('gray')
                        ->visible(fn (Aviso $record): bool => Gate::allows('view', $record))
                        ->slideOver()
                        ->modalWidth('2xl')
                        ->modalHeading(fn (Aviso $record): string => 'Leituras — '.$record->titulo)
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Fechar')
                        ->modalContent(function (Aviso $record) {
                            $record->load([
                                'leituras' => fn ($leituras) => $leituras
                                    ->with('usuario:id,name,email')
                                    ->latest('lido_em'),
                            ]);

                            return view('filament.admin.resources.avisos.leituras', [
                                'aviso' => $record,
                            ]);
                        }),

                    Action::make('reenviar')
                        ->label('Enviar novamente')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->visible(fn (Aviso $record): bool => Gate::allows('publish', $record))
                        ->disabled(fn (Aviso $record): bool => $record->statusExibicao() !== 'ativo')
                        ->tooltip(fn (Aviso $record): ?string => $record->statusExibicao() !== 'ativo'
                            ? 'Somente avisos ativos podem ser enviados novamente.'
                            : 'Reexibe o aviso e preserva o histórico anterior de leituras.')
                        ->requiresConfirmation()
                        ->modalHeading('Enviar este aviso novamente?')
                        ->modalDescription('O aviso voltará a aparecer para todos os destinatários atuais. O histórico das leituras anteriores será preservado.')
                        ->modalSubmitActionLabel('Enviar novamente')
                        ->action(function (Aviso $record): void {
                            Gate::authorize('publish', $record);

                            /** @var User $user */
                            $user = Auth::user();
                            app(AvisoService::class)->reenviar($record, $user);

                            Notification::make()
                                ->title('Aviso enviado novamente.')
                                ->success()
                                ->send();
                        }),

                    Action::make('alterar_publicacao')
                        ->label(fn (Aviso $record): string => $record->ativo ? 'Desativar' : 'Publicar')
                        ->icon(fn (Aviso $record): string => $record->ativo ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                        ->color(fn (Aviso $record): string => $record->ativo ? 'warning' : 'success')
                        ->visible(fn (Aviso $record): bool => Gate::allows('publish', $record))
                        ->disabled(fn (Aviso $record): bool => ! $record->ativo && $record->fim_exibicao?->isPast())
                        ->tooltip(fn (Aviso $record): ?string => ! $record->ativo && $record->fim_exibicao?->isPast()
                            ? 'Edite o período antes de publicar este aviso expirado.'
                            : null)
                        ->requiresConfirmation()
                        ->modalSubmitActionLabel(fn (Aviso $record): string => $record->ativo ? 'Desativar' : 'Publicar')
                        ->action(function (Aviso $record): void {
                            Gate::authorize('publish', $record);

                            /** @var User $user */
                            $user = Auth::user();
                            app(AvisoService::class)->definirPublicacaoEmMassa(
                                [$record],
                                $user,
                                ! $record->ativo,
                            );
                            $record->refresh();

                            Notification::make()
                                ->title($record->ativo ? 'Aviso publicado.' : 'Aviso desativado.')
                                ->success()
                                ->send();
                        }),

                    Action::make('duplicar')
                        ->label('Duplicar')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->visible(fn (Aviso $record): bool => Gate::allows('duplicate', $record))
                        ->requiresConfirmation()
                        ->modalDescription('A cópia será criada como inativa e manterá o conteúdo, o período e o público-alvo do aviso original.')
                        ->action(function (Aviso $record): void {
                            Gate::authorize('duplicate', $record);

                            /** @var User $user */
                            $user = Auth::user();

                            app(AvisoService::class)->duplicar($record, $user);

                            Notification::make()
                                ->title('Aviso duplicado como inativo.')
                                ->success()
                                ->send();
                        }),

                    EditAction::make(),
                ])
                    ->label('Ações')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('desativar')
                        ->label('Desativar')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->visible(fn (): bool => ($user = Auth::user())
                            && Gate::forUser($user)->allows('publish', Aviso::class))
                        ->requiresConfirmation()
                        ->modalHeading('Desativar avisos selecionados')
                        ->modalDescription('Os avisos selecionados deixarão de aparecer no quadro.')
                        ->action(function ($records): void {
                            /** @var User|null $user */
                            $user = Auth::user();
                            abort_unless($user, 403);

                            $quantidade = app(AvisoService::class)
                                ->definirPublicacaoEmMassa($records, $user, false);

                            Notification::make()
                                ->title("{$quantidade} aviso(s) desativado(s).")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('publicar')
                        ->label('Publicar')
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->visible(fn (): bool => ($user = Auth::user())
                            && Gate::forUser($user)->allows('publish', Aviso::class))
                        ->requiresConfirmation()
                        ->modalHeading('Publicar avisos selecionados')
                        ->modalDescription('Avisos expirados precisam ter o período atualizado antes da publicação.')
                        ->action(function ($records): void {
                            /** @var User|null $user */
                            $user = Auth::user();
                            abort_unless($user, 403);

                            try {
                                $quantidade = app(AvisoService::class)
                                    ->definirPublicacaoEmMassa($records, $user, true);

                                Notification::make()
                                    ->title("{$quantidade} aviso(s) publicado(s).")
                                    ->success()
                                    ->send();
                            } catch (\DomainException $exception) {
                                Notification::make()
                                    ->title('Não foi possível publicar os avisos.')
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('reenviar')
                        ->label('Enviar novamente')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->visible(fn (): bool => ($user = Auth::user())
                            && Gate::forUser($user)->allows('publish', Aviso::class))
                        ->requiresConfirmation()
                        ->modalHeading('Enviar avisos selecionados novamente')
                        ->modalDescription('Os avisos voltarão a aparecer para os destinatários e o histórico de leituras será preservado.')
                        ->action(function ($records): void {
                            /** @var User|null $user */
                            $user = Auth::user();
                            abort_unless($user, 403);

                            try {
                                $quantidade = app(AvisoService::class)
                                    ->reenviarEmMassa($records, $user);

                                Notification::make()
                                    ->title("{$quantidade} aviso(s) enviado(s) novamente.")
                                    ->success()
                                    ->send();
                            } catch (\DomainException $exception) {
                                Notification::make()
                                    ->title('Não foi possível enviar novamente.')
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25);
    }

    private static function filtrarStatus(Builder $query, ?string $status): Builder
    {
        $agora = now();

        return match ($status) {
            'ativo' => $query
                ->where('ativo', true)
                ->where('inicio_exibicao', '<=', $agora)
                ->where('fim_exibicao', '>=', $agora),
            'agendado' => $query
                ->where('ativo', true)
                ->where('inicio_exibicao', '>', $agora),
            'expirado' => $query
                ->where('ativo', true)
                ->where('fim_exibicao', '<', $agora),
            'inativo' => $query->where('ativo', false),
            default => $query,
        };
    }

    private static function filtrarPublicoAlvo(Builder $query, ?string $tipo): Builder
    {
        return match ($tipo) {
            'todos' => $query->whereHas('publicoAlvo', fn (Builder $publico): Builder => $publico->where('todos_usuarios', true)),
            'usuarios' => $query->whereHas('publicoAlvo.usuarios'),
            'roles' => $query->whereHas('publicoAlvo.roles'),
            'permissoes' => $query->whereHas('publicoAlvo.permissoes'),
            'funcoes' => $query->whereHas('publicoAlvo.funcoesAdministrativas'),
            'escolas' => $query->whereHas('publicoAlvo.escolas'),
            'setores' => $query->whereHas('publicoAlvo.setores'),
            default => $query,
        };
    }

    private static function resumoPublicoAlvo(Aviso $aviso): string
    {
        $publico = $aviso->publicoAlvo;

        if (! $publico) {
            return 'Público indisponível';
        }

        if ($publico->todos_usuarios) {
            return 'Todos do escopo';
        }

        $categorias = collect([
            'Usuários' => (int) ($publico->usuarios_count ?? 0),
            'Níveis' => (int) ($publico->roles_count ?? 0),
            'Permissões' => (int) ($publico->permissoes_count ?? 0),
            'Cargos' => (int) ($publico->funcoes_administrativas_count ?? 0),
            'Escolas' => (int) ($publico->escolas_count ?? 0),
            'Setores' => (int) ($publico->setores_count ?? 0),
        ])->filter(fn (int $quantidade): bool => $quantidade > 0);

        return $categorias->isEmpty()
            ? 'Sem critérios válidos'
            : $categorias->map(fn (int $quantidade, string $nome): string => $nome.': '.$quantidade)->join(' · ');
    }
}
