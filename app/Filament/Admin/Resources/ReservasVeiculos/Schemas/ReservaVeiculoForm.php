<?php

namespace App\Filament\Admin\Resources\ReservasVeiculos\Schemas;

use App\Models\Escola;
use App\Models\ReservaVeiculo;
use App\Models\User;
use App\Services\Dashboard\ReservaVeiculoService;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

final class ReservaVeiculoForm
{
    /** @return array<int, Section> */
    public static function criacao(User $usuario): array
    {
        return [
            Section::make('Solicitante e período')
                ->description('O solicitante é identificado automaticamente pelo usuário conectado.')
                ->columns(2)
                ->schema([
                    TextInput::make('servidor')
                        ->label('Servidor')
                        ->default($usuario->name)
                        ->disabled()
                        ->dehydrated(false),

                    Select::make('repeticao')
                        ->label('Repetição')
                        ->options([
                            'nenhuma' => 'Não se repete',
                            'diaria' => 'Todos os dias',
                            'semanal' => 'Semanalmente',
                            'mensal' => 'Mensalmente',
                            'personalizada' => 'Personalizar',
                        ])
                        ->default('nenhuma')
                        ->native(false)
                        ->live(),

                    CheckboxList::make('dias_semana')
                        ->label('Repetir nos dias')
                        ->options(['1' => 'Seg', '2' => 'Ter', '3' => 'Qua', '4' => 'Qui', '5' => 'Sex', '6' => 'Sáb', '7' => 'Dom'])
                        ->helperText(fn (Get $get): string => $get('repeticao') === 'personalizada'
                            ? 'A reserva será criada nos dias selecionados, dentro do intervalo informado.'
                            : 'Na repetição semanal, sem seleção, será usado o dia da data inicial.')
                        ->default(fn (Get $get): array => filled($get('data_inicial'))
                            ? [(string) CarbonImmutable::parse($get('data_inicial'))->dayOfWeekIso]
                            : [])
                        ->columns(7)
                        ->live()
                        ->visible(fn (Get $get): bool => in_array($get('repeticao'), ['semanal', 'personalizada'], true))
                        ->required(fn (Get $get): bool => $get('repeticao') === 'personalizada')
                        ->columnSpanFull(),

                    DatePicker::make('data_inicial')
                        ->label('Data da reserva')
                        ->required()
                        ->minDate(today())
                        ->native()
                        ->displayFormat('d/m/Y')
                        ->live()
                        ->columnSpan(fn (Get $get): int => $get('repeticao') !== 'nenhuma' ? 1 : 2),

                    DatePicker::make('data_final')
                        ->label('Data final')
                        ->required(fn (Get $get): bool => $get('repeticao') !== 'nenhuma')
                        ->minDate(fn (Get $get): mixed => $get('data_inicial') ?: today())
                        ->native()
                        ->displayFormat('d/m/Y')
                        ->visible(fn (Get $get): bool => $get('repeticao') !== 'nenhuma')
                        ->live(),

                    TimePicker::make('hora_inicio')
                        ->label('Horário inicial')
                        ->required()
                        ->seconds(false)
                        ->extraInputAttributes(fn (Get $get): array => self::atributosHorarioMinimo(
                            $get('data_inicial'),
                        ))
                        ->native()
                        ->live(),

                    TimePicker::make('hora_fim')
                        ->label('Horário final')
                        ->required()
                        ->seconds(false)
                        ->native()
                        ->after('hora_inicio')
                        ->live(),
                ]),

            self::destinoEAtividade(),

            Section::make('Veículo')
                ->description('A lista considera todas as ocorrências e o intervalo de horário informados.')
                ->schema([
                    Select::make('veiculo_transporte_id')
                        ->label('Veículo disponível')
                        ->options(fn (Get $get): array => app(ReservaVeiculoService::class)
                            ->veiculosDisponiveis(
                                $usuario,
                                $get('data_inicial'),
                                $get('repeticao') !== 'nenhuma'
                                    ? $get('data_final')
                                    : $get('data_inicial'),
                                $get('hora_inicio'),
                                $get('hora_fim'),
                                null,
                                [
                                    'repeticao' => $get('repeticao') ?? 'nenhuma',
                                    'dias_semana' => $get('dias_semana') ?? [],
                                    'fim_repeticao' => 'data',
                                ],
                            ))
                        ->searchable()
                        ->noOptionsMessage(fn (Get $get): string => $get('repeticao') !== 'nenhuma'
                            ? 'Não há veículos disponíveis para os dias e o horário selecionados.'
                            : 'Não há veículos disponíveis para o dia e o horário selecionados.')
                        ->noSearchResultsMessage('Nenhum veículo disponível corresponde à busca.')
                        ->native(false)
                        ->required()
                        ->helperText('Se nenhum veículo aparecer, revise as datas e os horários ou cadastre a frota.'),
                ]),
        ];
    }

