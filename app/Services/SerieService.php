<?php

namespace App\Services;

use App\Models\ComponenteCurricular;
use App\Models\Serie;
use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SerieService
{
    public function __construct(
        protected UserService $userService,
    ) {}

    /** Configura a tabela completa (paginações, colunas, filtros, ações, ordenação). */
    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5)
            ->searchable([
                'componentesCurriculares.nome',
            ])
            ->searchPlaceholder('Buscar por código, nome ou componente curricular')
            ->columns($this->colunasTabela())
            ->filters($this->filtrosTabela())
            ->recordActions($this->acoesTabela($user))
            ->toolbarActions($this->acoesEmMassa($user))
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    private function colunasTabela(): array
    {
        return [
            TextColumn::make('codigo')
                ->label('Código')
                ->searchable()
                ->sortable(),

            TextColumn::make('nome')
                ->label('Nome')
                ->searchable()
                ->sortable(),

            TextColumn::make('componentesCurriculares.nome')
                ->searchable()
                ->label('Componentes')
                ->badge()
                ->separator(',')
                ->wrap()
                ->limit(10)
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('turmas_count')
                ->label('Qtd. Turmas')
                ->counts('turmas')
                ->sortable(),

            TextColumn::make('created_at')
                ->label('Criado')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('updated_at')
                ->label('Atualizado')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    private function filtrosTabela(): array
    {
        return [
            SelectFilter::make('componente_curricular_id')
                ->label('Componente curricular')
                ->relationship(
                    'componentesCurriculares',
                    'nome',
                    modifyQueryUsing: fn ($query) => $query->orderBy('nome'),
                )
                ->searchable()
                ->preload(),

            TernaryFilter::make('possui_turmas')
                ->label('Possui turmas')
                ->queries(
                    true: fn ($query) => $query->has('turmas'),
                    false: fn ($query) => $query->doesntHave('turmas'),
                ),
        ];
    }

    private function acoesTabela(?User $user): array
    {
        return [
            EditAction::make(),
            DeleteAction::make()
                ->before(function (DeleteAction $action) use ($user) {
                    if (! $this->userService->ehAdmin($user)) {
                        $action->failure();
                        $action->halt();
                    }
                })
                ->visible(
                    fn () => $this->userService->ehAdmin(Auth::user())
                ),
        ];
    }

    private function acoesEmMassa(?User $user): array
    {
        return [
            BulkAction::make('adicionar_componentes_curriculares')
                ->label('Adicionar componentes')
                ->icon('heroicon-o-plus-circle')
                ->visible(fn (): bool => $user?->hasPermissionTo('Editar Séries') ?? false)
                ->form([
                    Select::make('componentes_curriculares')
                        ->label('Componentes curriculares')
                        ->options(fn (): array => ComponenteCurricular::query()
                            ->orderBy('nome')
                            ->pluck('nome', 'id')
                            ->toArray())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Os componentes selecionados serão adicionados às séries sem remover os vínculos atuais.'),
                ])
                ->requiresConfirmation()
                ->modalHeading('Adicionar componentes às séries selecionadas')
                ->modalDescription('Escolha um ou mais componentes curriculares para vincular a todas as séries selecionadas.')
                ->action(function (array $data, $records): void {
                    $componentesIds = collect($data['componentes_curriculares'] ?? [])
                        ->map(fn ($id): int => (int) $id)
                        ->filter()
                        ->unique()
                        ->values();

                    if ($componentesIds->isEmpty()) {
                        throw ValidationException::withMessages([
                            'componentes_curriculares' => 'Selecione ao menos um componente curricular.',
                        ]);
                    }

                    $componentesValidosIds = ComponenteCurricular::query()
                        ->whereKey($componentesIds->all())
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->all();

                    if ($componentesValidosIds === []) {
                        throw ValidationException::withMessages([
                            'componentes_curriculares' => 'Selecione componentes curriculares válidos.',
                        ]);
                    }

                    $seriesAtualizadas = 0;

                    foreach ($records as $record) {
                        if (! $record instanceof Serie) {
                            continue;
                        }

                        $record->componentesCurriculares()->syncWithoutDetaching($componentesValidosIds);
                        $record->touch();

                        $seriesAtualizadas++;
                    }

                    Notification::make()
                        ->title('Componentes adicionados')
                        ->body("{$seriesAtualizadas} série(s) atualizada(s) sem remover vínculos existentes.")
                        ->success()
                        ->send();
                })
                ->deselectRecordsAfterCompletion(),

            DeleteBulkAction::make()
                ->before(function ($records, $action) use ($user) {
                    if (! $this->userService->podeDeletarEmLote($user, $records)) {
                        $action->halt();
                    }
                })
                ->visible(fn () => $this->userService->ehAdmin(Auth::user())),
        ];
    }
}
