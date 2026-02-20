<?php

namespace App\Filament\Clusters\AlunoCluster\Resources;

use App\Filament\Clusters\AlunoCluster;
use App\Filament\Clusters\AlunoCluster\Resources\CaeiResource\Pages;
use App\Models\Aluno;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use Filament\Pages\SubNavigationPosition;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Enums\FiltersLayout;
use App\Services\AlunoService;
use App\Services\UserService;

class CaeiResource extends Resource
{
    protected static ?string $model = Aluno::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = 'CAEI';
    protected static ?string $pluralModelLabel = 'CAEI';
    protected static ?string $modelLabel = 'CAEI';
    protected static ?string $slug = 'caei';

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
    protected static ?string $cluster = AlunoCluster::class;
    protected static ?int $navigationSort = 3;

    public static function alunoService(): AlunoService
    {
        return app(AlunoService::class);
    }
    public static function userService(): UserService
    {
        return app(UserService::class);
    }

    public static function form(Form $form): Form
    {
        // Se o CAEI não edita diretamente por aqui, deixa sem schema.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        $opts = [
            'Sim, Lista de Espera',
            'Sim, Em Atendimento',
            'Sim, Desistente',
            'Sim, Desligado',
            'Não',
        ];

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
            ->columns([
                TextColumn::make('turma.escola.nome')
                    ->label('Escola')
                    ->sortable()
                    ->searchable()
                    ->wrap(),

                TextColumn::make('turma.serie.nome')
                    ->label('Série')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('turma.turma')
                    ->label('Turma')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('cgm')
                    ->label('CGM')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Copiado!')
                    ->copyableState(fn($state) => $state)
                    ->tooltip('Clique para copiar'),

                TextColumn::make('nome')
                    ->label('Aluno')
                    ->sortable()
                    ->searchable()
                    ->wrap(),

                TextColumn::make('encaminhado_para_caei')
                    ->label('Encaminhado para CAEI')
                    ->badge()
                    ->color(fn(?string $state) => match ($state) {
                        'Sim' => 'success',
                        'Nao' => 'danger',
                        'Não' => 'danger',
                        default => 'secondary',
                    }),

                TextColumn::make('status_fonoaudiologo')
                    ->label('Fonoaudiólogo')
                    ->badge()
                    ->color(fn(?string $state) => match ($state) {

                        'Sim, Lista de Espera' => 'warning',
                        'Sim, Em Atendimento' => 'success',
                        'Sim, Desistente' => 'danger',
                        'Sim, Desligado' => 'gray',
                        'Não' => 'danger',
                        default => 'secondary',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status_psicologo')
                    ->label('Psicólogo')
                    ->badge()
                    ->color(fn(?string $state) => match ($state) {

                        'Sim, Lista de Espera' => 'warning',
                        'Sim, Em Atendimento' => 'success',
                        'Sim, Desistente' => 'danger',
                        'Sim, Desligado' => 'gray',
                        'Não' => 'danger',
                        default => 'secondary',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status_psicopedagogo')
                    ->label('Psicopedagogo')
                    ->badge()
                    ->color(fn(?string $state) => match ($state) {

                        'Sim, Lista de Espera' => 'warning',
                        'Sim, Em Atendimento' => 'success',
                        'Sim, Desistente' => 'danger',
                        'Sim, Desligado' => 'gray',
                        'Não' => 'danger',
                        default => 'secondary',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('avanco_caei')
                    ->label('Avanço CAEI')
                    ->badge()
                    ->color(fn(?string $state) => match ($state) {
                        'Sim' => 'success',
                        'Nao' => 'danger',
                        'Nao está em atendimento' => 'warning',
                        default => 'secondary',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(function (Builder $query) use ($user) {
                static::userService()->aplicarFiltroPorEscolaDoUsuario($query, $user);
            })
            ->filtersFormColumns(12)
            ->filters([
                SelectFilter::make('id_escola')
                    ->label('Escola')
                    ->relationship('turma.escola', 'nome')
                    ->searchable()
                    ->visible(function () {
                        /** @var App\Models\User $user */
                        $user = Auth::user();
                        return $user->hasPermissionTo('Filtrar Alunos por Escola');
                    })
                    ->preload()
                    ->columnSpan(4),

                SelectFilter::make('id_serie')
                    ->label('Série')
                    ->relationship('turma.serie', 'nome')
                    ->searchable()
                    ->preload()
                    ->columnSpan(4),

                SelectFilter::make('encaminhado_para_caei')
                    ->label('Encaminhado CAEI')
                    ->options([
                        'Sim' => 'Sim',
                        'Nao' => 'Não',
                    ])
                    ->columnSpan(4),

                SelectFilter::make('avanco_caei')
                    ->label('Avanço CAEI')
                    ->options([
                        'Sim' => 'Sim',
                        'Nao' => 'Não',
                        'Nao está em atendimento' => 'Não está em atendimento',
                    ])
                    ->columnSpan(3),

                SelectFilter::make('status_psicopedagogo')
                    ->label('Psicopedagogo')
                    ->options($opts)
                    ->columnSpan(3),

                SelectFilter::make('status_psicologo')
                    ->label('Psicólogo')
                    ->options($opts)
                    ->columnSpan(3),

                SelectFilter::make('status_fonoaudiologo')
                    ->label('Fonoaudiólogo')
                    ->options($opts)
                    ->columnSpan(3),
            ], layout: FiltersLayout::AboveContent)
            ->actions([])
            ->bulkActions([
                FilamentExportBulkAction::make('exportar_xlsx')
                    ->label('Exportar XLSX')
                    ->defaultFormat('xlsx')
                    ->visible(function () {
                        /** @var App\Models\User $user */
                        $user = Auth::user();
                        return $user->hasPermissionTo('Exportar Alunos');
                    })
                    ->directDownload(),
                FilamentExportBulkAction::make('exportar_pdf')
                    ->label('Exportar PDF')
                    ->defaultFormat('pdf')
                    ->color('danger')
                    ->visible(function () {
                        /** @var App\Models\User $user */
                        $user = Auth::user();
                        return $user->hasPermissionTo('Exportar Alunos');
                    })
                    ->directDownload(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        // Mostra só alunos que têm relação com CAEI (encaminhados ou com status preenchido)
        return parent::getEloquentQuery()
            ->where(function ($q) {
                $q->where('encaminhado_para_caei', 'Sim')
                    ->orWhereNotNull('status_fonoaudiologo')
                    ->orWhereNotNull('status_psicologo')
                    ->orWhereNotNull('status_psicopedagogo')
                    ->orWhereNotNull('avanco_caei');
            })
            ->with(['turma.escola', 'turma.serie']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCaeis::route('/'),
        ];
    }
}
