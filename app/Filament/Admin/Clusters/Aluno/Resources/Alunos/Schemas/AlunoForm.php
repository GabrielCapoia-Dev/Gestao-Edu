<?php

namespace App\Filament\Admin\Clusters\Aluno\Resources\Alunos\Schemas;

use App\Models\Aluno;
use App\Models\Turma;
use App\Models\Serie;
use App\Models\Professor;
use App\Services\EscolaService;
use App\Services\UserService;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class AlunoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(static::components());
    }

    public static function components(): array
    {
        return [
            static::secaoDadosAluno(),
            static::secaoRetencoes(),
            static::secaoInformacoesCaei(),
            static::secaoInformacoesMedicas(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SEÇÕES
    |--------------------------------------------------------------------------
    */

    private static function secaoDadosAluno(): Section
    {
        return Section::make('Dados do Aluno')
            ->collapsible()
            ->schema([
                Grid::make(12)->schema([
                    static::campoCgm(),
                    static::campoNome(),
                    static::campoSexo(),
                    static::campoDataNascimento(),
                    static::fieldsetEscola(),
                    static::fieldsetProfissionalApoio(),
                    static::camposCheckboxAluno(),
                ]),
            ]);
    }

    private static function secaoRetencoes(): Section
    {
        return Section::make('Retenções')
            ->collapsible()
            ->schema([
                Grid::make(12)->schema([
                    Grid::make()->schema([
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
                                            ->options(['Sim' => 'Sim', 'Nao' => 'Não'])
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
                                                '1 vez'    => '1 vez',
                                                '2 vezes'  => '2 vezes',
                                                '3 vezes'  => '3 vezes',
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
                                                'Faltas'       => 'Faltas',
                                                'Aprendizagem' => 'Aprendizagem',
                                            ])
                                            ->descriptions([
                                                'Faltas'       => 'Excesso de faltas',
                                                'Aprendizagem' => 'Dificuldades na aprendizagem',
                                            ])
                                            ->columns(2)
                                            ->columnSpan(3),
                                    ]),
                            ])
                            ->columnSpan(6),
                    ]),
                ]),
            ]);
    }

    private static function secaoInformacoesCaei(): Section
    {
        return Section::make('Informações CAEI')
            ->collapsible()
            ->schema([
                Grid::make(12)->schema([
                    Fieldset::make()
                        ->columns(12)
                        ->schema([
                            Radio::make('encaminhado_para_caei')
                                ->label('Encaminhado(a) para a atendimento no CAEI?')
                                ->columns(2)
                                ->columnSpan(5)
                                ->options(['Sim' => 'Sim', 'Nao' => 'Não'])
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
                                            static::radioCaeiProfissional('status_fonoaudiologo', 'Fonoaudiólogo'),
                                            static::radioCaeiProfissional('status_psicologo', 'Psicólogo'),
                                            static::radioCaeiProfissional('status_psicopedagogo', 'Psicopedagogo'),
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
                                            'Sim'                    => 'Sim',
                                            'Nao'                    => 'Não',
                                            'Nao está em atendimento' => 'Não está em atendimento',
                                        ])
                                        ->hidden(fn(Get $get) => ! in_array('Sim', (array) $get('encaminhado_para_caei'), true))
                                        ->dehydrated(fn(Get $get) => in_array('Sim', (array) $get('encaminhado_para_caei'), true))
                                        ->required(fn(Get $get) => in_array('Sim', (array) $get('encaminhado_para_caei'), true)),
                                ]),
                        ]),
                ]),
            ]);
    }

    private static function secaoInformacoesMedicas(): Section
    {
        $userService = app(UserService::class);

        return Section::make('Informações Medicas')
            ->collapsible()
            ->visible(fn() => $userService->podeAnexarLaudos(Auth::user()))
            ->schema([
                Grid::make(12)->schema([
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
                                ->disabled(fn() => ! $userService->podeAnexarLaudos(Auth::user()))
                                ->deletable(fn() => $userService->podeExcluirLaudos(Auth::user()))
                                ->addable(fn() => $userService->podeAnexarLaudos(Auth::user()))
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
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CAMPOS INDIVIDUAIS
    |--------------------------------------------------------------------------
    */

    private static function campoCgm(): TextInput
    {
        return TextInput::make('cgm')
            ->label('CGM:')
            ->required()
            ->minLength(6)
            ->rules(['regex:/^\d+$/'])
            ->validationMessages([
                'regex' => 'Apenas numeros',
                'min'   => 'O CGM deve ter no mínimo 6 dígitos.',
            ])
            ->unique(ignoreRecord: true)
            ->maxLength(20)
            ->columnSpan(3)
            ->reactive()
            ->afterStateUpdated(function ($state, Set $set, Get $get, ?string $operation) {
                if ($operation !== 'create') return;

                $state = trim((string) $state);

                if ($state === '') {
                    static::limparCamposAluno($set);
                    return;
                }

                $alunoBase = Aluno::with('turma')->where('cgm', $state)->first();

                if (! $alunoBase) {
                    static::limparCamposAluno($set);
                    return;
                }

                $set('nome', $alunoBase->nome);
                $set('sexo', $alunoBase->sexo);
                $set('data_nascimento', $alunoBase->data_nascimento);
                $set('frequenta_srm', (bool) $alunoBase->frequenta_srm);
                $set('dificuldade_aprendizagem', (bool) $alunoBase->dificuldade_aprendizagem);
                $set('encaminhado_para_SME', (bool) $alunoBase->encaminhado_para_sme);

                if ($alunoBase->turma) {
                    $set('id_turma', $alunoBase->id_turma);
                    $set('id_escola', $alunoBase->turma->id_escola);
                    $set('turno_turma', $alunoBase->turma->turno);
                }
            });
    }

    private static function campoNome(): TextInput
    {
        return TextInput::make('nome')
            ->label('Nome:')
            ->required()
            ->minLength(3)
            ->columnSpan(3)
            ->disabled(fn(Get $get, ?string $operation) => $operation === 'create' && blank($get('cgm')))
            ->maxLength(100)
            ->rule('regex:/^[\p{L}\p{N}]+(?: [\p{L}\p{N}]+)*$/u')
            ->validationMessages(['regex' => 'Use apenas letras, sem caracteres especiais.']);
    }

    private static function campoSexo(): Select
    {
        return Select::make('sexo')
            ->label('Sexo:')
            ->columnSpan(3)
            ->disabled(fn(Get $get, ?string $operation) => $operation === 'create' && blank($get('cgm')))
            ->placeholder('Selecione')
            ->required()
            ->options(['Masculino' => 'Masculino', 'Feminino' => 'Feminino']);
    }

    private static function campoDataNascimento(): DatePicker
    {
        return DatePicker::make('data_nascimento')
            ->columnSpan(3)
            ->label('Data de Nascimento:')
            ->required()
            ->disabled(fn(Get $get, ?string $operation) => $operation === 'create' && blank($get('cgm')))
            ->maxDate(Carbon::today()->subYears(1))
            ->rule(fn() => 'before_or_equal:' . Carbon::today()->subYears(1)->toDateString())
            ->validationMessages(['before_or_equal' => 'A criança precisa ter pelo menos 1 anos.']);
    }

    private static function fieldsetEscola(): Fieldset
    {
        $escolaService = app(EscolaService::class);
        $userService   = app(UserService::class);

        return Fieldset::make('Informações da Escola')
            ->columns(12)
            ->schema([
                Grid::make(12)->schema([
                    Select::make('id_escola')
                        ->label('Escola')
                        ->options(fn() => $escolaService->opcoesDeEscolasParaUsuario(Auth::user()))
                        ->searchable()
                        ->preload()
                        ->columnSpan(5)
                        ->required()
                        ->default(fn($record) => static::escolaInicialParaForm($record))
                        ->afterStateHydrated(function ($state, callable $set, $record) {
                            $set('id_escola', static::escolaInicialParaForm($record));
                        })
                        ->dehydrated(false)
                        ->disabled(function (Get $get, ?string $operation, $context) use ($userService) {
                            if ($operation !== 'create') {
                                return $userService->deveTravarCampoEscola(Auth::user(), $context);
                            }
                            if (blank($get('cgm'))) return true;
                            return $userService->deveTravarCampoEscola(Auth::user(), $context);
                        })
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
                            return static::opcoesDeTurmasParaEscola($idEscola);
                        })
                        ->searchable()
                        ->required()
                        ->disabled(function (Get $get, $record, ?string $operation) {
                            if ($operation !== 'create') {
                                $idEscola = $get('id_escola') ?? $record?->turma?->id_escola;
                                return blank($idEscola);
                            }
                            if (blank($get('cgm'))) return true;
                            $idEscola = $get('id_escola') ?? $record?->turma?->id_escola;
                            return blank($idEscola);
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
                            'Manhã'    => 'Manhã',
                            'Tarde'    => 'Tarde',
                            'Noite'    => 'Noite',
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
            ->columnSpan(8);
    }

    private static function fieldsetProfissionalApoio(): Fieldset
    {
        return Fieldset::make('Profissional de Apoio')
            ->schema([
                Grid::make()->columns(1)->schema([
                    Checkbox::make('profissional_apoio')
                        ->label('Tem acompanhamento de Profissional de Apoio?')
                        ->reactive()
                        ->afterStateUpdated(function (bool $state, Set $set) {
                            if (! $state) $set('id_professor', null);
                        }),

                    Select::make('id_professor')
                        ->label('Profissional de Apoio')
                        ->options(function (Get $get) {
                            $user     = Auth::user();
                            $idEscola = $get('id_escola') ?? $user?->id_escola;
                            $idTurma  = $get('id_turma');

                            if (! $idTurma) return [];

                            $turno = Turma::whereKey($idTurma)->value('turno');

                            return Professor::query()
                                ->where('id_escola', $idEscola)
                                ->where('profissional_apoio', true)
                                ->where('turno', $turno)
                                ->orderBy('nome')
                                ->limit(500)
                                ->get(['id', 'nome', 'matricula'])
                                ->mapWithKeys(fn($p) => [
                                    $p->id => ($p->matricula ? '#' . $p->matricula . ' - ' : '') . $p->nome,
                                ])
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
                            if (! $get('profissional_apoio') || ! $get('id_turma')) return [];

                            $idEscola = $get('id_escola') ?? Auth::user()?->id_escola;
                            $turno    = Turma::whereKey($get('id_turma'))->value('turno');

                            return [
                                Rule::exists('professores', 'id')
                                    ->where('profissional_apoio', true)
                                    ->when($idEscola, fn($q) => $q->where('id_escola', $idEscola))
                                    ->when($turno,    fn($q) => $q->where('turno', $turno)),
                            ];
                        }),
                ]),
            ])
            ->columnSpan(4);
    }

    private static function camposCheckboxAluno(): Grid
    {
        return Grid::make(12)->schema([
            Checkbox::make('frequenta_srm')
                ->columnSpan(4)
                ->disabled(fn(Get $get, ?string $operation) => $operation === 'create' && blank($get('cgm')))
                ->label('Frequenta Sala de Recursos Multifuncionais?'),

            Checkbox::make('dificuldade_aprendizagem')
                ->columnSpan(4)
                ->disabled(fn(Get $get, ?string $operation) => $operation === 'create' && blank($get('cgm')))
                ->label('Apresenta dificuldade na aprendizagem?'),

            Checkbox::make('encaminhado_para_SME')
                ->columnSpan(4)
                ->disabled(fn(Get $get, ?string $operation) => $operation === 'create' && blank($get('cgm')))
                ->label('Encaminhado(a) para a Equipe Multiprofissional da SME?'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private static function radioCaeiProfissional(string $field, string $label): Radio
    {
        return Radio::make($field)
            ->label($label)
            ->columnSpan(6)
            ->columns(3)
            ->options([
                'Sim, Lista de Espera' => 'Sim, Lista de Espera',
                'Sim, Em Atendimento'  => 'Sim, Em Atendimento',
                'Sim, Desistente'      => 'Sim, Desistente',
                'Sim, Desligado'       => 'Sim, Desligado',
                'Não'                  => 'Não',
            ])
            ->dehydrated(fn(Get $get) => in_array('Sim', (array) $get('encaminhado_para_caei'), true))
            ->required(fn(Get $get) => in_array('Sim', (array) $get('encaminhado_para_caei'), true));
    }

    private static function limparCamposAluno(Set $set): void
    {
        $set('nome', null);
        $set('sexo', null);
        $set('data_nascimento', null);
        $set('id_escola', null);
        $set('id_turma', null);
        $set('turno_turma', null);
        $set('frequenta_srm', false);
        $set('dificuldade_aprendizagem', false);
        $set('encaminhado_para_SME', false);
    }

    private static function escolaInicialParaForm(?Aluno $record): ?int
    {
        return $record?->turma?->id_escola ?? (Auth::user()?->id_escola ?? null);
    }

    private static function opcoesDeTurmasParaEscola(?int $idEscola): array
    {
        if (! $idEscola) return [];

        return Turma::with('serie')
            ->where('id_escola', $idEscola)
            ->get()
            ->filter(fn($t) => $t->serie)
            ->mapWithKeys(fn($turma) => [
                $turma->id => "{$turma->serie->nome} - {$turma->turma}",
            ])
            ->toArray();
    }
}