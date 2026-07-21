<?php

namespace App\Filament\Admin\Resources\EventosCalendario\Schemas;

use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\Enums\EventoCalendarioCor;
use App\Models\Enums\EventoCalendarioTransporteEscopo;
use App\Models\Escola;
use App\Models\EventoCalendario;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Services\Dashboard\EventoCalendarioEscolaService;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

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

    /** @return array<int, \Filament\Schemas\Components\Component> */
    public static function components(?User $user): array
    {
        return [
            Section::make('Evento')
                ->columns(2)
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
                    Select::make('categoria')
                        ->label('Categoria')
                        ->options(collect(EventoCalendarioCategoria::cases())->mapWithKeys(
                            fn ($item): array => [$item->value => $item->label()],
                        )->all())
                        ->required()
                        ->native(false),
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
                        ->placeholder('Preencher horários automaticamente')
                        ->helperText('O período apenas sugere os horários. Você poderá alterá-los livremente.')
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            $periodo = self::PERIODOS[(string) $state] ?? null;

                            if ($periodo) {
                                $set('hora_inicio', $periodo['inicio']);
                                $set('hora_fim', $periodo['fim']);
                            }
                        })
                        ->native(false),
                    TimePicker::make('hora_inicio')
                        ->label('Horário inicial')
                        ->seconds(false)
                        ->required(),
                    TimePicker::make('hora_fim')
                        ->label('Horário final')
                        ->seconds(false)
                        ->after('hora_inicio')
                        ->required(),
                    Select::make('cor')
                        ->label('Identificação visual')
                        ->options(collect(EventoCalendarioCor::cases())->mapWithKeys(
                            fn ($item): array => [$item->value => $item->label()],
                        )->all())
                        ->default(EventoCalendarioCor::AZUL->value)
                        ->required()
                        ->native(false),
                    Toggle::make('inserir_link')
                        ->label('Inserir link?')
                        ->live()
                        ->afterStateUpdated(function (bool $state, Set $set): void {
                            if (! $state) {
                                $set('link_acao', null);
                                $set('texto_botao', null);
                            }
                        })
                        ->columnSpanFull(),
                ]),

            Section::make('Link de ação')
                ->columns(2)
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => (bool) $get('inserir_link'))
                ->schema([
                    TextInput::make('link_acao')
                        ->label('Link de ação')
                        ->placeholder('https://... ou /admin/...')
                        ->required(fn (Get $get): bool => (bool) $get('inserir_link'))
                        ->maxLength(2048)
                        ->rule(static function (): Closure {
                            return static function (string $attribute, mixed $value, Closure $fail): void {
                                if (blank($value)) {
                                    return;
                                }

                                $link = trim((string) $value);
                                $relativo = str_starts_with($link, '/') && ! str_starts_with($link, '//');
                                $protocolo = strtolower((string) parse_url($link, PHP_URL_SCHEME));

                                if (! $relativo && ! in_array($protocolo, ['http', 'https'], true)) {
                                    $fail('Informe uma URL HTTP(S) ou um caminho interno iniciado por /.');
                                }
                            };
                        })
                        ->columnSpanFull(),
                    TextInput::make('texto_botao')
                        ->label('Texto do botão')
                        ->maxLength(80),
                ]),

            Section::make('Distribuição por escola')
                ->description('Defina as escolas participantes e, quando necessário, horários e transporte específicos por unidade.')
                ->columnSpanFull()
                ->schema([
                    Toggle::make('enviar_todas_escolas')
                        ->label('Enviar para todas as escolas do meu escopo')
                        ->helperText('Para acesso global, inclui todas as escolas ativas do sistema.')
                        ->default(true)
                        ->live()
                        ->columnSpanFull(),

                    Repeater::make('escolas_agendadas')
                        ->label('Escolas específicas')
                        ->visible(fn (Get $get): bool => ! (bool) $get('enviar_todas_escolas'))
                        ->required(fn (Get $get): bool => ! (bool) $get('enviar_todas_escolas'))
                        ->minItems(1)
                        ->defaultItems(0)
                        ->addActionLabel('+ Escola')
                        ->reorderable(false)
                        ->cloneable(false)
                        ->itemLabel(fn (array $state): string => self::schoolLabel($state['escola_id'] ?? null))
                        ->schema([
                            Select::make('escola_id')
                                ->label('Escola')
                                ->options(fn (): array => self::schoolOptions($user))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->distinct()
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set): void {
                                    if (blank($get('hora_inicio'))) {
                                        $set('hora_inicio', $get('../../hora_inicio'));
                                    }

                                    if (blank($get('hora_fim'))) {
                                        $set('hora_fim', $get('../../hora_fim'));
                                    }

                                    $set('series_ids', []);
                                    $set('turmas_ids', []);
                                })
                                ->native(false)
                                ->columnSpanFull(),
                            TimePicker::make('hora_inicio')
                                ->label('Horário inicial nesta escola')
                                ->seconds(false)
                                ->required(),
                            TimePicker::make('hora_fim')
                                ->label('Horário final nesta escola')
                                ->seconds(false)
                                ->after('hora_inicio')
                                ->required(),
                            Toggle::make('precisa_transporte')
                                ->label('Precisa de transporte?')
                                ->live()
                                ->afterStateUpdated(function (bool $state, Set $set): void {
                                    if (! $state) {
                                        $set('escopo_transporte', null);
                                        $set('series_ids', []);
                                        $set('turmas_ids', []);
                                    }
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
                                })
                                ->native(false)
                                ->columnSpanFull(),
                            Select::make('series_ids')
                                ->label('Séries')
                                ->options(fn (Get $get): array => self::seriesOptions($get('escola_id')))
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->required(fn (Get $get): bool => $get('escopo_transporte') === EventoCalendarioTransporteEscopo::SERIES->value)
                                ->visible(fn (Get $get): bool => $get('escopo_transporte') === EventoCalendarioTransporteEscopo::SERIES->value)
                                ->live()
                                ->columnSpanFull(),
                            Select::make('turmas_ids')
                                ->label('Turmas')
                                ->options(fn (Get $get): array => self::turmasOptions($get('escola_id')))
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->required(fn (Get $get): bool => $get('escopo_transporte') === EventoCalendarioTransporteEscopo::TURMAS->value)
                                ->visible(fn (Get $get): bool => $get('escopo_transporte') === EventoCalendarioTransporteEscopo::TURMAS->value)
                                ->live()
                                ->columnSpanFull(),
                            Placeholder::make('estimativa_transporte')
                                ->label('Estimativa para transporte')
                                ->content(function (Get $get) use ($user): string {
                                    if (! $get('precisa_transporte')) {
                                        return 'Transporte não solicitado.';
                                    }

                                    $total = app(EventoCalendarioEscolaService::class)->estimarParaFormulario(
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
                        ->columnSpanFull(),
                ]),

        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public static function dadosParaEdicao(EventoCalendario $evento, array $data): array
    {
        $inicio = $evento->data_inicio->format('H:i');
        $fim = $evento->data_fim->format('H:i');

        return [
            ...$data,
            'data_evento' => $evento->data_inicio->toDateString(),
            'hora_inicio' => $inicio,
            'hora_fim' => $fim,
            'periodo' => self::periodoCorrespondente($inicio, $fim),
            'inserir_link' => filled($evento->link_acao),
            'escolas_agendadas' => app(EventoCalendarioEscolaService::class)->paraFormulario($evento),
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

    private static function schoolLabel(mixed $escolaId): string
    {
        if (! filled($escolaId)) {
            return 'Nova escola';
        }

        return (string) (Escola::query()->whereKey((int) $escolaId)->value('nome') ?? 'Escola não encontrada');
    }

    /** @return array<int, string> */
    private static function seriesOptions(mixed $escolaId): array
    {
        if (! filled($escolaId)) {
            return [];
        }

        return Serie::query()
            ->whereHas('turmas', fn (Builder $query): Builder => $query->where('id_escola', (int) $escolaId))
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }

    /** @return array<int, string> */
    private static function turmasOptions(mixed $escolaId): array
    {
        if (! filled($escolaId)) {
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
