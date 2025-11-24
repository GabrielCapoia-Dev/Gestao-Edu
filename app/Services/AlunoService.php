<?php

namespace App\Services;


use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use App\Models\Aluno;
use App\Models\Turma;
use App\Models\User;
use App\Models\Serie;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Set;
use App\Models\Professor;

class AlunoService
{
    public function __construct(
        protected UserService $userService,
        protected EscolaService $escolaService
    ) {}

    public function configurarFormulario(Form $form, ?User $user): Form
    {
        return $form
            ->schema($this->schemaFormulario());
    }

    public function schemaFormulario(): array
    {
        return [
            Section::make('Dados do Aluno')
                ->collapsible()
                ->schema([
                    Grid::make(12)
                        ->schema([

                            TextInput::make('cgm')
                                ->label('CGM:')
                                ->required()
                                ->minLength(6)
                                ->rules(['regex:/^\d+$/'])
                                ->validationMessages([
                                    'regex' => 'Apenas numeros',
                                    'min' => 'O CGM deve ter no mínimo 6 dígitos.',
                                ])
                                ->unique(ignoreRecord: true)
                                ->maxLength(20)
                                ->columnSpan(3),

                            TextInput::make('nome')
                                ->label('Nome:')
                                ->required()
                                ->minLength(3)
                                ->columnSpan(3)
                                ->maxLength(100)
                                ->rule('regex:/^[\p{L}\p{N}]+(?: [\p{L}\p{N}]+)*$/u')
                                ->validationMessages([
                                    'regex' => 'Use apenas letras, sem caracteres especiais.',
                                ]),

                            Select::make('sexo')
                                ->label('Sexo:')
                                ->columnSpan(3)
                                ->placeholder('Selecione')
                                ->required()
                                ->options([
                                    'Masculino' => 'Masculino',
                                    'Feminino' => 'Feminino',
                                ]),

                            DatePicker::make('data_nascimento')
                                ->columnSpan(3)
                                ->label('Data de Nascimento:')
                                ->required()
                                ->maxDate(Carbon::today()->subYears(1))
                                ->rule(fn() => 'before_or_equal:' . Carbon::today()->subYears(1)->toDateString())
                                ->validationMessages([
                                    'before_or_equal' => 'A criança precisa ter pelo menos 1 anos.',
                                ]),

                            Fieldset::make('Informações da Escola')
                                ->columns(12)
                                ->schema([
                                    Grid::make(12)
                                        ->schema([
                                            Select::make('id_escola')
                                                ->label('Escola')
                                                ->options(fn() => $this->escolaService->opcoesDeEscolasParaUsuario(Auth::user()))
                                                ->searchable()
                                                ->preload()
                                                ->columnSpan(5)
                                                ->required()
                                                ->default(fn($record) => $this->escolaInicialParaForm($record, Auth::user()))
                                                ->afterStateHydrated(function ($state, callable $set, $record) {
                                                    $set('id_escola', $this->escolaInicialParaForm($record, Auth::user()));
                                                })
                                                ->dehydrated(false)
                                                ->disabled(fn() => $this->deveTravarCampoEscola(Auth::user()))
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, Set $set) {
                                                    $set('id_turma', null);
                                                    $set('turno_turma', null);
                                                    $set('id_professor', null);
                                                    $set('profissional_apoio', false);
                                                }),

                                            Select::make('id_turma')
                                                ->label('Turma')
                                                ->options(function (Get $get, $record) {
                                                    $idEscola = $get('id_escola') ?? $record?->turma?->id_escola;
                                                    return $this->opcoesDeTurmasParaEscola($idEscola);
                                                })
                                                ->searchable()
                                                ->required()
                                                ->disabled(function (Get $get, $record) {
                                                    $idEscola = $get('id_escola') ?? $record?->turma?->id_escola;
                                                    return $this->desabilitarSelectTurma($idEscola);
                                                })
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, Set $set) {
                                                    $set('turno_turma', Turma::whereKey($state)->value('turno'));
                                                    $set('id_professor', null);
                                                })
                                                ->columnSpan(4)
                                                ->placeholder('Selecione a turma'),

                                            Select::make('turno_turma')
                                                ->label('Turno da turma')
                                                ->options([
                                                    'Manhã' => 'Manhã',
                                                    'Tarde' => 'Tarde',
                                                    'Noite' => 'Noite',
                                                    'Integral' => 'Integral',
                                                ])
                                                ->native(false)
                                                ->disabled()
                                                ->dehydrated(false)
                                                ->visible(fn(Get $get) => filled($get('id_turma')))
                                                ->columnSpan(3)
                                                ->afterStateHydrated(function (Set $set, Get $get, $record) {
                                                    $idTurma = $get('id_turma') ?? $record?->id_turma;
                                                    if ($idTurma) {
                                                        $set('turno_turma', Turma::whereKey($idTurma)->value('turno'));
                                                    }
                                                }),
                                        ]),
                                ])
                                ->columnSpan(8),



                            Fieldset::make('Profissional de Apoio')
                                ->schema([
                                    Grid::make()->columns(1)->schema([
                                        Checkbox::make('profissional_apoio')
                                            ->label('Tem acompanhamento de Profissional de Apoio?')
                                            ->reactive()
                                            ->afterStateUpdated(function (bool $state, Set $set) {
                                                if (! $state) {
                                                    $set('id_professor', null);
                                                }
                                            }),

                                        Select::make('id_professor')
                                            ->label('Profissional de Apoio')
                                            ->options(function (Get $get) {
                                                $user     = Auth::user();
                                                $idEscola = $get('id_escola') ?? $user?->id_escola;
                                                $idTurma  = $get('id_turma');

                                                if (! $idTurma) {
                                                    return [];
                                                }

                                                $turno = Turma::whereKey($idTurma)->value('turno');

                                                return Professor::query()
                                                    ->where('id_escola', $idEscola)
                                                    ->where('profissional_apoio', true)
                                                    ->where('turno', $turno)
                                                    ->orderBy('nome')
                                                    ->limit(500)
                                                    ->get(['id', 'nome', 'matricula'])
                                                    ->mapWithKeys(function ($p) {
                                                        $label = ($p->matricula ? '#' . $p->matricula . " - " : '') . $p->nome;
                                                        return [$p->id => $label];
                                                    })
                                                    ->all();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->reactive()
                                            ->disabled(fn(Get $get) => ! $get('profissional_apoio') || ! $get('id_turma'))
                                            ->hidden(fn(Get $get) => ! $get('profissional_apoio'))
                                            ->dehydrated(fn(Get $get) => (bool) $get('profissional_apoio'))
                                            ->required(fn(Get $get) => (bool) $get('profissional_apoio') && (bool) $get('id_turma'))
                                            ->placeholder(fn(Get $get) => $get('id_turma') ? 'Selecione o profissional de apoio' : 'Selecione uma turma primeiro')
                                            ->helperText(fn(Get $get) => $get('id_turma') ? null : 'Selecione uma turma primeiro')
                                            ->rules(function (Get $get) {
                                                if (! $get('profissional_apoio') || ! $get('id_turma')) {
                                                    return [];
                                                }

                                                $idEscola = $get('id_escola') ?? Auth::user()?->id_escola;
                                                $turno    = Turma::whereKey($get('id_turma'))->value('turno');

                                                return [
                                                    Rule::exists('professores', 'id')
                                                        ->where('profissional_apoio', true)
                                                        ->when($idEscola, fn($q) => $q->where('id_escola', $idEscola))
                                                        ->when($turno,   fn($q) => $q->where('turno', $turno)),
                                                ];
                                            })

                                    ]),
                                ])
                                ->columnSpan(4),

                            Grid::make(12)
                                ->schema([
                                    Checkbox::make('frequenta_srm')
                                        ->columnSpan(4)
                                        ->label('Frequenta Sala de Recursos Multifuncionais?'),

                                    Checkbox::make('dificuldade_aprendizagem')
                                        ->columnSpan(4)
                                        ->label('Apresenta dificuldade na aprendizagem?'),

                                    Checkbox::make('encaminhado_para_SME')
                                        ->columnSpan(4)
                                        ->label('Encaminhado(a) para a Equipe Multiprofissional da SME?'),
                                ]),
                        ])
                ]),

