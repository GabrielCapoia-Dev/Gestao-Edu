<?php

namespace App\Filament\Admin\Pages\Relatorios;

use App\Models\Escola;
use App\Models\Serie;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use BackedEnum;
use App\Models\TurmaComponenteProfessor;

class RelatorioComponenteProfessorFaltando extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBar;
    protected static ?string $title = 'Componentes com Falta de Professores';
    protected string $view = 'filament.pages.relatorios.relatorio-componente-professor-faltando';
    protected static ?string $slug = 'relatorio-componentes-com-professores-faltando';
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        return $user->hasPermissionTo('Listar Relatórios: Componentes com Professores Faltando');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TurmaComponenteProfessor::query()
                    ->from('turma_componente_professor as tcp')
                    ->join('componentes_curriculares as cc', 'cc.id', '=', 'tcp.componente_curricular_id')
                    ->join('turmas', 'turmas.id', '=', 'tcp.turma_id')
                    ->join('escolas', 'escolas.id', '=', 'turmas.id_escola')
                    ->join('series', 'series.id', '=', 'turmas.id_serie')
                    ->selectRaw('
                        tcp.id,
                        cc.nome as componente_nome,
                        COUNT(*) as total,
                        SUM(CASE WHEN tcp.professor_id IS NOT NULL THEN 1 ELSE 0 END) as com_professor,
                        SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) as sem_professor,
                        turmas.id_escola,
                        turmas.id_serie
                    ')
                    ->groupBy('tcp.id', 'cc.id', 'cc.nome', 'turmas.id_escola', 'turmas.id_serie')
                    ->havingRaw('SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) > 0')
                    ->orderByRaw('SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) DESC')
            )

            ->columns([
                Tables\Columns\TextColumn::make('componente_nome')
                    ->label('Componente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total de vínculos')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('com_professor')
                    ->label('Com professor')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sem_professor')
                    ->label('Sem professor')
                    ->badge()
                    ->color('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cobertura')
                    ->label('Cobertura')
                    ->getStateUsing(function ($record) {
                        if ($record->total == 0) return '0%';
                        return round(($record->com_professor / $record->total) * 100) . '%';
                    })
                    ->badge()
                    ->color(fn($state) => match (true) {
                        (int)$state === 100 => 'success',
                        (int)$state >= 50 => 'warning',
                        default => 'danger',
                    }),
            ])

            ->filters([
                Tables\Filters\SelectFilter::make('escola')
                    ->label('Escola')
                    ->options(fn() => Escola::pluck('nome', 'id'))
                    ->query(
                        fn(Builder $query, array $data) =>
                        $data['value']
                            ? $query->where('turmas.id_escola', $data['value'])
                            : $query
                    ),

                Tables\Filters\SelectFilter::make('serie')
                    ->label('Série')
                    ->options(fn() => Serie::pluck('nome', 'id'))
                    ->query(
                        fn(Builder $query, array $data) =>
                        $data['value']
                            ? $query->where('turmas.id_serie', $data['value'])
                            : $query
                    ),
            ])

            ->defaultSort('sem_professor', 'desc') // 🔴 ESSENCIAL
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5);
    }
}
