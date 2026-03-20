<?php

namespace App\Services;

use App\Models\Professor;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Checkbox;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use App\Models\ComponenteCurricular;
use App\Models\Serie;
use App\Services\UserService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;


class ProfessorService
{
    public function __construct(
        protected UserService $userService,
        protected AlunoService $alunoService
    ) {}


    public function configurarFormulario(Schema $schema): Schema
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        return $schema
            ->components([
                Section::make('Dados do Professor')
                    ->schema([
                        Select::make('id_escola')
                            ->label('Escola')
                            ->relationship('escola', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->placeholder('Selecione a escola')
                            ->disabled(function (?Professor $record) use ($user) {
                                return $record !== null && !$user->hasPermissionTo('Editar Escola do Professor');
                            })
                            ->columnSpanFull(),

                        TextInput::make('matricula')
                            ->label('Matrícula')
                            ->required()
                            ->disabled(function (?Professor $record) use ($user) {
                                return $record !== null && !$user->hasPermissionTo('Editar Matricula do Professor');
                            })
                            ->maxLength(255)
                            ->placeholder('Ex: PROF001'),

                        TextInput::make('nome')
                            ->label('Nome Completo')
                            ->required()
                            ->disabled(function (?Professor $record) use ($user) {
                                return $record !== null && !$user->hasPermissionTo('Editar Nome do Professor');
                            })
                            ->maxLength(255)
                            ->placeholder('Ex: João da Silva'),

                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255)
                            ->disabled(function (?Professor $record) use ($user) {
                                return $record !== null && !$user->hasPermissionTo('Editar Dados do Professor');
                            })
                            ->placeholder('professor@exemplo.com'),

                        TextInput::make('telefone')
                            ->label('Telefone')
                            ->tel()
                            ->maxLength(255)
                            ->mask('(99) 99999-9999')
                            ->disabled(function (?Professor $record) use ($user) {
                                return $record !== null && !$user->hasPermissionTo('Editar Dados do Professor');
                            })
                            ->placeholder('(00) 00000-0000'),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }


    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) use ($user) {
                $this->userService->aplicarFiltroPorEscolaDoUsuarioEmTurma($query, $user);
            })
            ->paginated([5, 10, 25, 50, 100])
            ->columns($this->colunasTabela())
            ->recordActions($this->acoesTabela($user))
            ->toolbarActions($this->acoesEmMassa($user))
            ->filters($this->filtrosTabela())
            ->defaultSort('updated_at', 'desc')
            ->striped()
            ->headerActions($this->acoesCabecalho());
    }

    public function colunasTabela(): array
    {
        return [
            TextColumn::make('escola.nome')
                ->label('Escola')
                ->sortable()
                ->wrap(),

            TextColumn::make('matricula')
                ->label('Matrícula')
                ->searchable()
                ->copyable()
                ->sortable(),

            TextColumn::make('nome')
                ->label('Nome')
                ->searchable()
                ->sortable()
                ->copyable()
                ->wrap(),

            TextColumn::make('email')
                ->label('E-mail')
                ->searchable()
                ->copyable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('telefone')
                ->label('Telefone')
                ->searchable()
                ->placeholder('Não informado')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('componentes')
                ->label('Componentes')
                ->getStateUsing(
                    fn($record) =>
                    $record->componentesPorTurma
                        ->pluck('nome')
                        ->unique()
                        ->sort()
                        ->toArray()
                )
                ->badge()
                ->color('info')
                ->separator(',')
                ->wrap()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('turmas_count')
                ->label('Qtd. Turmas')
                ->counts('turmas')
                ->sortable()
                ->alignCenter()
                ->badge()
                ->color('success'),

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

    public function acoesCabecalho(): array
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
                ->icon('heroicon-m-list-bullet')
                ->button()
                ->extraAttributes([
                    'class' => 'cursor-default text-xl font-semibold',
                ])
        ];
    }

