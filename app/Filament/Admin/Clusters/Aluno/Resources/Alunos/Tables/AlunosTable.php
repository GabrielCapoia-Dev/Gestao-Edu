<?php

namespace App\Filament\Admin\Clusters\Aluno\Resources\Alunos\Tables;

use App\Models\Aluno;
use App\Models\User;
use App\Services\UserService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AlunosTable
{
    public static function configure(Table $table, ?User $user = null): Table
    {
        $user        = $user ?? Auth::user();
        $userService = app(UserService::class);

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($user, $userService) {
                $userService->aplicarFiltroPorEscolaDoUsuario($query, $user);

                if ($turmaId = request()->get('turma')) {
                    $query->where('id_turma', $turmaId);
                }
            })
            ->columns(static::columns($user))
            ->recordActions(static::actions($user))
            ->toolbarActions(static::bulkActions($user))
            ->filters(static::filters($user), layout: FiltersLayout::AboveContent)
            ->defaultSort('updated_at', 'desc')
            ->filtersFormColumns(12)
            ->striped()
            ->headerActions(static::headerActions());
    }

    /*
    |--------------------------------------------------------------------------
    | HEADER ACTIONS
    |--------------------------------------------------------------------------
    */

    public static function headerActions(): array
    {
        return [
            Action::make('total_listado')
                ->label(fn($livewire) => 'Total: ' . number_format(
                    $livewire->getFilteredTableQuery()->count(),
                    0,
                    ',',
                    '.'
                ))
                ->disabled()
                ->color('gray')
                ->button()
                ->extraAttributes(['class' => 'cursor-default text-xl font-semibold']),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | COLUMNS
    |--------------------------------------------------------------------------
    */

    public static function columns(?User $user = null): array
    {
        return [
            TextColumn::make('turma.escola.nome')
                ->label('Escola')
                ->wrap()
                ->sortable()
                ->searchable(),

            TextColumn::make('turma.serie.nome')
                ->label('Série')
                ->alignCenter()
                ->sortable()
                ->searchable(),

            TextColumn::make('turma.turma')
                ->label('Turma')
                ->alignCenter()
                ->sortable()
                ->searchable(),

            TextColumn::make('cgm')
                ->label('CGM')
                ->wrap()
                ->sortable()
                ->alignCenter()
                ->searchable()
                ->copyable()
                ->copyMessage('Copiado!')
                ->copyableState(fn($state) => $state)
                ->tooltip('Clique para copiar'),

            TextColumn::make('nome')
                ->label('Nome')
                ->alignCenter()
                ->sortable()
                ->searchable(),

            TextColumn::make('professor.nome')
                ->label('Profissional de Apoio')
                ->wrap()
                ->alignCenter()
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            IconColumn::make('dificuldade_aprendizagem')
                ->label('Dificuldade de Aprendizagem')
                ->boolean()
                ->trueIcon('heroicon-o-check-circle')
                ->falseIcon('heroicon-o-x-circle')
                ->trueColor('success')
                ->falseColor('danger')
                ->alignCenter()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            IconColumn::make('frequenta_srm')
                ->label('Frequenta SRM')
                ->boolean()
                ->trueIcon('heroicon-o-check-circle')
                ->falseIcon('heroicon-o-x-circle')
                ->trueColor('success')
                ->falseColor('danger')
                ->alignCenter()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            IconColumn::make('encaminhado_para_sme')
                ->label('Encaminhado para SME')
                ->boolean()
                ->trueIcon('heroicon-o-check-circle')
                ->falseIcon('heroicon-o-x-circle')
                ->trueColor('success')
                ->falseColor('danger')
                ->alignCenter()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('data_nascimento')
                ->label('Data de Nascimento')
                ->wrap()
                ->date('d/m/Y')
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('data_nascimento')
                ->label('Idade')
                ->formatStateUsing(function ($state) {
                    if (empty($state)) return '-';
                    $idade = Carbon::parse($state)->age;
                    return $idade . ' ' . ($idade == 1 ? 'ano' : 'anos');
                })
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('sexo')
                ->label('Sexo')
                ->wrap()
                ->sortable()
                ->searchable()
                ->badge()
                ->color(fn(string $state): string => match ($state) {
                    'Masculino' => 'primary',
                    'Feminino'  => 'danger',
                    default     => 'gray',
                })
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('laudos_count')
                ->label('Laudos Anexados')
                ->tooltip('Clique para ver os laudos')
                ->state(fn(Aluno $record) => $record->laudos->count())
                ->formatStateUsing(fn(int $state) => $state > 0 ? $state . ' laudo(s)' : '-')
                ->toggleable(isToggledHiddenByDefault: false)
                ->visible(fn() => app(UserService::class)->podeVerLaudos(Auth::user()))
                ->action(
                    Action::make('ver_laudos')
                        ->modal()
                        ->slideOver()
                        ->visible(fn() => app(UserService::class)->podeVerLaudos(Auth::user()))
                        ->modalCancelAction(false)
                        ->modalSubmitAction(false)
                        ->modalHeading(fn(Aluno $record) => "Laudos de {$record->nome}")
                        ->modalContent(fn(Aluno $record) => view(
                            'components.alunos.laudos-modal',
                            ['aluno' => $record]
                        ))
                        ->disabled(fn(Aluno $record) => $record->laudos->isEmpty())
                ),

            TextColumn::make('laudos_nomes')
                ->label('Laudos')
                ->state(function (Aluno $record) {
                    $nomes = $record->laudos->pluck('nome')->filter()->unique()->values()->all();
                    return empty($nomes) ? '-' : implode(' | ', $nomes);
                })
                ->wrap()
                ->toggleable(isToggledHiddenByDefault: true)
                ->visible(fn() => app(UserService::class)->podeVerLaudos(Auth::user()))
                ->searchable(
                    query: fn(Builder $query, string $search): Builder =>
                        $query->whereHas('laudos', fn(Builder $q) => $q->where('nome', 'like', "%{$search}%"))
                ),

            TextColumn::make('created_at')
                ->label('Criado em')
                ->since()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('updated_at')
                ->label('Atualizado em')
                ->since()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | FILTERS
    |--------------------------------------------------------------------------
    */

    public static function filters(?User $user = null): array
    {
        return [
            SelectFilter::make('tipo_escola')
                ->multiple()
                ->label('Tipo Unidade')
                ->visible(fn() => $user->hasPermissionTo('Filtrar Alunos por Escola'))
                ->options(['CMEI' => 'CMEI', 'ESCOLA' => 'ESCOLA'])
                ->columnSpan(2)
                ->query(function (Builder $query, array $data): Builder {
                    $tipos = $data['values'] ?? [];
                    if (empty($tipos)) return $query;

                    return $query->whereHas('turma.escola', function (Builder $q) use ($tipos) {
                        $q->where(function (Builder $inner) use ($tipos) {
                            foreach ($tipos as $tipo) {
                                $inner->orWhere('nome', 'like', $tipo . '%');
                            }
                        });
                    });
                }),

            SelectFilter::make('id_escola')
                ->multiple()
                ->label('Escola')
                ->relationship('turma.escola', 'nome')
                ->searchable()
                ->visible(fn() => $user->hasPermissionTo('Filtrar Alunos por Escola'))
                ->columnSpan(2)
                ->preload(),

            SelectFilter::make('id_serie')
                ->multiple()
                ->label('Série')
                ->relationship('turma.serie', 'nome')
                ->searchable()
                ->columnSpan(2)
                ->preload(),

            Filter::make('idade')
                ->label('Idade')
                ->columnSpan(2)
                ->schema([
                    Grid::make(8)->schema([
                        TextInput::make('idade_min')
                            ->label('De X anos')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(25)
                            ->columnSpan(4),

                        TextInput::make('idade_max')
                            ->label('Até X anos')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(25)
                            ->columnSpan(4),
                    ]),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    $min = $data['idade_min'] ?? null;
                    $max = $data['idade_max'] ?? null;

                    if (! filled($min) && ! filled($max)) return $query;

                    if (filled($min) && filled($max)) {
                        $min = (int) $min;
                        $max = (int) $max;
                        if ($min > $max) [$min, $max] = [$max, $min];
                        return $query->whereBetween('data_nascimento', [
                            now()->subYears($max)->startOfDay(),
                            now()->subYears($min)->endOfDay(),
                        ]);
                    }

                    if (filled($min)) {
                        return $query->whereDate('data_nascimento', '<=', now()->subYears((int) $min)->endOfDay());
                    }

                    return $query->whereDate('data_nascimento', '>=', now()->subYears((int) $max)->startOfDay());
                })
                ->indicateUsing(function (array $data): ?string {
                    $min = $data['idade_min'] ?? null;
                    $max = $data['idade_max'] ?? null;

                    if (! filled($min) && ! filled($max)) return null;
                    if (filled($min) && filled($max)) {
                        $a = (int) $min;
                        $b = (int) $max;
                        if ($a > $b) [$a, $b] = [$b, $a];
                        return $a === $b ? "{$a} anos" : "De {$a} a {$b} anos";
                    }
                    if (filled($min)) return "A partir de {$min} anos";
                    return "Até {$max} anos";
                }),

            SelectFilter::make('laudos')
                ->multiple()
                ->label('Laudo')
                ->relationship('laudos', 'nome')
                ->columnSpan(2)
                ->visible(fn() => $user->hasPermissionTo('Visualizar Laudos de Aluno'))
                ->searchable()
                ->preload(),

            SelectFilter::make('turno')
                ->label('Turno')
                ->options([
                    'Manhã'    => 'Manhã',
                    'Tarde'    => 'Tarde',
                    'Noite'    => 'Noite',
                    'Integral' => 'Integral',
                ])
                ->columnSpan(2)
                ->query(function (Builder $query, array $data): Builder {
                    $turno = $data['value'] ?? null;
                    if (! $turno) return $query;
                    return $query->whereHas('turma', fn(Builder $q) => $q->where('turno', $turno));
                }),

            TernaryFilter::make('tem_laudos')
                ->label('Crianças com laudos')
                ->columnSpan(2)
                ->boolean()
                ->visible(fn() => $user->hasPermissionTo('Visualizar Laudos de Aluno'))
                ->trueLabel('Apenas com laudos')
                ->falseLabel('Apenas sem laudos')
                ->queries(
                    true:  fn(Builder $query) => $query->whereHas('laudos'),
                    false: fn(Builder $query) => $query->whereDoesntHave('laudos'),
                ),

            TernaryFilter::make('com_apoio')
                ->label('Profissional de apoio')
                ->columnSpan(2)
                ->boolean()
                ->trueLabel('Apenas com apoio')
                ->falseLabel('Apenas sem apoio')
                ->queries(
                    true:  fn(Builder $query) => $query->whereNotNull('id_professor'),
                    false: fn(Builder $query) => $query->whereNull('id_professor'),
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ROW ACTIONS
    |--------------------------------------------------------------------------
    */

    public static function actions(?User $user = null): array
    {
        return [
            Action::make('ver_detalhes')
                ->label('Ver detalhes')
                ->icon('heroicon-m-eye')
                ->color('warning')
                ->modal()
                ->slideOver()
                ->visible(fn() => $user->hasPermissionTo('Visualizar Detalhes de Aluno'))
                ->modalCancelAction(false)
                ->modalSubmitAction(false)
                ->modalHeading(fn(Aluno $record) => "Detalhes de {$record->nome}")
                ->modalContent(fn(Aluno $record) => view(
                    'components.alunos.detalhes-modal',
                    ['aluno' => $record]
                )),

            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | BULK ACTIONS
    |--------------------------------------------------------------------------
    */

    public static function bulkActions(?User $user = null): array
    {
        return [
            DeleteBulkAction::make()
                ->visible(fn() => $user->hasPermissionTo('Excluir Alunos em Massa')),
        ];
    }
}