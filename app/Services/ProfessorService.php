<?php

namespace App\Services;

use App\Models\User;
use App\Models\Professor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Form;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;

class ProfessorService
{
    public function __construct(
        protected UserService $userService,
        protected AlunoService $alunoService
    ) {}

    public function configurarFormulario(Form $form, ?User $user): Form
    {
        return $form
            ->schema($this->schemaFormulario());
    }

    public function schemaFormulario(): array
    {
        return [
            Grid::make(12)
                ->schema([
                    TextInput::make('matricula')
                        ->label('Matricula')
                        ->columnSpan(2)
                        ->minLength(3)
                        ->rules(['regex:/^\d+$/'])
                        ->validationMessages([
                            'regex' => 'Apenas numeros',
                            'min' => 'A matricula deve ter no mínimo 3 dígitos.',
                        ])
                        ->disabled(
                            fn(string $operation) => ! $this->userService->podeEditarMatriculaDoProfessor(Auth::user(), $operation)
                        )
                        ->unique(ignoreRecord: true)
                        ->maxLength(20),

                    TextInput::make('nome')
                        ->label('Nome:')
                        ->required()
                        ->columnSpan(5)
                        ->minLength(3)
                        ->maxLength(100)
                        ->disabled(
                            fn(string $operation) => ! $this->userService->podeEditarNomeDoProfessor(Auth::user(), $operation)
                        )
                        ->rule('regex:/^[\p{L}\p{N}]+(?: [\p{L}\p{N}]+)*$/u')
                        ->validationMessages([
                            'regex' => 'Use apenas letras, sem caracteres especiais.',
                        ]),

                    TextInput::make('email')
                        ->columnSpan(5)
                        ->label('E-mail')
                        ->email(),
                ]),

            Grid::make(6)
                ->schema([
                    Select::make('id_escola')
                        ->label('Escola')
                        ->relationship('escola', 'nome')
                        ->required()
                        ->columnSpan(3)
                        ->preload()
                        ->searchable()
                        ->default(fn() => Auth::user()?->id_escola)
                        ->dehydrated(true)
                        ->disabled(function () {
                            $user = Auth::user();

                            if (! $user) {
                                return false;
                            }

                            if (filled($user->id_escola)) {
                                return true;
                            }

                            return false;
                        }),



                    Select::make('turno')
                        ->columnSpan(3)
                        ->required()
                        ->label('Turno')
                        ->options([
                            'Manhã' => 'Manhã',
                            'Tarde' => 'Tarde',
                            'Noite' => 'Noite',
                        ]),
                ]),

            Grid::make(9)
                ->schema([
                    Checkbox::make('professor_srm')
                        ->columnSpan(3)
                        ->label('É um professor da SRM?')
                        ->helperText('Esse é um professor de Sala de Recursos Multifuncionais?')
                        ->reactive()
                        ->afterStateUpdated(function (Set $set, ?bool $state) {
                            if ($state) {
                                $set('profissional_apoio', false);
                            }
                        })
                        ->afterStateHydrated(function (Set $set, Get $get) {
                            if ($get('professor_srm') && $get('profissional_apoio')) {
                                $set('profissional_apoio', false);
                            }
                        })
                        ->required(fn(Get $get) => ! (bool) $get('profissional_apoio'))
                        ->rules(['prohibited_if:profissional_apoio,1']),

                    Checkbox::make('profissional_apoio')
                        ->columnSpan(3)
                        ->label('É um profissional de Apoio?')
                        ->reactive()
                        ->afterStateUpdated(function (Set $set, ?bool $state) {
                            if ($state) {
                                $set('professor_srm', false);
                            }
                        })
                        ->afterStateHydrated(function (Set $set, Get $get) {
                            if ($get('profissional_apoio') && $get('professor_srm')) {
                                $set('professor_srm', false);
                            }
                        })
                        ->required(fn(Get $get) => ! (bool) $get('professor_srm'))
                        ->rules(['prohibited_if:professor_srm,1']),


                ]),

            Grid::make(12)
                ->visible(
                    fn(string $operation) =>
                    $this->userService->podeVisualizarEspecializacoesDeProfessores(Auth::user())
                        && $this->userService->podeEditarEspecializacoesDeProfessores(Auth::user(), $operation)
                )
                ->schema([
                    Repeater::make('especializacoes')
                        ->label('Especializações')
                        ->relationship('especializacoes')
                        ->defaultItems(1)
                        ->columnSpan(12)
                        ->collapsible()
                        ->columns(12)
                        ->schema([

                            Grid::make(12)
                                ->columnSpan(12)
                                ->schema([
                                    Select::make('tipo')
                                        ->label('Tipo de especialização')
                                        ->placeholder('Selecione')
                                        ->required()
                                        ->options([
                                            'Magisterio'     => 'Magistério',
                                            'Licenciatura'   => 'Licenciatura',
                                            'Bacharelado'    => 'Bacharelado',
                                            'Pos Graduacao'  => 'Pós-graduação',
                                            'Mestrado'       => 'Mestrado',
                                            'Doutorado'      => 'Doutorado',
                                        ])
                                        ->columnSpan(2),

                                    Textarea::make('descricao_especializacao')
                                        ->label('Descrição da especialização')
                                        ->required()
                                        ->autosize()
                                        ->minLength(3)
                                        ->maxLength(255)
                                        ->columnSpan(5),

                                    FileUpload::make('anexo_especializacao_path')
                                        ->label('Documento (PDF)')
                                        ->disk('public')
                                        ->directory(fn(Get $get) => 'professor-especializacoes/' . ($get('../../matricula') ?? 'sem-matricula'))
                                        ->openable(false)
                                        ->previewable(false)
                                        ->acceptedFileTypes(['application/pdf'])
                                        ->columnSpan(5),

                                ]),
                            Grid::make(12)
                                ->columnSpan(12)
                                ->schema([
                                    Checkbox::make('especializacao_educacao_especial')
                                        ->columnSpan(6)
                                        ->label('É de Educação Especial?')
                                        ->helperText('Se essa especialização for de Educação Especial, marque essa opção.')


                                ]),
                        ])
                        ->required(),
                ])
        ];
    }

    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) use ($user) {
                $this->userService->aplicarFiltroPorEscolaDoUsuarioEmTurma($query, $user);
            })
            ->paginated([10, 25, 50, 100])
            ->columns($this->colunasTabela())
            ->actions($this->acoesTabela($user))
            ->bulkActions($this->acoesEmMassa($user))
            ->filters($this->filtrosTabela())
            ->defaultSort('updated_at', 'desc')
            ->striped()
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
            ]);
    }

    public function colunasTabela(): array
    {
        return [
            TextColumn::make('escola.nome')
                ->label('Escola')
                ->wrap()
                ->sortable()
                ->searchable(),

            TextColumn::make('matricula')
                ->label('Matrícula')
                ->copyable()
                ->copyMessage('Matrícula copiada!')
                ->copyableState(fn($state) => $state)
                ->tooltip('Clique para copiar'),

            TextColumn::make('nome')
                ->label('Nome')
                ->sortable()
                ->searchable(),

            TextColumn::make('email')
                ->label('E-mail')
                ->icon('heroicon-o-envelope')
                ->copyable()
                ->copyMessage('E-mail copiado!')
                ->copyableState(fn($state) => $state)
                ->url(fn($record) => $record->email ? "mailto:{$record->email}" : null, shouldOpenInNewTab: false)
                ->sortable()
                ->wrap()
                ->searchable()
                ->toggleable()
                ->tooltip('Clique para copiar'),

            TextColumn::make('especializacoes_nomes')
                ->label('Especializações')
                ->state(function (Professor $record) {
                    $tipos = $record->especializacoes
                        ->pluck('tipo')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    return empty($tipos) ? '-' : implode(', ', $tipos);
                })
                ->wrap()
                ->toggleable(),

            TextColumn::make('turno')
                ->label('Turno')
                ->badge()
                ->color(fn(string $state) => match ($state) {
                    'Manhã' => 'primary',
                    'Tarde' => 'warning',
                    'Noite' => 'gray',
                    default => 'secondary',
                })
                ->alignCenter()
                ->sortable()
                ->searchable(),

            IconColumn::make('professor_srm')
                ->label('Professor SRM')
                ->boolean()
                ->trueIcon('heroicon-m-check-circle')
                ->falseIcon('heroicon-m-x-circle')
                ->trueColor('success')
                ->alignCenter()
                ->sortable()
                ->falseColor('danger')
                ->toggleable(),

            IconColumn::make('profissional_apoio')
                ->label('Profissional de Apoio')
                ->boolean()
                ->alignCenter()
                ->trueIcon('heroicon-m-check-circle')
                ->falseIcon('heroicon-m-x-circle')
                ->trueColor('success')
                ->sortable()
                ->falseColor('danger')
                ->toggleable(),

            IconColumn::make('especializacao_educacao_especial')
                ->label('Educ. Especial?')
                ->boolean()
                ->alignCenter()
                ->trueIcon('heroicon-m-check-circle')
                ->falseIcon('heroicon-m-x-circle')
                ->trueColor('success')
                ->sortable()
                ->falseColor('danger')
                ->toggleable(),

            TextColumn::make('created_at')
                ->label('Criado')
                ->since()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('updated_at')
                ->label('Atualizado')
                ->since()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public function acoesTabela(?User $user): array
    {
        return [
            Action::make('ver_detalhes')
                ->label('Ver detalhes')
                ->icon('heroicon-m-eye')
                ->color('warning')
                ->modal()
                ->slideOver()
                ->modalCancelAction(false)
                ->modalSubmitAction(false)
                ->visible(function () {
                    return $this->userService->podeVisualizarDetalhesProfessor(Auth::user());
                })
                ->modalHeading(fn(Professor $record) => "Detalhes de {$record->nome}")
                ->modalContent(fn(Professor $record) => view(
                    'components.professores.detalhes-modal',
                    ['professor' => $record]
                ))
                ->extraModalWindowAttributes([
                    'class' => 'professor-detalhes-modal-window',
                ]),

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

            BulkActionGroup::make([
                DeleteBulkAction::make()
                    ->before(function ($records, $action) use ($user) {
                        if (! $this->userService->podeDeletarEmLote($user, $records)) {
                            $action->halt();
                        }
                    })
                    ->visible(fn() => $this->userService->podeExcluirProfessoresEmLote(Auth::user())),
            ])
                ->visible(fn() => $this->userService->podeExcluirProfessoresEmLote(Auth::user())),

        ];
    }

    public function filtrosTabela(): array
    {
        return [
            SelectFilter::make('id_escola')
                ->label('Escola')
                ->relationship('escola', 'nome')
                ->preload()
                ->multiple()
                ->visible(fn() => $this->userService->podeFiltrarProfessoresPorEscola(Auth::user()))
                ->searchable()
                ->indicator('Escola'),

            SelectFilter::make('especializacao')
                ->label('Especialização')
                ->options([
                    'Magisterio'     => 'Magistério',
                    'Licenciatura'   => 'Licenciatura',
                    'Bacharelado'    => 'Bacharelado',
                    'Pos Graduacao'  => 'Pós-Graduação',
                    'Mestrado'       => 'Mestrado',
                    'Doutorado'      => 'Doutorado',
                ])
                ->multiple()
                ->searchable()
                ->indicator('Especialização')
                ->query(function (Builder $query, array $data): Builder {
                    $values = (array) ($data['value'] ?? null);

                    if (empty($values)) {
                        return $query;
                    }

                    return $query->whereHas('especializacoes', function (Builder $q) use ($values) {
                        $q->whereIn('tipo', $values);
                    });
                }),


            SelectFilter::make('turno')
                ->label('Turno')
                ->options([
                    'Manhã' => 'Manhã',
                    'Tarde' => 'Tarde',
                    'Noite' => 'Noite',
                ])
                ->indicator('Turno'),

            TernaryFilter::make('professor_srm')
                ->label('Professor SRM')
                ->trueLabel('Somente SRM')
                ->falseLabel('Sem SRM')
                ->placeholder('Todos')
                ->queries(
                    true: fn(Builder $q) => $q->where('professor_srm', true),
                    false: fn(Builder $q) => $q->where('professor_srm', false),
                    blank: fn(Builder $q) => $q
                )
                ->indicator('SRM'),

            TernaryFilter::make('profissional_apoio')
                ->label('Profissional de Apoio')
                ->trueLabel('Somente Apoio')
                ->falseLabel('Sem Apoio')
                ->placeholder('Todos')
                ->queries(
                    true: fn(Builder $q) => $q->where('profissional_apoio', true),
                    false: fn(Builder $q) => $q->where('profissional_apoio', false),
                    blank: fn(Builder $q) => $q
                )
                ->indicator('Apoio'),
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
