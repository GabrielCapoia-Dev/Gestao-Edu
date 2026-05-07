<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Escola;
use App\Models\Turma;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AlunoService
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function configurarFormulario(Schema $schema, string $operation): Schema
    {
        return $schema->components([
            Section::make('Dados do Aluno')
                ->schema([
                    TextInput::make('nome')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('cgm')
                        ->label('CGM')
                        ->required()
                        ->maxLength(255),

                    DatePicker::make('data_nascimento')
                        ->label('Data de Nascimento')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    Select::make('id_turma')
                        ->label('Turma')
                        ->options(fn() => $this->opcoesDeTurmas(Auth::user()))
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('status')
                        ->label('Status')
                        ->options(Aluno::statusOptions())
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (?string $operation = null): bool => $operation !== 'create'),
                ])
                ->columns(2),
        ]);
    }

    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) use ($user) {
                $this->userService->aplicarFiltroAlunosDoUsuario($query, $user);

                if (request()->filled('turma')) {
                    $query->where('id_turma', request()->integer('turma'));
                }

                $query->with(['turma.escola', 'turma.serie']);
            })
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->columns($this->colunasTabela())
            ->filters($this->filtrosTabela($user))
            ->recordActions($this->acoesTabela($user))
            ->toolbarActions($this->acoesEmMassa($user))
            ->defaultSort('nome')
            ->striped();
    }

    private function colunasTabela(): array
    {
        return [
            TextColumn::make('nome')
                ->label('Nome')
                ->searchable()
                ->sortable()
                ->wrap(),

            TextColumn::make('cgm')
                ->label('CGM')
                ->searchable()
                ->sortable(),

            TextColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn (?string $state): string => Aluno::statusOptions()[$state] ?? ucfirst((string) $state))
                ->badge()
                ->color(fn (?string $state): string => match ($state) {
                    Aluno::STATUS_MATRICULADO => 'success',
                    Aluno::STATUS_REMANEJADO => 'warning',
                    Aluno::STATUS_TRANSFERIDO => 'info',
                    Aluno::STATUS_APROVADO => 'success',
                    Aluno::STATUS_RETIDO => 'danger',
                    default => 'gray',
                })
                ->sortable(),

            TextColumn::make('data_nascimento')
                ->label('Data de Nascimento')
                ->date('d/m/Y')
                ->sortable(),

            TextColumn::make('turma.serie.nome')
                ->label('Série')
                ->sortable()
                ->toggleable(),

            TextColumn::make('turma.nome')
                ->label('Turma')
                ->sortable()
                ->badge(),

            TextColumn::make('turma.escola.nome')
                ->label('Escola')
                ->sortable()
                ->toggleable(),
        ];
    }

    private function filtrosTabela(?User $user): array
    {
        return [
            SelectFilter::make('id_turma')
                ->label('Turma')
                ->options($this->opcoesDeTurmas($user))
                ->searchable()
                ->preload(),

            SelectFilter::make('status')
                ->label('Status')
                ->options(Aluno::statusOptions()),

            SelectFilter::make('id_escola')
                ->label('Escola')
                ->options($this->opcoesDeEscolas($user))
                ->searchable()
                ->preload()
                ->query(function (Builder $query, array $data) {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }

                    return $query->whereHas('turma', fn(Builder $q) => $q->where('id_escola', $data['value']));
                })
                ->visible(fn() => $user?->hasPermissionTo('Filtrar Alunos por Escola') ?? false),
        ];
    }

    private function acoesTabela(?User $user): array
    {
        return [
            Action::make('remanejar')
                ->label('Remanejar')
                ->icon('heroicon-o-arrows-right-left')
                ->color('warning')
                ->visible(fn (Aluno $record): bool => $record->estaMatriculado()
                    && ($user?->hasPermissionLike('realizar remanejamento de aluno') ?? false))
                ->modalHeading(fn (Aluno $record): string => 'Remanejar '.$record->nome)
                ->modalSubmitActionLabel('Remanejar')
                ->schema(fn (Aluno $record): array => [
                    Select::make('turma_destino_id')
                        ->label('Nova turma')
                        ->options(fn () => $this->opcoesDeTurmasParaRemanejamento($record, $user))
                        ->searchable()
                        ->preload()
                        ->required(),
                    Textarea::make('motivo')
                        ->label('Motivo')
                        ->maxLength(1000)
                        ->rows(3),
                ])
                ->action(function (Aluno $record, array $data): void {
                    app(AlunoMovimentacaoService::class)->remanejar(
                        $record,
                        (int) $data['turma_destino_id'],
                        Auth::user(),
                        $data['motivo'] ?? null
                    );

                    Notification::make()
                        ->title('Aluno remanejado com sucesso.')
                        ->success()
                        ->send();
                }),

            Action::make('parecer_transferencia')
                ->label('Parecer de Transferencia')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->visible(fn (Aluno $record): bool => $record->estaMatriculado()
                    && (($user?->hasPermissionLike('realizar transferencia de aluno') ?? false)
                        || ($user?->hasPermissionLike('realizar tranferencia de aluno') ?? false)
                        || ($user?->hasPermissionLike('gerar parecer de transferencia') ?? false)))
                ->url(fn (Aluno $record): string => route('filament.admin.pages.parecer-transferencia-aluno', [
                    'aluno' => $record->id,
                ])),

            EditAction::make()
                ->visible(fn(Aluno $record) => $record->estaMatriculado()
                    && $this->userService->podeEditarAlunos($user)),

            DeleteAction::make()
                ->visible(fn(Aluno $record) => $record->estaMatriculado()
                    && $this->userService->podeExcluirAlunos($user)),
        ];
    }

    private function acoesEmMassa(?User $user): array
    {
        return [
            DeleteBulkAction::make()
                ->authorizeIndividualRecords('delete')
                ->visible(fn() => $user?->hasPermissionTo('Excluir Alunos em Massa') ?? false),
        ];
    }

    public function queryVisivel(?User $user): Builder
    {
        $query = Aluno::query();

        return $this->userService->aplicarFiltroAlunosDoUsuario($query, $user);
    }

    public function opcoesDeTurmas(?User $user): array
    {
        $query = Turma::query()
            ->with(['serie:id,nome', 'escola:id,nome'])
            ->orderBy('nome');

        $this->userService->aplicarFiltroTurmasDoUsuario($query, $user);

        return $query->get()
            ->mapWithKeys(function (Turma $turma) {
                $label = trim(collect([
                    $turma->escola?->nome,
                    $turma->serie?->nome,
                    $turma->nome,
                ])->filter()->join(' - '));

                return [$turma->id => $label];
            })
            ->toArray();
    }

    public function opcoesDeEscolas(?User $user): array
    {
        $query = Escola::query()->orderBy('nome');

        if (! $user?->hasRole('Admin') && filled($user?->id_escola)) {
            $query->whereKey($user->id_escola);
        }

        return $query->pluck('nome', 'id')->toArray();
    }

    private function opcoesDeTurmasParaRemanejamento(Aluno $aluno, ?User $user): array
    {
        $aluno->loadMissing('turma');

        $query = Turma::query()
            ->with(['serie:id,nome', 'escola:id,nome'])
            ->where('id_escola', (int) $aluno->turma?->id_escola)
            ->whereKeyNot((int) $aluno->id_turma)
            ->orderBy('nome');

        $this->userService->aplicarFiltroTurmasDoUsuario($query, $user);

        return $query->get()
            ->mapWithKeys(function (Turma $turma) {
                return [$turma->id => trim(collect([
                    $turma->serie?->nome,
                    $turma->nome,
                    $turma->turno,
                ])->filter()->join(' - '))];
            })
            ->toArray();
    }
}
