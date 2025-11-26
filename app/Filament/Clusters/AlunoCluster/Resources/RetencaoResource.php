<?php

namespace App\Filament\Clusters\AlunoCluster\Resources;

use App\Filament\Clusters\AlunoCluster;
use App\Filament\Clusters\AlunoCluster\Resources\RetencaoResource\Pages;
use App\Models\AlunoRetencao;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Filament\Pages\SubNavigationPosition;
use Filament\Tables\Actions\Action;

class RetencaoResource extends Resource
{
    protected static ?string $model = AlunoRetencao::class;

    protected static ?string $cluster = AlunoCluster::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $navigationLabel = 'Retenções';
    protected static ?string $modelLabel = 'Retenção';
    protected static ?string $pluralModelLabel = 'Retenções';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        // Se quiser deixar somente leitura, mantém vazio e tira create/edit das pages.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
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
            ->columns([
                // Identificação do aluno
                TextColumn::make('aluno.cgm')
                    ->label('CGM')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Copiado!')
                    ->copyableState(fn($state) => $state)
                    ->tooltip('Clique para copiar'),

                TextColumn::make('aluno.nome')
                    ->label('Aluno')
                    ->sortable()
                    ->searchable()
                    ->wrap(),

                // Escola / turma
                TextColumn::make('aluno.turma.escola.nome')
                    ->label('Escola')
                    ->sortable()
                    ->searchable()
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('aluno.turma.serie.nome')
                    ->label('Série Atual')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('aluno.turma.turma')
                    ->label('Turma Atual')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Dados da retenção
                TextColumn::make('vezes_retido')
                    ->label('Qtd. Retenções')
                    ->sortable(),

                TextColumn::make('serie.nome')
                    ->label('Série em que foi retido')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('ano_retido')
                    ->label('Ano em que foi retido')
                    ->sortable(),

                TextColumn::make('motivo_retido')
                    ->label('Motivos')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) {
                            return '-';
                        }

                        if (is_array($state)) {
                            return implode(', ', $state);
                        }

                        return (string) $state;
                    })
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Criado')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Exemplo: filtro por ano de retenção:
                // Tables\Filters\SelectFilter::make('ano_retido')
                //     ->options(
                //         AlunoRetencao::query()
                //             ->select('ano_retido')
                //             ->distinct()
                //             ->orderByDesc('ano_retido')
                //             ->pluck('ano_retido', 'ano_retido')
                //     ),
            ])
            ->actions([
                // Se for só leitura, deixa sem Edit:
                // Tables\Actions\EditAction::make(),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'aluno.turma.escola',
                'aluno.turma.serie',
                'serie',
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRetencaos::route('/'),
            // Se quiser travar edição/criação manual, não declare create/edit aqui.
            // 'create' => Pages\CreateRetencao::route('/create'),
            // 'edit' => Pages\EditRetencao::route('/{record}/edit'),
        ];
    }
}
