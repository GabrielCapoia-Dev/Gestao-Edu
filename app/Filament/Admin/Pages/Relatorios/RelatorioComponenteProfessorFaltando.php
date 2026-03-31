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

    // ✅ Isso impede o Filament de injetar ORDER BY turma_componente_professor.id
    public function getTableRecordKey(\Illuminate\Database\Eloquent\Model $record): string
    {
        return (string) $record->row_num;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $sub = DB::table('turma_componente_professor as tcp')
                    ->join('componentes_curriculares as cc', 'cc.id', '=', 'tcp.componente_curricular_id')
                    ->join('turmas', 'turmas.id', '=', 'tcp.turma_id')
                    ->join('escolas', 'escolas.id', '=', 'turmas.id_escola')
                    ->join('series', 'series.id', '=', 'turmas.id_serie')
                    ->selectRaw('
                        cc.id as componente_id,
                        cc.nome as componente_nome,
                        COUNT(*) as total,
                        SUM(CASE WHEN tcp.professor_id IS NOT NULL THEN 1 ELSE 0 END) as com_professor,
                        SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) as sem_professor,
                        turmas.id_escola,
                        turmas.id_serie
                    ')
                    ->groupBy('cc.id', 'cc.nome', 'turmas.id_escola', 'turmas.id_serie')
                    ->havingRaw('SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) > 0')
                    ->orderByRaw('SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) DESC');

                // Envolve em mais uma subquery adicionando row_num como chave
                $outer = DB::table(DB::raw("({$sub->toSql()}) as inner_sub"))
                    ->mergeBindings($sub)
                    ->selectRaw('inner_sub.*, ROW_NUMBER() OVER (ORDER BY inner_sub.sem_professor DESC) as row_num');

                return TurmaComponenteProfessor::query()
                    ->fromSub($outer, 'sub')
                    ->select('sub.*')
                    // ✅ Força ordenação por coluna que existe na subquery
                    ->orderBy('sub.sem_professor', 'desc');
            })

            ->columns([
                Tables\Columns\TextColumn::make('componente_nome')
                    ->label('Componente')
                    ->searchable(
                        query: fn(Builder $query, string $search) =>
                            $query->where('sub.componente_nome', 'like', "%{$search}%")
                    )
                    ->sortable(
                        query: fn(Builder $query, string $direction) =>
                            $query->orderBy('sub.componente_nome', $direction)
                    ),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total de vínculos')
                    ->badge()
                    ->color('gray')
                    ->sortable(
                        query: fn(Builder $query, string $direction) =>
                            $query->orderBy('sub.total', $direction)
                    ),

                Tables\Columns\TextColumn::make('com_professor')
                    ->label('Com professor')
                    ->badge()
                    ->color('success')
                    ->sortable(
                        query: fn(Builder $query, string $direction) =>
                            $query->orderBy('sub.com_professor', $direction)
                    ),

                Tables\Columns\TextColumn::make('sem_professor')
                    ->label('Sem professor')
                    ->badge()
                    ->color('danger')
                    ->sortable(
                        query: fn(Builder $query, string $direction) =>
                            $query->orderBy('sub.sem_professor', $direction)
                    ),

                Tables\Columns\TextColumn::make('cobertura')
                    ->label('Cobertura')
                    ->getStateUsing(function ($record) {
                        if (!$record->total) return '0%';
                        return round(($record->com_professor / $record->total) * 100) . '%';
                    })
                    ->badge()
                    ->color(fn(?string $state) => match (true) {
                        ((int) str_replace('%', '', $state ?? '0')) === 100 => 'success',
                        ((int) str_replace('%', '', $state ?? '0')) >= 50  => 'warning',
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
                                ? $query->where('sub.id_escola', $data['value'])
                                : $query
                    ),

                Tables\Filters\SelectFilter::make('serie')
                    ->label('Série')
                    ->options(fn() => Serie::pluck('nome', 'id'))
                    ->query(
                        fn(Builder $query, array $data) =>
                            $data['value']
                                ? $query->where('sub.id_serie', $data['value'])
                                : $query
                    ),
            ])

            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5);
    }
}