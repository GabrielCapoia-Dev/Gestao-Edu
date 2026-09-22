<?php

namespace App\Filament\Admin\Pages\Schemas;

use App\Models\Enums\EventoCalendarioCor;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\Enums\EventoCalendarioTransporteEscopo;
use App\Models\Aluno;
use App\Models\Escola;
use App\Models\EventoCalendario;
use App\Models\FuncaoAdministrativa;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Services\Dashboard\EventoCalendarioEscolaService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class EventoCalendarioForm
{
    /** @var array<string, array{inicio: string, fim: string}> */
    public const PERIODOS = [
        'manha' => ['inicio' => '08:00', 'fim' => '12:00'],
        'tarde' => ['inicio' => '13:30', 'fim' => '17:30'],
        'noite' => ['inicio' => '19:00', 'fim' => '22:00'],
        'dia_todo' => ['inicio' => '08:00', 'fim' => '17:30'],
    ];

    public static function configure(Schema $schema, ?User $user): Schema
    {
        return $schema->components(self::components($user));
    }

    /** @return array<int, Component> */
    public static function components(
        ?User $user,
        bool $exibirAcoesFinais = true,
        string $cancelWireMethod = 'unmountAction',
    ): array
    {
        $somenteTransporte = $user !== null
            && Gate::forUser($user)->allows('requiresTransport', EventoCalendario::class);

        $eventoStep = Grid::make(1)
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('titulo')
                        ->label('Título')
                        ->required()
                        ->maxLength(160)
                        ->columnSpanFull(),
                    Textarea::make('descricao')
                        ->label('Descrição')
                        ->rows(4)
                        ->maxLength(5000)
                        ->columnSpanFull(),
                    TextInput::make('local')
                        ->label('Local do evento')
                        ->placeholder('Ex.: Centro de Formação Municipal')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('latitude')->hidden()->dehydrated(),
                    TextInput::make('longitude')->hidden()->dehydrated(),
                    TextInput::make('endereco_mapa')->hidden()->dehydrated(),
                    ViewField::make('mapa_local')
                        ->label('Localização no mapa')
                        ->view('filament.admin.pages.fields.evento-local-map')
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    Grid::make(['default' => 1, 'md' => 4])
                        ->schema([
                            Select::make('cor')
                                ->label('Identificação visual')
                                ->options(collect(EventoCalendarioCor::cases())->mapWithKeys(
                                    fn ($item): array => [$item->value => $item->label()],
                                )->all())
                                ->default(EventoCalendarioCor::AZUL->value)
                                ->required()
                                ->native(false),
                            TextInput::make('link_acao')
                                ->label('Link de ação')
                                ->placeholder('https://exemplo.gov.br/...')
                                ->maxLength(2048)
                                ->rule('starts_with:https://')
                                ->live(),
                            TextInput::make('texto_botao')
                                ->label('Texto do botão')
                                ->placeholder('Ex.: Saiba mais')
                                ->required(fn (Get $get): bool => filled($get('link_acao')))
                                ->maxLength(80),
                            Select::make('categoria')
                                ->label('Categoria')
                                ->options(collect(EventoCalendarioCategoria::cases())->mapWithKeys(
                                    fn (EventoCalendarioCategoria $categoria): array => [$categoria->value => $categoria->label()],
                                )->all())
                                ->required()
                                ->native(false)
                                ->live(),
                        ]),
                    TextInput::make('categoria_detalhe')
                        ->label('Qual categoria?')
                        ->required(fn (Get $get): bool => $get('categoria') === EventoCalendarioCategoria::OUTRO->value)
                        ->visible(fn (Get $get): bool => $get('categoria') === EventoCalendarioCategoria::OUTRO->value)
                        ->maxLength(160),
                    Grid::make([
                        'default' => 1,
                        'md' => 4,
                    ])
                        ->schema([
                            DatePicker::make('data_evento')
                                ->label('Data do evento')
                                ->required()
                                ->native(false),
                            Select::make('periodo')
                                ->label('Período')
                                ->options([
                                    'manha' => 'Manhã',
                                    'tarde' => 'Tarde',
                                    'noite' => 'Noite',
                                    'dia_todo' => 'Dia todo',
                                ])
                                ->placeholder('Preencher automaticamente')
                                ->helperText('Sugere os horários')
                                ->live()
                                ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                    $periodo = self::PERIODOS[(string) $state] ?? null;

                                    if ($periodo) {
                                        $set('hora_inicio', $periodo['inicio']);
                                        $set('hora_fim', $periodo['fim']);
                                        $set('turnos_filtro', self::turnosParaPeriodo((string) $state));
                                        $set('transporte_turnos', self::turnosParaPeriodo((string) $state));
                                    }
                                })
                                ->native(false),
                            TimePicker::make('hora_inicio')
                                ->label('Início')
                                ->seconds(false)
                                ->live()
                                ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                    $set('turnos_filtro', self::turnosPorHorario($state, $get('hora_fim')));
                                })
                                ->required(),
                            TimePicker::make('hora_fim')
                                ->label('Fim')
                                ->seconds(false)
                                ->after('hora_inicio')
                                ->live()
                                ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                    $set('turnos_filtro', self::turnosPorHorario($get('hora_inicio'), $state));
                                })
                                ->required(),
                        ]),
                    Hidden::make('enviar_escolas_especificas')
                        ->hidden()
                        ->default($somenteTransporte)
                        ->dehydrated(),
                ]);

        $publicoStep = Grid::make(1)
            ->columnSpanFull()
            ->schema([
                Hidden::make('publico_tipo')->default('segmentado')->dehydrated(),
                Hidden::make('funcoes_administrativas_ids')->default([])->dehydrated(),
                Hidden::make('publico_regras')->default([])->dehydrated(),
                Hidden::make('publico_excecoes_ids')->default([])->dehydrated(),
                Hidden::make('publico_preview_aplicado')->default(false)->dehydrated(false),
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    Select::make('publico_escola_ids')->label('Escolas')->multiple()->searchable()->preload()
                        ->options(fn (): array => self::schoolOptions($user))->live()->afterStateUpdated(fn (Get $get, Set $set) => self::sincronizarRegraPublico($get, $set))->native(false),
                    Select::make('publico_funcao_ids')->label('Cargos')->multiple()->searchable()->preload()
                        ->options(fn (): array => self::funcoesConviteOptions())->live()->afterStateUpdated(fn (Get $get, Set $set) => self::sincronizarRegraPublico($get, $set))->native(false),
                ]),
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Select::make('publico_turnos')->label('Turnos')->multiple()->options(self::turnoOptions())
                        ->live()->afterStateUpdated(fn (Get $get, Set $set) => self::sincronizarRegraPublico($get, $set))->native(false),
                    Select::make('publico_serie_ids')->label('Séries')->multiple()->searchable()->preload()
                        ->options(fn (): array => Serie::query()->orderBy('nome')->pluck('nome', 'id')->all())->live()->afterStateUpdated(fn (Get $get, Set $set) => self::sincronizarRegraPublico($get, $set))->native(false),
                    Select::make('publico_componente_ids')->label('Componentes curriculares')->multiple()->searchable()->preload()
                        ->options(fn (): array => \App\Models\ComponenteCurricular::query()->orderBy('nome')->pluck('nome', 'id')->all())->live()->afterStateUpdated(fn (Get $get, Set $set) => self::sincronizarRegraPublico($get, $set))->native(false),
                ]),
                ViewField::make('aplicar_filtros_participantes')
                    ->hiddenLabel()
                    ->dehydrated(false)
                    ->view('filament.admin.pages.fields.evento-participantes-filter-action')
                    ->columnSpanFull(),
                ViewField::make('publico_participantes_tabela')
                    ->hiddenLabel()
                    ->dehydrated(false)
                    ->view('filament.admin.pages.fields.evento-participantes-table')
                    ->viewData(function (Get $get) use ($user): array {
                        $usuarios = (bool) $get('publico_preview_aplicado') && $user
                            ? app(\App\Services\Dashboard\EventoCalendarioPublicoService::class)->preview($user, self::regraPublico($get), $get('publico_excecoes_ids') ?? [])
                            : collect();

                        return [
                            'usuarios' => $usuarios->values(),
                            'aguardandoFiltro' => ! (bool) $get('publico_preview_aplicado'),
                            'excecoes' => collect($get('publico_excecoes_ids') ?? [])->map(fn ($id): int => (int) $id)->all(),
                            'escolasSelecionadas' => collect($get('publico_escola_ids') ?? [])
                                ->filter()
                                ->map(fn ($id): int => (int) $id)
                                ->values()
                                ->all(),
                        ];
                    })
                    ->columnSpanFull(),
            ]);

        $escolaStep = Section::make('Distribuição escolar')
                ->description('Use os filtros para localizar e carregar automaticamente as escolas participantes.')
                ->columnSpanFull()
                ->visible(fn (): bool => $somenteTransporte)
                ->schema([
                    Grid::make([
                        'default' => 1,
                        'md' => 2,
                    ])->schema([
                        Toggle::make('selecionar_todas_escolas_filtro')
                            ->label('Considerar todas as escolas do meu escopo')
                            ->helperText('Os demais filtros poderão reduzir esse conjunto.')
                            ->default(true)
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) use ($user): void {
                                $set('series_filtro_ids', []);
                                $set('turmas_filtro_ids', []);
                                self::atualizarDistribuicao($user, $get, $set);
                            }),
                        Select::make('escolas_filtro_ids')
                            ->label('Escolas')
                            ->options(fn (): array => self::schoolOptions($user))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->placeholder('Selecione uma ou mais escolas')
                            ->visible(fn (Get $get): bool => ! (bool) $get('selecionar_todas_escolas_filtro'))
                            ->required(fn (Get $get): bool => ! (bool) $get('selecionar_todas_escolas_filtro'))
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) use ($user): void {
                                $set('series_filtro_ids', []);
                                $set('turmas_filtro_ids', []);
                                self::atualizarDistribuicao($user, $get, $set);
                            })
                            ->native(false),
                        Select::make('series_filtro_ids')
                            ->label('Séries')
                            ->options(fn (Get $get): array => self::seriesFilterOptions(
                                $user,
                                $get('selecionar_todas_escolas_filtro'),
                                $get('escolas_filtro_ids'),
                            ))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->placeholder('Todas as séries')
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) use ($user): void {
                                $set('turmas_filtro_ids', []);
                                self::atualizarDistribuicao($user, $get, $set);
                            })
                            ->native(false),
                        Select::make('turnos_filtro')
                            ->label('Turnos')
                            ->options(self::turnoOptions())
                            ->multiple()
                            ->placeholder('Inferido pelo horário do evento')
                            ->helperText('O período sugere o turno; você pode ajustar este filtro.')
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) use ($user): void {
                                $set('turmas_filtro_ids', []);
                                self::atualizarDistribuicao($user, $get, $set);
                            })
                            ->native(false),
                        Select::make('turmas_filtro_ids')
                            ->label('Turmas')
                            ->options(fn (Get $get): array => self::turmasFilterOptions(
                                $user,
                                $get('selecionar_todas_escolas_filtro'),
                                $get('escolas_filtro_ids'),
                                $get('series_filtro_ids'),
                                $get('turnos_filtro'),
                            ))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->placeholder('Todas as turmas encontradas')
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::atualizarDistribuicao($user, $get, $set))
                            ->native(false),
                        Toggle::make('precisa_transporte_grupo')
                            ->label('Precisa de transporte?')
                            ->helperText('A estimativa considera somente matrículas principais ativas dos filtros informados.')
                            ->default($somenteTransporte)
                            ->disabled($somenteTransporte)
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::atualizarDistribuicao($user, $get, $set)),
                    ]),
                    Placeholder::make('resumo_distribuicao')
                        ->label('Resumo da seleção')
                        ->content(fn (Get $get): HtmlString => self::resumoDistribuicao($user, $get('escolas_agendadas')))
                        ->columnSpanFull(),
                    Repeater::make('escolas_agendadas')
                        ->label('Escolas carregadas')
                        ->required(fn (Get $get): bool => (bool) $get('enviar_escolas_especificas'))
                        ->minItems(1)
                        ->defaultItems(0)
                        ->addActionLabel('+ Escola')
                        ->reorderable(false)
                        ->cloneable(false)
                        ->itemLabel(fn (array $state): string => self::schoolLabel($user, $state['escola_id'] ?? null))
                        ->schema([
                            Select::make('escola_id')
                                ->label('Escola')
                                ->options(fn (): array => self::schoolOptions($user))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->distinct()
                                ->live()
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('series_ids', []);
                                    $set('turmas_ids', []);
                                    $set('quantidade_estimada_transporte', null);
                                })
                                ->native(false)
                                ->columnSpanFull(),
                            Toggle::make('precisa_transporte')
                                ->label('Precisa de transporte?')
                                ->default($somenteTransporte)
                                ->disabled($somenteTransporte)
                                ->dehydrated()
                                ->live()
                                ->afterStateUpdated(function (bool $state, Set $set): void {
                                    if (! $state) {
                                        $set('escopo_transporte', null);
                                        $set('series_ids', []);
                                        $set('turmas_ids', []);
                                    }

                                    $set('quantidade_estimada_transporte', null);
                                })
                                ->columnSpanFull(),
                            Select::make('escopo_transporte')
                                ->label('Estudantes considerados')
                                ->options(EventoCalendarioTransporteEscopo::options())
                                ->required(fn (Get $get): bool => (bool) $get('precisa_transporte'))
                                ->visible(fn (Get $get): bool => (bool) $get('precisa_transporte'))
                                ->live()
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('series_ids', []);
                                    $set('turmas_ids', []);
                                    $set('quantidade_estimada_transporte', null);
                                })
                                ->native(false)
                                ->columnSpanFull(),
                            Select::make('series_ids')
                                ->label('Séries')
                                ->options(fn (Get $get): array => self::seriesOptions($user, $get('escola_id')))
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->required(fn (Get $get): bool => $get('escopo_transporte') === EventoCalendarioTransporteEscopo::SERIES->value)
                                ->visible(fn (Get $get): bool => $get('escopo_transporte') === EventoCalendarioTransporteEscopo::SERIES->value)
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('quantidade_estimada_transporte', null))
                                ->columnSpanFull(),
                            Select::make('turmas_ids')
                                ->label('Turmas')
                                ->options(fn (Get $get): array => self::turmasOptions($user, $get('escola_id')))
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->required(fn (Get $get): bool => $get('escopo_transporte') === EventoCalendarioTransporteEscopo::TURMAS->value)
                                ->visible(fn (Get $get): bool => $get('escopo_transporte') === EventoCalendarioTransporteEscopo::TURMAS->value)
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('quantidade_estimada_transporte', null))
                                ->columnSpanFull(),
                            Placeholder::make('estimativa_transporte')
                                ->label('Estimativa para transporte')
                                ->content(function (Get $get) use ($user): string {
                                    if (! $get('precisa_transporte')) {
                                        return 'Transporte não solicitado.';
                                    }

                                    $total = is_numeric($get('quantidade_estimada_transporte'))
                                        ? (int) $get('quantidade_estimada_transporte')
                                        : app(EventoCalendarioEscolaService::class)->estimarParaFormulario(
                                            $user,
                                            $get('escola_id'),
                                            $get('escopo_transporte'),
                                            $get('series_ids'),
                                            $get('turmas_ids'),
                                        );

                                    return "{$total} estudante(s) matriculado(s).";
                                })
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->collapsible()
                        ->collapsed()
                        ->columnSpanFull(),
                ]);

        $transporteStep = Step::make('Transporte escolar')->schema([
            Hidden::make('escolas_agendadas')->default([])->dehydrated(),
            Hidden::make('transporte_excecoes_aluno_ids')->default([])->dehydrated(),
            ToggleButtons::make('precisa_transporte_evento')
                ->label('Vai precisar de transporte para os alunos?')
                ->options(['sim' => 'Sim', 'nao' => 'Não'])
                ->inline()
                ->default('nao')
                ->required()
                ->live()
                ->extraAttributes(['class' => 'evento-transporte-pergunta'])
                ->afterStateUpdated(function (string $state, Get $get, Set $set): void {
                    $precisa = $state === 'sim';
                    $set('enviar_escolas_especificas', $precisa);
                    $set('precisa_transporte_grupo', $precisa);
                    if ($precisa && blank($get('transporte_escola_ids'))) {
                        $set('selecionar_todas_escolas_filtro', true);
                    }
                    if (! $precisa) {
                        $set('escolas_agendadas', []);
                    }
                }),
            Grid::make(['default' => 1, 'md' => 4])
                ->visible(fn (Get $get): bool => $get('precisa_transporte_evento') === 'sim')
                ->schema([
                    Select::make('transporte_escola_ids')->label('Escolas')->multiple()->searchable()->preload()
                        ->options(fn (): array => self::schoolOptions($user))->live()
                        ->afterStateUpdated(function (Get $get, Set $set) use ($user): void {
                            $escolas = collect($get('transporte_escola_ids') ?? [])->filter()->values()->all();
                            $set('selecionar_todas_escolas_filtro', $escolas === []);
                            $set('escolas_filtro_ids', $escolas);
                            self::atualizarDistribuicao($user, $get, $set);
                        })->native(false),
                    Select::make('transporte_prefixos')->label('Tipo de escola')->multiple()
                        ->options(['CMEI' => 'CMEI', 'ESCOLA' => 'Escola'])
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set) use ($user): void {
                            if (blank($get('transporte_escola_ids'))) {
                                $set('selecionar_todas_escolas_filtro', true);
                            }
                            self::atualizarDistribuicao($user, $get, $set);
                        })->native(false),
                    Select::make('transporte_serie_ids')->label('Séries')->multiple()->searchable()->preload()
                        ->options(fn (): array => Serie::query()->orderBy('nome')->pluck('nome', 'id')->all())->live()
                        ->afterStateUpdated(function (Get $get, Set $set) use ($user): void {
                            $set('series_filtro_ids', $get('transporte_serie_ids'));
                            self::atualizarDistribuicao($user, $get, $set);
                        })->native(false),
                    Select::make('transporte_turnos')->label('Turnos')->multiple()->options(self::turnoOptions())->live()
                        ->afterStateUpdated(function (Get $get, Set $set) use ($user): void {
                            $set('turnos_filtro', $get('transporte_turnos'));
                            self::atualizarDistribuicao($user, $get, $set);
                        })->native(false),
                ]),
            ViewField::make('transporte_alunos_tabela')
                ->hiddenLabel()
                ->dehydrated(false)
                ->visible(fn (Get $get): bool => $get('precisa_transporte_evento') === 'sim')
                ->view('filament.admin.pages.fields.evento-transporte-alunos-table')
                ->viewData(function (Get $get) use ($user): array {
                    $escolas = collect($get('transporte_escola_ids') ?? [])->filter()->map(fn ($id): int => (int) $id)->values()->all();
                    $prefixos = collect($get('transporte_prefixos') ?? [])->map(fn ($prefixo): string => mb_strtoupper(trim((string) $prefixo)))->filter()->values()->all();
                    $series = collect($get('transporte_serie_ids') ?? [])->filter()->values()->all();
                    $turnos = collect($get('transporte_turnos') ?? [])->filter()->values()->all();
                    $possuiFiltro = $escolas !== [] || $prefixos !== [] || $series !== [] || $turnos !== [];
                    $alunos = collect();
                    $mensagem = null;

                    if (! $possuiFiltro) {
                        $mensagem = 'Selecione ao menos um filtro para listar os alunos.';
                    } elseif ($user) {
                        $escolasPermitidas = array_map('intval', array_keys(self::schoolOptions($user)));
                        $escolasConsulta = $escolas !== []
                            ? array_values(array_intersect($escolas, $escolasPermitidas))
                            : $escolasPermitidas;

                        $alunos = Aluno::query()
                            ->select(['id', 'nome', 'id_turma'])
                            ->with(['turma:id,nome,turno,id_escola,id_serie', 'turma.escola:id,nome', 'turma.serie:id,nome'])
                            ->whereHas('turma', function (Builder $query) use ($escolasConsulta, $prefixos, $series, $turnos): void {
                                $query->whereIn('id_escola', $escolasConsulta)
                                    ->when($prefixos !== [], function (Builder $q) use ($prefixos): void {
                                        $q->whereHas('escola', function (Builder $escola) use ($prefixos): void {
                                            $escola->where(function (Builder $query) use ($prefixos): void {
                                                foreach ($prefixos as $prefixo) {
                                                    $query->orWhere('nome', 'like', $prefixo . '%');
                                                }
                                            });
                                        });
                                    })
                                    ->when($series !== [], fn (Builder $q): Builder => $q->whereIn('id_serie', $series))
                                    ->when($turnos !== [], fn (Builder $q): Builder => $q->whereIn('turno', $turnos));
                            })
                            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL)
                            ->where('status', Aluno::STATUS_MATRICULADO)
                            ->orderBy('nome')
                            ->get();

                        if ($alunos->isEmpty()) {
                            $mensagem = 'Nenhum aluno encontrado para os filtros informados.';
                        }
                    }

                    return [
                        'alunos' => $alunos,
                        'mensagem' => $mensagem,
                        'excecoes' => $get('transporte_excecoes_aluno_ids') ?? [],
                    ];
                })
                ->columnSpanFull(),
        ]);

        $wizard = Wizard::make([
            Step::make('Dados do evento')->schema([$eventoStep]),
            Step::make('Convidar participantes')->schema([$publicoStep]),
            $transporteStep,
        ])
            ->contained(false)
            ->nextAction(fn (Action $action): Action => $action->label('Próximo'))
            ->previousAction(fn (Action $action): Action => $action->label('Voltar'));

        if ($exibirAcoesFinais) {
            $wizard
                ->cancelAction(new HtmlString('<button type="button" class="evento-wizard-action evento-wizard-action--secondary" wire:click="'.$cancelWireMethod.'">Cancelar</button>'))
                ->submitAction(new HtmlString('<button type="submit" class="evento-wizard-action evento-wizard-action--primary">Criar evento</button>'));
        }

        return [$wizard->columnSpanFull()];
    }

    public static function dadosParaEdicao(EventoCalendario $evento, array $data): array
    {
        $evento->loadMissing(['publicoAlvo.funcoesAdministrativas', 'publicoRegras', 'publicoExcecoes']);
        $inicio = $evento->data_inicio->format('H:i');
        $fim = $evento->data_fim->format('H:i');

        return [
            ...$data,
            'data_evento' => $evento->data_inicio->toDateString(),
            'hora_inicio' => $inicio,
            'hora_fim' => $fim,
            'periodo' => self::periodoCorrespondente($inicio, $fim),
            'categoria' => $evento->categoria?->value ?? 'administrativo',
            'categoria_detalhe' => $evento->categoria_detalhe,
            'endereco_mapa' => $evento->endereco_mapa,
            'latitude' => $evento->latitude,
            'longitude' => $evento->longitude,
            'enviar_escolas_especificas' => ! $evento->enviar_todas_escolas,
            'escolas_agendadas' => app(EventoCalendarioEscolaService::class)->paraFormulario($evento),
            'selecionar_todas_escolas_filtro' => false,
            'escolas_filtro_ids' => $evento->escolasAgendadas->pluck('escola_id')->map(fn ($id): int => (int) $id)->all(),
            'turnos_filtro' => self::turnosParaPeriodo((string) self::periodoCorrespondente($inicio, $fim)),
            'precisa_transporte_grupo' => $evento->escolasAgendadas->contains('precisa_transporte', true),
            'precisa_transporte_evento' => $evento->escolasAgendadas->contains('precisa_transporte', true) ? 'sim' : 'nao',
            'publico_tipo' => 'segmentado',
            'funcoes_administrativas_ids' => [],
            'publico_regras' => $evento->publicoRegras->map(fn ($regra): array => $regra->filtros ?? [])->values()->all(),
            'publico_escola_ids' => data_get($evento->publicoRegras->first()?->filtros, 'escola_ids', []),
            'publico_funcao_ids' => data_get($evento->publicoRegras->first()?->filtros, 'funcao_ids', []),
            'publico_turnos' => data_get($evento->publicoRegras->first()?->filtros, 'turnos', []),
            'publico_serie_ids' => data_get($evento->publicoRegras->first()?->filtros, 'serie_ids', []),
            'publico_componente_ids' => data_get($evento->publicoRegras->first()?->filtros, 'componente_ids', []),
            'publico_excecoes_ids' => $evento->publicoExcecoes->pluck('user_id')->map(fn ($id): int => (int) $id)->all(),
            'transporte_excecoes_aluno_ids' => app(\App\Services\Dashboard\EventoCalendarioSnapshotService::class)
                ->excecoesParaFormulario($evento),
        ];
    }

    public static function periodoCorrespondente(string $inicio, string $fim): ?string
    {
        foreach (self::PERIODOS as $periodo => $horarios) {
            if ($horarios['inicio'] === $inicio && $horarios['fim'] === $fim) {
                return $periodo;
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private static function schoolOptions(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $context = app(DashboardUserContextFactory::class)->make($user);
        $query = Escola::query()->where('ativo', true)->orderBy('nome');

        if (! $context->escopoGlobal) {
            $query->whereKey($context->escolaIds);
        }

        return $query->pluck('nome', 'id')->all();
    }

    private static function schoolLabel(?User $user, mixed $escolaId): string
    {
        if (! filled($escolaId)) {
            return 'Nova escola';
        }

        return (string) (self::schoolOptions($user)[(int) $escolaId] ?? 'Escola não encontrada');
    }

    /** @return array<string, string> */
    private static function turnoOptions(): array
    {
        return [
            'manha' => 'Manhã',
            'tarde' => 'Tarde',
            'noite' => 'Noite',
            'integral' => 'Integral',
        ];
    }

    /** @return list<string> */
    private static function turnosParaPeriodo(string $periodo): array
    {
        return match ($periodo) {
            'manha' => ['manha'],
            'tarde' => ['tarde'],
            'noite' => ['noite'],
            'dia_todo' => ['manha', 'tarde', 'integral'],
            default => [],
        };
    }

    /** @return list<string> */
    private static function turnosPorHorario(mixed $inicio, mixed $fim): array
    {
        if (! preg_match('/^(\d{2}):(\d{2})/', (string) $inicio, $inicioPartes)
            || ! preg_match('/^(\d{2}):(\d{2})/', (string) $fim, $fimPartes)) {
            return [];
        }

        $inicioMinutos = ((int) $inicioPartes[1] * 60) + (int) $inicioPartes[2];
        $fimMinutos = ((int) $fimPartes[1] * 60) + (int) $fimPartes[2];

        if ($fimMinutos <= $inicioMinutos) {
            return [];
        }

        $turnos = [];

        if ($inicioMinutos < 750 && $fimMinutos > 360) {
            $turnos[] = 'manha';
        }

        if ($inicioMinutos < 1110 && $fimMinutos > 750) {
            $turnos[] = 'tarde';
        }

        if ($fimMinutos > 1110) {
            $turnos[] = 'noite';
        }

        if ($inicioMinutos <= 480 && $fimMinutos >= 1050) {
            $turnos[] = 'integral';
        }

        return array_values(array_unique($turnos));
    }

    /** @return array<int, string> */
    private static function seriesFilterOptions(
        ?User $user,
        mixed $selecionarTodas,
        mixed $escolaIds,
    ): array
    {
        $idsAcessiveis = array_keys(self::schoolOptions($user));
        $escolaIds = filter_var($selecionarTodas, FILTER_VALIDATE_BOOLEAN)
            ? $idsAcessiveis
            : collect(is_iterable($escolaIds) ? $escolaIds : [])->map(fn ($id): int => (int) $id)->intersect($idsAcessiveis)->all();

        return Serie::query()
            ->whereHas('turmas', fn (Builder $query): Builder => $query->whereIn('id_escola', $escolaIds))
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }

    /** @return array<int, string> */
    private static function turmasFilterOptions(
        ?User $user,
        mixed $selecionarTodas,
        mixed $escolaIds,
        mixed $serieIds,
        mixed $turnos,
    ): array {
        $idsAcessiveis = array_keys(self::schoolOptions($user));
        $escolas = filter_var($selecionarTodas, FILTER_VALIDATE_BOOLEAN)
            ? $idsAcessiveis
            : collect(is_iterable($escolaIds) ? $escolaIds : [])->map(fn ($id): int => (int) $id)->intersect($idsAcessiveis)->all();
        $series = collect(is_iterable($serieIds) ? $serieIds : [])->map(fn ($id): int => (int) $id)->filter()->all();
        $turnos = collect(is_iterable($turnos) ? $turnos : [])->map(fn ($turno): string => (string) $turno)->filter()->all();

        if ($escolas === []) {
            return [];
        }

        return Turma::query()
            ->whereIn('id_escola', $escolas)
            ->when($series !== [], fn (Builder $query): Builder => $query->whereIn('id_serie', $series))
            ->when($turnos !== [], fn (Builder $query): Builder => $query->whereIn('turno', $turnos))
            ->with(['serie:id,nome', 'escola:id,nome'])
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(fn (Turma $turma): array => [
                (int) $turma->id => collect([$turma->escola?->nome, $turma->serie?->nome, $turma->nome, self::turnoOptions()[$turma->turno] ?? $turma->turno])
                    ->filter()
                    ->implode(' - '),
            ])
            ->all();
    }

    /** @return array<int|string, string> */
    private static function regraPublico(Get $get): array
    {
        $regra = [
            'escola_ids' => $get('publico_escola_ids') ?? [],
            'funcao_ids' => $get('publico_funcao_ids') ?? [],
            'turnos' => $get('publico_turnos') ?? [],
            'serie_ids' => $get('publico_serie_ids') ?? [],
            'componente_ids' => $get('publico_componente_ids') ?? [],
        ];

        return collect($regra)->contains(function (mixed $valor): bool {
            return is_iterable($valor) ? count($valor) > 0 : filled($valor);
        }) ? [$regra] : [];
    }

    private static function sincronizarRegraPublico(Get $get, Set $set): void
    {
        $set('publico_regras', self::regraPublico($get));
        $set('publico_preview_aplicado', false);
    }

    /** @return array<int|string, string> */
    private static function funcoesConviteOptions(): array
    {
        return FuncaoAdministrativa::query()
            ->where('ativo', true)
            ->where(function (Builder $funcoes): void {
                $funcoes
                    ->where('exige_professor', true)
                    ->orWhere('direcao_escolar', true)
                    ->orWhere('coordenacao_pedagogica', true)
                    ->orWhere('secretaria_escolar', true)
                    ->orWhereIn('nome', [
                        'Manutenção',
                        'Obras',
                        'Motorista',
                        'Transporte',
                        'Assessoria Pedagógica',
                    ]);
            })
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }

    private static function atualizarDistribuicao(
        ?User $user,
        Get $get,
        Set $set,
        ?string $horaInicio = null,
        ?string $horaFim = null,
    ): void {
        if (! $user || ! (bool) $get('enviar_escolas_especificas')) {
            return;
        }

        $horaInicio ??= filled($get('hora_inicio')) ? (string) $get('hora_inicio') : null;
        $horaFim ??= filled($get('hora_fim')) ? (string) $get('hora_fim') : null;

        if (! $horaInicio || ! $horaFim) {
            return;
        }

        try {
            $linhas = app(EventoCalendarioEscolaService::class)->gerarPorFiltros([
                'selecionar_todas_escolas' => (bool) $get('selecionar_todas_escolas_filtro'),
                'escola_ids' => $get('escolas_filtro_ids'),
                'serie_ids' => $get('series_filtro_ids'),
                'turnos' => $get('turnos_filtro'),
                'turma_ids' => $get('turmas_filtro_ids'),
                'prefixos' => $get('transporte_prefixos'),
                'precisa_transporte' => (bool) $get('precisa_transporte_grupo'),
            ], $user, $horaInicio, $horaFim);

            $set('escolas_agendadas', $linhas);
        } catch (\Illuminate\Validation\ValidationException) {
            $set('escolas_agendadas', []);
        }
    }

    private static function resumoDistribuicao(?User $user, mixed $linhas): HtmlString
    {
        $nomes = collect(self::schoolOptions($user));
        $linhas = collect(is_iterable($linhas) ? $linhas : [])
            ->filter(fn ($linha): bool => is_array($linha) && filled($linha['escola_id'] ?? null))
            ->filter(fn (array $linha): bool => $nomes->has((int) $linha['escola_id']))
            ->values();

        if ($linhas->isEmpty()) {
            return new HtmlString('<span class="text-sm text-gray-500">Aplique os filtros para carregar as escolas participantes.</span>');
        }

        $linhas = $linhas->map(function (array $linha) use ($user): array {
            if (($linha['precisa_transporte'] ?? false) && ! is_numeric($linha['quantidade_estimada_transporte'] ?? null)) {
                $linha['quantidade_estimada_transporte'] = app(EventoCalendarioEscolaService::class)->estimarParaFormulario(
                    $user,
                    $linha['escola_id'] ?? null,
                    $linha['escopo_transporte'] ?? null,
                    $linha['series_ids'] ?? [],
                    $linha['turmas_ids'] ?? [],
                );
            }

            return $linha;
        });

        $listaEscolas = $linhas
            ->map(fn (array $linha): string => '<li>'.e((string) ($nomes[(int) $linha['escola_id']] ?? 'Escola não encontrada')).'</li>')
            ->implode('');
        $comTransporte = $linhas->where('precisa_transporte', true);
        $totalAlunos = (int) $comTransporte->sum(fn (array $linha): int => (int) ($linha['quantidade_estimada_transporte'] ?? 0));
        $listaTransporte = $comTransporte
            ->map(fn (array $linha): string => '<li>'.e((string) ($nomes[(int) $linha['escola_id']] ?? 'Escola não encontrada')).': '.(int) ($linha['quantidade_estimada_transporte'] ?? 0).' estudante(s)</li>')
            ->implode('');
        $transporte = $comTransporte->isEmpty()
            ? '<div class="rounded-lg border border-gray-200 p-3"><strong>Transporte</strong><br><span class="text-sm text-gray-500">Não solicitado</span></div>'
            : '<details class="relative rounded-lg border border-gray-200 p-3"><summary class="cursor-pointer"><strong>'.e((string) $totalAlunos).' estudante(s)</strong><br><span class="text-sm text-gray-500">Estimativa para transporte</span></summary><div class="absolute left-0 top-full z-20 mt-2 max-h-64 min-w-full overflow-y-auto rounded-lg border border-gray-200 bg-white p-3 shadow-lg"><ul class="list-disc pl-5 text-sm">'.$listaTransporte.'</ul></div></details>';

        return new HtmlString(
            '<div class="grid gap-3 md:grid-cols-2">'
            .'<details class="relative rounded-lg border border-gray-200 p-3"><summary class="cursor-pointer"><strong>'.$linhas->count().' escola(s)</strong><br><span class="text-sm text-gray-500">Clique para conferir a lista</span></summary><div class="absolute left-0 top-full z-20 mt-2 max-h-64 min-w-full overflow-y-auto rounded-lg border border-gray-200 bg-white p-3 shadow-lg"><ul class="list-disc pl-5 text-sm">'.$listaEscolas.'</ul></div></details>'
            .$transporte
            .'</div>',
        );
    }

    /** @return array<int, string> */
    private static function seriesOptions(?User $user, mixed $escolaId): array
    {
        if (! filled($escolaId) || ! array_key_exists((int) $escolaId, self::schoolOptions($user))) {
            return [];
        }

        return Serie::query()
            ->whereHas('turmas', fn (Builder $query): Builder => $query->where('id_escola', (int) $escolaId))
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }

    /** @return array<int, string> */
    private static function turmasOptions(?User $user, mixed $escolaId): array
    {
        if (! filled($escolaId) || ! array_key_exists((int) $escolaId, self::schoolOptions($user))) {
            return [];
        }

        return Turma::query()
            ->where('id_escola', (int) $escolaId)
            ->with('serie:id,nome')
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(fn (Turma $turma): array => [
                (int) $turma->id => collect([$turma->serie?->nome, $turma->nome])
                    ->filter()
                    ->implode(' - '),
            ])
            ->all();
    }
}