    public function acoesTabela(?User $user): array
    {
        return [
            ViewAction::make()
                ->modalHeading(fn($record) => "Detalhes - {$record->nome}")
                ->modalWidth('4xl')
                ->schema([
                    Section::make('Informações do Professor')
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('escola.nome')
                                ->label('Escola'),
                            \Filament\Infolists\Components\TextEntry::make('matricula')
                                ->label('Matrícula')
                                ->copyable(),
                            \Filament\Infolists\Components\TextEntry::make('nome')
                                ->label('Nome')
                                ->copyable(),
                            \Filament\Infolists\Components\TextEntry::make('email')
                                ->label('E-mail')
                                ->copyable(),
                            \Filament\Infolists\Components\TextEntry::make('telefone')
                                ->label('Telefone')
                                ->placeholder('Não informado'),
                        ])
                        ->columns(3),

                    Section::make('Turmas')
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('turmas_lista')
                                ->label('')
                                ->getStateUsing(function ($record) {
                                    return $record->id; // Só precisa retornar algo para o formatStateUsing funcionar
                                })
                                ->formatStateUsing(function ($state, $record) {
                                    $turmas = \App\Models\Turma::whereHas('componentes', function ($query) use ($record) {
                                        $query->where('turma_componente_professor.professor_id', $record->id);
                                    })->with(['serie', 'escola', 'componentes' => function ($query) use ($record) {
                                        $query->wherePivot('professor_id', $record->id);
                                    }])->get();

                                    if ($turmas->isEmpty()) {
                                        return 'Não leciona em nenhuma turma';
                                    }

                                    $html = '<div class="space-y-3">';

                                    foreach ($turmas as $turma) {
                                        $componentes = $turma->componentes->pluck('nome')->join(', ');
                                        $turno = match ($turma->turno) {
                                            'manha' => 'Manhã',
                                            'tarde' => 'Tarde',
                                            'noite' => 'Noite',
                                            'integral' => 'Integral',
                                            default => $turma->turno,
                                        };

                                        $html .= '
                                <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <div class="font-semibold text-primary-600 dark:text-primary-400">
                                        ' . e($turma->serie->nome) . ' - Turma ' . e($turma->nome) . '
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        <span class="font-medium">Escola:</span> ' . e($turma->escola->nome) . '
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        <span class="font-medium">Turno:</span> ' . e($turno) . '
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        <span class="font-medium">Componentes:</span> ' . e($componentes) . '
                                    </div>
                                </div>
                            ';
                                    }

                                    $html .= '</div>';

                                    return new \Illuminate\Support\HtmlString($html);
                                })
                                ->columnSpanFull(),
                        ]),
                ])
                ->visible(function () use ($user): bool {
                    return $user->hasPermissionTo('Visualizar Professores');
                }),

            EditAction::make(),
            DeleteAction::make()
                ->before(function (User $record, DeleteAction $action) use ($user) {
                    if (! $this->userService->podeDeletar($user, $record)) {
                        $action->failure();
                        $action->halt();
                    }
                }),
        ];
    }

    public function acoesEmMassa(?User $user): array
    {
        return [
            DeleteBulkAction::make()
                ->before(function ($records, $action) use ($user) {
                    if (! $this->userService->podeDeletarEmLote($user, $records)) {
                        $action->halt();
                    }
                })
                ->visible(fn() => $this->userService->podeExcluirProfessoresEmLote(Auth::user())),
        ];
    }

    public function filtrosTabela(): array
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        return [
            SelectFilter::make('id_escola')
                ->label('Escola')
                ->relationship('escola', 'nome')
                ->searchable()
                ->preload()
                ->visible(function () use ($user): bool {
                    return $user->hasPermissionTo('Filtrar Professores por Escola');
                }),

            SelectFilter::make('serie_id')
                ->label('Série')
                ->options(
                    Serie::query()->pluck('nome', 'id')
                )
                ->searchable()
                ->query(function ($query, array $data) {
                    if (! $data['value']) {
                        return $query;
                    }

                    return $query->whereHas('turmas', function ($q) use ($data) {
                        $q->where('id_serie', $data['value']);
                    });
                })
                ->visible(function () use ($user): bool {
                    return $user->hasPermissionTo('Filtrar Professores por Serie');
                }),

            SelectFilter::make('componente_curricular_id')
                ->label('Componente')
                ->options(
                    ComponenteCurricular::query()->pluck('nome', 'id')
                )
                ->searchable()
                ->query(function ($query, array $data) {
                    if (! $data['value']) {
                        return $query;
                    }

                    return $query->whereHas('componentesPorTurma', function ($q) use ($data) {
                        $q->where('componente_curricular_id', $data['value']);
                    });
                })
                ->visible(function () use ($user): bool {
                    return $user->hasPermissionTo('Filtrar Professores por Componente');
                }),
        ];
    }

    public function forcarVinculoComEscola(array $data, ?User $auth): array
    {
        if ($auth && filled($auth->id_escola)) {
            $data['id_escola'] = $auth->id_escola;
        }

        return $data;
    }
}