            Section::make('Retenções')
                ->collapsible()
                ->schema([
                    Grid::make(12)
                        ->schema([
                            Grid::make()
                                ->schema([
                                    Fieldset::make()
                                        ->columns(12)
                                        ->schema([
                                            Grid::make(12)
                                                ->columnSpan(12)
                                                ->schema([
                                                    Radio::make('ja_foi_retido')
                                                        ->label('Já foi retido?')
                                                        ->columns(2)
                                                        ->columnSpan(2)
                                                        ->options([
                                                            'Sim' => 'Sim',
                                                            'Nao' => 'Não',
                                                        ])
                                                        ->required()
                                                        ->reactive()
                                                        ->afterStateUpdated(function ($state, Set $set) {
                                                            if ($state !== 'Sim') {
                                                                $set('retidos', null);
                                                            }
                                                        }),

                                                ]),

                                            Repeater::make('retidos')
                                                ->label('Retenções')
                                                ->relationship('retencoes')
                                                ->defaultItems(1)
                                                ->collapsible()
                                                ->reorderable(false)
                                                ->columns(12)
                                                ->columnSpan(12)
                                                ->hidden(fn(Get $get) => $get('ja_foi_retido') !== 'Sim')
                                                ->dehydrated(fn(Get $get) => $get('ja_foi_retido') === 'Sim')
                                                ->required(fn(Get $get) => $get('ja_foi_retido') === 'Sim')
                                                ->schema([
                                                    Select::make('vezes_retido')
                                                        ->columnSpan(3)
                                                        ->required()
                                                        ->label('Quantas vezes foi retido?')
                                                        ->options([
                                                            '1 vez' => '1 vez',
                                                            '2 vezes' => '2 vezes',
                                                            '3 vezes' => '3 vezes',
                                                            '4 ou mais' => '4 ou mais',
                                                        ]),

                                                    Select::make('id_serie')
                                                        ->label('Série em que foi retido')
                                                        ->options(fn() => Serie::all()->pluck('nome', 'id'))
                                                        ->columnSpan(3)
                                                        ->required(),

                                                    Select::make('ano_retido')
                                                        ->label('Ano retido')
                                                        ->required()
                                                        ->multiple()
                                                        ->columnSpan(3)
                                                        ->options(function () {
                                                            $anoAtual = now()->year;
                                                            $anos = [];

                                                            for ($i = 0; $i < 10; $i++) {
                                                                $ano = $anoAtual - $i;
                                                                $anos[$ano] = $ano;
                                                            }

                                                            return $anos;
                                                        }),

                                                    CheckboxList::make('motivo_retido')
                                                        ->label('As retenções ocorreram por:')
                                                        ->required()
                                                        ->options([
                                                            'Faltas' => 'Faltas',
                                                            'Aprendizagem' => 'Aprendizagem',
                                                        ])
                                                        ->descriptions([
                                                            'Faltas' => 'Excesso de faltas',
                                                            'Aprendizagem' => 'Dificuldades na aprendizagem',
                                                        ])
                                                        ->columns(2)
                                                        ->columnSpan(3),
                                                ])
                                        ])
                                        ->columnSpan(6),


                                ]),

                        ]),

                ]),
            Section::make('Informações CAEI')
                ->collapsible()
                ->schema([
                    Grid::make(12)
                        ->schema([
                            Fieldset::make()
                                ->columns(12)
                                ->schema([
                                    Radio::make('encaminhado_para_caei')
                                        ->label('Encaminhado(a) para a atendimento no CAEI?')
                                        ->columns(2)
                                        ->columnSpan(5)
                                        ->options([
                                            'Sim' => 'Sim',
                                            'Nao' => 'Não',
                                        ])
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, Set $set) {
                                            if (! in_array('Sim', (array) $state, true)) {
                                                $set('fonoaudiologo', null);
                                                $set('psicologo', null);
                                                $set('psicopedagogo', null);
                                                $set('avanco_caei', null);
                                            }
                                        })
                                        ->required(),

                                    Fieldset::make()
                                        ->hidden(fn(Get $get) => ! in_array('Sim', (array) $get('encaminhado_para_caei'), true))
                                        ->columns(12)
                                        ->schema([
                                            Grid::make()
                                                ->hidden(fn(Get $get) => ! in_array('Sim', (array) $get('encaminhado_para_caei'), true))
                                                ->columnSpan(6)
                                                ->schema([
                                                    Radio::make('status_fonoaudiologo')
                                                        ->label('Fonoaudiólogo')
                                                        ->columnSpan(6)
                                                        ->columns(3)
                                                        ->options([
                                                            'Sim, Lista de Espera' => 'Sim, Lista de Espera',
                                                            'Sim, Em Atendimento' => 'Sim, Em Atendimento',
                                                            'Sim, Desistente' => 'Sim, Desistente',
                                                            'Sim, Desligado' => 'Sim, Desligado',
                                                            'Não' => 'Não',
                                                        ])
                                                        ->dehydrated(fn(Get $get) => in_array('Sim', (array) $get('encaminhado_para_caei'), true))
                                                        ->required(fn(Get $get) => in_array('Sim', (array) $get('encaminhado_para_caei'), true)),

                                                    Radio::make('status_psicologo')
                                                        ->label('Psicólogo')
                                                        ->columnSpan(6)
                                                        ->columns(3)
                                                        ->options([
                                                            'Sim, Lista de Espera' => 'Sim, Lista de Espera',
                                                            'Sim, Em Atendimento' => 'Sim, Em Atendimento',
                                                            'Sim, Desistente' => 'Sim, Desistente',
                                                            'Sim, Desligado' => 'Sim, Desligado',
                                                            'Não' => 'Não',
                                                        ])
                                                        ->dehydrated(fn(Get $get) => in_array('Sim', (array) $get('encaminhado_para_caei'), true))
                                                        ->required(fn(Get $get) => in_array('Sim', (array) $get('encaminhado_para_caei'), true)),

                                                    Radio::make('status_psicopedagogo')
                                                        ->label('Psicopedagogo')
                                                        ->columnSpan(6)
                                                        ->columns(3)
                                                        ->options([
                                                            'Sim, Lista de Espera' => 'Sim, Lista de Espera',
                                                            'Sim, Em Atendimento' => 'Sim, Em Atendimento',
                                                            'Sim, Desistente' => 'Sim, Desistente',
                                                            'Sim, Desligado' => 'Sim, Desligado',
                                                            'Não' => 'Não',
                                                        ])
                                                        ->dehydrated(fn(Get $get) => in_array('Sim', (array) $get('encaminhado_para_caei'), true))
                                                        ->required(fn(Get $get) => in_array('Sim', (array) $get('encaminhado_para_caei'), true)),
                                                ]),
                                        ]),
                                    Fieldset::make()
                                        ->hidden(fn(Get $get) => ! in_array('Sim', (array) $get('encaminhado_para_caei'), true))
                                        ->columns(12)
                                        ->schema([


                                            Radio::make('avanco_caei')
                                                ->label('Após o atendimento no CAEI, o(a) estudante apresentou avanços na aprendizagem?')
                                                ->columns(3)
                                                ->columnSpan(7)
                                                ->options([
                                                    'Sim' => 'Sim',
                                                    'Nao' => 'Não',
                                                    'Nao está em atendimento' => 'Não está em atendimento',
                                                ])
                                                ->hidden(fn(Get $get) => ! in_array('Sim', (array) $get('encaminhado_para_caei'), true))
                                                ->dehydrated(fn(Get $get) => in_array('Sim', (array) $get('encaminhado_para_caei'), true))
                                                ->required(fn(Get $get) => in_array('Sim', (array) $get('encaminhado_para_caei'), true))
                                        ]),
                                ]),

                        ]),
                ]),
            Section::make('Informações Medicas')
                ->collapsible()
                ->visible(fn() => $this->userService->podeAnexarLaudos(Auth::user()))
                ->schema([
                    Grid::make(12)
                        ->schema([
                            Fieldset::make()
                                ->columns(12)
                                ->schema([
                                    Repeater::make('laudosPivot')
                                        ->label('Laudos + anexos')
                                        ->relationship('laudosPivot')
                                        ->defaultItems(0)
                                        ->collapsible()
                                        ->columnSpan(12)
                                        ->columns(12)
                                        // Só pode mexer se tiver permissão de anexar:
                                        ->disabled(fn() => ! $this->userService->podeAnexarLaudos(Auth::user()))
                                        ->deletable(fn() => $this->userService->podeExcluirLaudos(Auth::user()))
                                        ->addable(fn() => $this->userService->podeAnexarLaudos(Auth::user()))
                                        ->schema([
                                            Select::make('laudo_id')
                                                ->label('Laudo')
                                                ->relationship('laudo', 'nome')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->columnSpan(4),

                                            FileUpload::make('anexo_laudo_path')
                                                ->label('Arquivo (PDF)')
                                                ->disk('laudos')
                                                ->directory(fn(Get $get) => 'aluno-' . ($get('cgm') ?? 'sem-cgm'))
                                                ->openable(false)
                                                ->required()
                                                ->previewable(false)
                                                ->acceptedFileTypes(['application/pdf'])
                                                ->columnSpan(8),
                                        ]),
                                ]),
                        ]),
                ]),



        ];
    }

    public function opcoesDeProfissionaisApoioParaEscola(?int $idEscola, ?string $turno = null): array
    {
        if (! $idEscola) {
            return [];
        }

        return Professor::query()
            ->where('id_escola', $idEscola)
            ->where('profissional_apoio', true)
            ->when($turno, fn($q) => $q->where('turno', $turno))
            ->orderBy('nome')
            ->limit(500)
            ->pluck('nome', 'id')
            ->all();
    }


    public function opcoesDeProfissionaisParaEscola(?int $idEscola): array
    {
        $query = Professor::query();

        if ($idEscola) {
            $query->where('id_escola', $idEscola);
        }

        return $query
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }


    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) use ($user) {
                $this->aplicarFiltroPorEscolaDoUsuario($query, $user);
            })
            ->columns($this->colunasTabela())
            ->actions($this->acoesTabela($user))
            ->bulkActions($this->acoesEmMassa($user))
            ->filters($this->filtrosTabela(), layout: FiltersLayout::AboveContent)
            ->defaultSort('updated_at', 'desc')
            ->filtersFormColumns(12)
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
                    ->button()
                    ->extraAttributes([
                        'class' => 'cursor-default text-xl font-semibold',
                    ]),
            ]);
    }

    public function aplicarFiltroPorEscolaDoUsuario(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query;
        }
        if ($this->userService->ehAdmin($user)) {
            return $query;
        }
        if (! empty($user->id_escola)) {
            return $query->whereHas('turma', function (Builder $turmaQuery) use ($user) {
                $turmaQuery->where('id_escola', $user->id_escola);
            });
        }
        return $query;
    }


    public function colunasTabela(): array
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
                    if (empty($state)) {
                        return '-';
                    }
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
                    'Feminino' => "danger",
                })
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('laudos_count')
                ->label('Laudos Anexados')
                ->tooltip('Clique para ver os laudos')
                ->state(fn(Aluno $record) => $record->laudos->count())
                ->formatStateUsing(fn(int $state) => $state > 0 ? $state . ' laudo(s)' : '-')
                ->toggleable(isToggledHiddenByDefault: false)
                ->visible(fn() => $this->userService->podeVerLaudos(Auth::user()))
                ->action(
                    Action::make('ver_laudos')
                        ->modal()
                        ->slideOver()
                        ->visible(fn() => $this->userService->podeVerLaudos(Auth::user()))
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
                    // Pega os nomes dos laudos vinculados ao aluno
                    $nomes = $record->laudos
                        ->pluck('nome')   // Collection de nomes
                        ->filter()        // remove null / vazios
                        ->unique()
                        ->values()
                        ->all();          // vira array

                    if (empty($nomes)) {
                        return '-';
                    }

                    // já devolve a string final
                    return implode(' | ', $nomes);
                })
                ->wrap()
                ->toggleable(isToggledHiddenByDefault: true)
                ->visible(fn() => $this->userService->podeVerLaudos(Auth::user()))
                ->searchable(
                    query: function (Builder $query, string $search): Builder {
                        // permite buscar pelo nome do laudo
                        return $query->whereHas('laudos', function (Builder $q) use ($search) {
                            $q->where('nome', 'like', "%{$search}%");
                        });
                    },
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

    public function filtrosTabela(): array
    {
        return [
            SelectFilter::make('id_escola')
                ->label('Escola')
                ->relationship('turma.escola', 'nome')
                ->searchable()
                ->columnSpan(2)
                ->preload(),

            SelectFilter::make('id_serie')
                ->label('Série')
                ->relationship('turma.serie', 'nome')
                ->searchable()
                ->columnSpan(2)
                ->preload(),

            Filter::make('idade')
                ->label('Idade')
                ->columnSpan(2)
                ->form([
                    Grid::make(8)
                        ->schema([
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

                    if (! filled($min) && ! filled($max)) {
                        return $query;
                    }

                    // Ambos preenchidos → intervalo [min, max]
                    if (filled($min) && filled($max)) {
                        $min = (int) $min;
                        $max = (int) $max;

                        // Garante que min <= max mesmo se o usuário inverter
                        if ($min > $max) {
                            [$min, $max] = [$max, $min];
                        }

                        // Ex.: 10–12 anos → datas entre hoje-12 e hoje-10
                        $start = now()->subYears($max)->startOfDay(); // mais velho (idade_max)
                        $end   = now()->subYears($min)->endOfDay();   // mais novo (idade_min)

                        return $query->whereBetween('data_nascimento', [$start, $end]);
                    }

                    // Só idade_min → "a partir de X anos" (>= X)
                    if (filled($min)) {
                        $min = (int) $min;
                        $border = now()->subYears($min)->endOfDay();

                        return $query->whereDate('data_nascimento', '<=', $border);
                    }

                    // Só idade_max → "até X anos" (<= X)
                    $max = (int) $max;
                    $border = now()->subYears($max)->startOfDay();

                    return $query->whereDate('data_nascimento', '>=', $border);
                })
                ->indicateUsing(function (array $data): ?string {
                    $min = $data['idade_min'] ?? null;
                    $max = $data['idade_max'] ?? null;

                    if (! filled($min) && ! filled($max)) {
                        return null;
                    }

                    if (filled($min) && filled($max)) {
                        if ($min == $max) {
                            return "{$min} anos";
                        }

                        // Se usuário inverteu, ajusta só na exibição também
                        $minInt = (int) $min;
                        $maxInt = (int) $max;

                        if ($minInt > $maxInt) {
                            [$minInt, $maxInt] = [$maxInt, $minInt];
                        }

                        return "De {$minInt} a {$maxInt} anos";
                    }

                    if (filled($min)) {
                        return "A partir de {$min} anos";
                    }

                    return "Até {$max} anos";
                }),
            SelectFilter::make('laudos')
                ->label('Laudo')
                ->relationship('laudos', 'nome')
                ->columnSpan(2)
                ->multiple()
                ->searchable()
                ->preload(),

            TernaryFilter::make('tem_laudos')
                ->label('Crianças com laudos')
                ->columnSpan(2)
                ->boolean()
                ->trueLabel('Apenas com laudos')
                ->falseLabel('Apenas sem laudos')
                ->queries(
                    true: fn(Builder $query) => $query->whereHas('laudos'),
                    false: fn(Builder $query) => $query->whereDoesntHave('laudos'),
                ),

            TernaryFilter::make('com_apoio')
                ->label('Profissional de apoio')
                ->columnSpan(2)
                ->boolean()
                ->trueLabel('Apenas com apoio')
                ->falseLabel('Apenas sem apoio')
                ->queries(
                    true: fn(Builder $query) => $query->whereNotNull('id_professor'),
                    false: fn(Builder $query) => $query->whereNull('id_professor'),
                ),
        ];
    }



    private function acoesTabela(): array
    {
        return [
            Action::make('ver_detalhes')
                ->label('Ver detalhes')
                ->icon('heroicon-m-eye')
                ->color('warning')
                ->modal()          // habilita modal
                ->slideOver()      // modal lateral
                ->modalCancelAction(false)
                ->modalSubmitAction(false)
                ->modalHeading(fn(Aluno $record) => "Detalhes de {$record->nome}")
                ->modalContent(fn(Aluno $record) => view(
                    'components.alunos.detalhes-modal',
                    ['aluno' => $record]
                )),
            EditAction::make(),
            DeleteAction::make()
        ];
    }

    public function acoesEmMassa(?User $user): array
    {
        return [
            DeleteBulkAction::make(),

            FilamentExportBulkAction::make('exportar_xlsx')
                ->label('Exportar XLSX')
                ->defaultFormat('xlsx')
                ->formatStates([
                    'dificuldade_aprendizagem' => fn($record) => $record->dificuldade_aprendizagem ? 'Sim' : 'Não',
                    'frequenta_srm'          => fn($record) => $record->frequenta_srm ? 'Sim' : 'Não',
                    'encaminhado_para_sme'   => fn($record) => $record->encaminhado_para_sme ? 'Sim' : 'Não',
                ])
                ->directDownload(),
        ];
    }
    public function desabilitarSelectTurma(?int $idEscola): bool
    {
        return blank($idEscola);
    }

    public function opcoesDeTurmasParaEscola(?int $idEscola): array
    {
        if (! $idEscola) {
            return [];
        }

        return Turma::with('serie')
            ->where('id_escola', $idEscola)
            ->get()
            ->filter(fn($t) => $t->serie) // garante série carregada
            ->mapWithKeys(fn($turma) => [
                $turma->id => "{$turma->serie->nome} - {$turma->turma}",
            ])
            ->toArray();
    }

    public function escolaInicialParaForm(?Aluno $record, ?User $user): ?int
    {
        return $record?->turma?->id_escola ?? ($user?->id_escola ?? null);
    }

    public function deveTravarCampoEscola(?User $user): bool
    {
        return ! app(UserService::class)->ehAdmin($user) && filled($user?->id_escola);
    }
}
