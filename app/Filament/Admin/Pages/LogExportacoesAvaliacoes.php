<?php

namespace App\Filament\Admin\Pages;

use App\Models\Avaliacao;
use App\Models\AvaliacaoExportacao;
use App\Models\User;
use App\Services\PessoaScopeService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class LogExportacoesAvaliacoes extends Page implements HasTable
{
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationParentItem = 'Avaliações';

    protected string $view = 'filament.pages.log-exportacoes-avaliacoes';

    protected static ?string $title = 'Log de Exportações';

    protected static ?string $navigationLabel = 'Log de Exportações';

    protected static ?string $slug = 'avaliacoes-log-exportacoes';

    protected static ?int $navigationSort = 25;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => 'Log de Exportações de Avaliações',
            'description' => 'Acompanhe o histórico de exportações de avaliações, incluindo detalhes sobre quando foram exportadas, por quem e quais avaliações foram envolvidas.',
        ]);
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', Avaliacao::class)
            || Gate::allows('export', Avaliacao::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('exportado_em')
                    ->label('Quando')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Usuário')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Sistema'),

                TextColumn::make('avaliacao.nome')
                    ->label('Avaliação')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('escopo')
                    ->label('Escopo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'aluno' => 'Aluno',
                        'turma' => 'Turma',
                        'escola' => 'Escola',
                        default => $state,
                    }),

                TextColumn::make('alvo')
                    ->label('O que')
                    ->getStateUsing(fn (AvaliacaoExportacao $record): string => $this->formatarAlvo($record))
                    ->wrap(),

                TextColumn::make('quantidade_alunos')
                    ->label('Alunos')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('quantidade_paginas')
                    ->label('Paginas')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('escopo')
                    ->label('Escopo')
                    ->options([
                        'aluno' => 'Aluno',
                        'turma' => 'Turma',
                        'escola' => 'Escola',
                    ]),

                SelectFilter::make('avaliacao_id')
                    ->label('Avaliação')
                    ->options(fn (): array => Avaliacao::query()->orderBy('nome')->pluck('nome', 'id')->toArray())
                    ->searchable(),
            ])
            ->defaultSort('exportado_em', 'desc');
    }

    private function query(): Builder
    {
        /** @var User|null $user */
        $user = Auth::user();

        $query = AvaliacaoExportacao::query()
            ->with([
                'avaliacao:id,nome,tipo_avaliacao_id',
                'avaliacao.tipo:id,nome',
                'escola:id,nome',
                'turma:id,nome,id_serie,id_escola',
                'turma.serie:id,nome',
                'aluno:id,nome,cgm',
                'user:id,name,email',
            ]);

        $scope = app(PessoaScopeService::class);

        if ($scope->hasGlobalAccess($user)) {
            return $query;
        }

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $escolaIds = $scope->escolaIdsDosVinculos($user);

        if ($escolaIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $escopo) use ($escolaIds): void {
            $escopo
                ->whereIn('escola_id', $escolaIds)
                ->orWhere(function (Builder $legado) use ($escolaIds): void {
                    $legado
                        ->whereNull('escola_id')
                        ->where(function (Builder $relacao) use ($escolaIds): void {
                            $relacao
                                ->whereHas('turma', fn (Builder $turma): Builder => $turma->whereIn('id_escola', $escolaIds))
                                ->orWhereHas('aluno.turma', fn (Builder $turma): Builder => $turma->whereIn('id_escola', $escolaIds));
                        });
                });
        });
    }

    private function formatarAlvo(AvaliacaoExportacao $record): string
    {
        if ($record->escopo === 'aluno') {
            $aluno = trim((string) ($record->aluno?->nome ?? 'Aluno'));
            $turma = trim((string) ($record->turma?->nome ?? ''));

            return trim($aluno.($turma !== '' ? ' - Turma '.$turma : ''));
        }

        if ($record->escopo === 'turma') {
            $turma = trim((string) ($record->turma?->nome ?? 'Turma'));
            $serie = trim((string) ($record->turma?->serie?->nome ?? ''));

            return trim($turma.($serie !== '' ? ' - '.$serie : ''));
        }

        return trim((string) ($record->escola?->nome ?? 'Escola'));
    }
}
