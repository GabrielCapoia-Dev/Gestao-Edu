<?php

namespace App\Filament\Clusters\AlunoCluster\Resources;

use App\Filament\Clusters\AlunoCluster;
use App\Filament\Clusters\AlunoCluster\Resources\SalaDeRecursosMultifuncionaisResource\Pages;
use App\Models\Aluno;
use App\Models\Professor;
use Filament\Tables\Filters\SelectFilter;
use Filament\Resources\Resource;
use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Pages\SubNavigationPosition;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Actions\Action;
use App\Services\AlunoService;

class SalaDeRecursosMultifuncionaisResource extends Resource
{
    protected static ?string $model = Aluno::class;
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $navigationIcon = 'heroicon-o-squares-plus';
    protected static ?string $navigationLabel = 'SRM';
    protected static ?string $modelLabel = 'Sala de Recurso Multifuncional';
    protected static ?string $pluralModelLabel = 'Sala de Recursos Multifuncionais';
    protected static ?int $navigationSort = 2;

    protected static ?string $cluster = AlunoCluster::class;

    /**
     * Só alunos que frequentam SRM
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // ajuste aqui se no banco for 'Sim' / 'Não' em vez de boolean
            ->where('frequenta_srm', true)
            ->with([
                'turma.escola',
                'turma.serie',
            ]);
    }
    public static function alunoService(): AlunoService
    {
        return app(AlunoService::class);
    }
    public static function table(Table $table): Table
    {

        /** @var \App\Models\User $user */
        $user = Auth::user();


        return $table
            ->headerActions([
                Action::make('total_listado')
                    ->label(fn($livewire) => 'Total: ' . number_format(
                        $livewire->getFilteredTableQuery()->count(),
                        0,
                        ',',
                        '.'
                    ))
                    ->disabled()
                    ->color('gray')
                    ->icon('heroicon-m-list-bullet')
                    ->button()
                    ->extraAttributes([
                        'class' => 'cursor-default text-xl font-semibold',
                    ]),
            ])
            ->modifyQueryUsing(function (Builder $query) use ($user) {
                static::alunoService()->aplicarFiltroPorEscolaDoUsuario($query, $user);
            })
            ->columns([

                Tables\Columns\TextColumn::make('turma.escola.nome')
                    ->label('Escola')
                    ->sortable()
                    ->searchable()
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('cgm')
                    ->label('CGM')
                    ->sortable()
                    ->searchable()
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->copyable()
                    ->copyMessage('Copiado!')
                    ->copyableState(fn($state) => $state)
                    ->tooltip('Clique para copiar'),

                Tables\Columns\TextColumn::make('nome')
                    ->label('Aluno')
                    ->sortable()
                    ->searchable()
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('turma.serie.nome')
                    ->label('Série')
                    ->sortable(),

                Tables\Columns\TextColumn::make('turma.turma')
                    ->label('Turma')
                    ->sortable(),

                Tables\Columns\TextColumn::make('turma.turno')
                    ->label('Turno')
                    ->sortable(),

                Tables\Columns\TextColumn::make('professores_srm')
                    ->label('Professor SRM')
                    ->getStateUsing(function (Aluno $record) {
                        if (! $record->turma) {
                            return '—';
                        }

                        $professores = Professor::query()
                            ->where('id_escola', $record->turma->id_escola)
                            ->where('turno', $record->turma->turno)
                            ->where('professor_srm', true)
                            ->orderBy('nome')
                            ->pluck('nome')
                            ->toArray();

                        return empty($professores)
                            ? 'Sem professor SRM'
                            : implode(', ', $professores);
                    })
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                SelectFilter::make('id_escola')
                    ->label('Escola')
                    ->relationship('turma.escola', 'nome')
                    ->searchable()
                    ->visible(
                        function () {
                            /** @var App\Models\User $user */
                            $user = Auth::user();
                            return $user->hasPermissionTo('Filtrar Alunos por Escola');
                        }
                    )
                    ->columnSpan(2)
                    ->preload(),

                SelectFilter::make('id_serie')
                    ->label('Série')
                    ->relationship('turma.serie', 'nome')
                    ->searchable()
                    ->columnSpan(2)
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                FilamentExportBulkAction::make('exportar_xlsx')
                    ->label('Exportar XLSX')
                    ->defaultFormat('xlsx')
                    ->formatStates([
                        'tem_carteirinha' => fn($record) => $record->tem_carteirinha ? 'Sim' : 'Não',
                    ])
                    ->directDownload(),
                FilamentExportBulkAction::make('exportar_pdf')
                    ->label('Exportar PDF')
                    ->defaultFormat('pdf')
                    ->color('danger')
                    ->formatStates([
                        'tem_carteirinha' => fn($record) => $record->tem_carteirinha ? 'Sim' : 'Não',
                    ])
                    ->directDownload(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSalaDeRecursosMultifuncionais::route('/'),
        ];
    }
}
