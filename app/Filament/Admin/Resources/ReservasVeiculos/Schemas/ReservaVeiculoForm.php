<?php

namespace App\Filament\Admin\Resources\ReservasVeiculos\Schemas;

use App\Models\Escola;
use App\Models\ReservaVeiculo;
use App\Models\User;
use App\Services\Dashboard\ReservaVeiculoService;
use Filament\Forms\Components\Checkbox;
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

                    Checkbox::make('reservar_varios_dias')
                        ->label('Reservar para vários dias')
                        ->helperText('O mesmo horário será reservado em todos os dias do intervalo, por até 31 dias.')
                        ->default(false)
                        ->live(),

                    DatePicker::make('data_inicial')
                        ->label('Data da reserva')
                        ->required()
                        ->minDate(today())
                        ->native()
                        ->displayFormat('d/m/Y')
                        ->live()
                        ->columnSpan(fn (Get $get): int => $get('reservar_varios_dias') ? 1 : 2),

                    DatePicker::make('data_final')
                        ->label('Data final')
                        ->required(fn (Get $get): bool => (bool) $get('reservar_varios_dias'))
                        ->minDate(fn (Get $get): mixed => $get('data_inicial') ?: today())
                        ->native()
                        ->displayFormat('d/m/Y')
                        ->visible(fn (Get $get): bool => (bool) $get('reservar_varios_dias'))
                        ->live(),

                    TimePicker::make('hora_inicio')
                        ->label('Horário inicial')
                        ->required()
                        ->seconds(false)
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
                ->description('A lista considera todas as datas e o intervalo de horário informados.')
                ->schema([
                    Select::make('veiculo_transporte_id')
                        ->label('Veículo disponível')
                        ->options(fn (Get $get): array => app(ReservaVeiculoService::class)
                            ->veiculosDisponiveis(
                                $usuario,
                                $get('data_inicial'),
                                $get('reservar_varios_dias') ? $get('data_final') : $get('data_inicial'),
                                $get('hora_inicio'),
                                $get('hora_fim'),
                            ))
                        ->searchable()
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

                Select::make('escola_id')
                    ->label('Escola ou CMEI')
                    ->options(fn (): array => Escola::query()
                        ->ativas()
                        ->orderBy('nome')
                        ->pluck('nome', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
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

}