    /** @return array<int, Section> */
    public static function edicao(User $usuario, ReservaVeiculo $reserva): array
    {
        return [
            Section::make('Solicitante e período')
                ->columns(2)
                ->schema([
                    TextInput::make('servidor')
                        ->label('Servidor')
                        ->default($reserva->usuario?->name)
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),

                    DatePicker::make('data')
                        ->label('Data')
                        ->required()
                        ->minDate(today())
                        ->native()
                        ->displayFormat('d/m/Y')
                        ->live(),

                    TimePicker::make('hora_inicio')
                        ->label('Horário inicial')
                        ->required()
                        ->seconds(false)
                        ->extraInputAttributes(fn (Get $get): array => self::atributosHorarioMinimo(
                            $get('data'),
                        ))
                        ->native()
                        ->live(),

                    TimePicker::make('hora_fim')
                        ->label('Horário final')
                        ->required()
                        ->seconds(false)
                        ->native()
                        ->after('hora_inicio')
                        ->live(),
                ]),

            self::destinoEAtividade(),

            Section::make('Veículo')
                ->schema([
                    Select::make('veiculo_transporte_id')
                        ->label('Veículo disponível')
                        ->options(fn (Get $get): array => app(ReservaVeiculoService::class)
                            ->veiculosDisponiveis(
                                $usuario,
                                $get('data'),
                                $get('data'),
                                $get('hora_inicio'),
                                $get('hora_fim'),
                                $reserva->id,
                            ))
                        ->searchable()
                        ->noOptionsMessage('Não há veículos disponíveis para o dia e o horário selecionados.')
                        ->noSearchResultsMessage('Nenhum veículo disponível corresponde à busca.')
                        ->native(false)
                        ->required(),
                ]),
        ];
    }

    private static function destinoEAtividade(): Section
    {
        return Section::make('Destino e atividade')
            ->columns(2)
            ->schema([
                Select::make('tipo_local')
                    ->label('Tipo de local')
                    ->options([
                        'escola' => 'Escola ou CMEI',
                        'outros' => 'Outro local',
                    ])
                    ->default('escola')
                    ->required()
                    ->native(false)
                    ->live(),

                Select::make('escola_ids')
                    ->label('Escola ou CMEI')
                    ->options(fn (): array => Escola::query()
                        ->ativas()
                        ->orderBy('nome')
                        ->pluck('nome', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->native(false)
                    ->required(fn (Get $get): bool => $get('tipo_local') === 'escola')
                    ->visible(fn (Get $get): bool => $get('tipo_local') === 'escola'),

                TextInput::make('local_outro')
                    ->label('Local')
                    ->required(fn (Get $get): bool => $get('tipo_local') === 'outros')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('tipo_local') === 'outros'),

                Textarea::make('atividade')
                    ->label('Atividade a ser realizada')
                    ->placeholder('Descreva brevemente o objetivo do deslocamento.')
                    ->rows(3)
                    ->maxLength(500)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    /** @return array{min: string}|array{} */
    private static function atributosHorarioMinimo(mixed $data): array
    {
        if ((string) $data !== today()->toDateString()) {
            return [];
        }

        $minimo = now()->addMinutes(20);
        if ($minimo->second > 0 || $minimo->micro > 0) {
            $minimo = $minimo->addMinute()->startOfMinute();
        }

        return ['min' => $minimo->format('H:i')];
    }
}
