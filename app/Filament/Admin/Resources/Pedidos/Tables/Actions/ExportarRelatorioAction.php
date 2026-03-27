<?php

namespace App\Filament\Admin\Resources\Pedidos\Tables\Actions;

use App\Models\TipoStatus;
use App\Models\TipoManutencao;
use App\Models\Enums\NivelEmergenciaPedido;
use Filament\Actions\Action;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Colors\Color;

class ExportarRelatorioAction
{
    public static function make(): Action
    {
        return Action::make('relatorio_geral')
            ->label('Exportar Relatório')
            ->icon('heroicon-o-document-chart-bar')
            ->color(Color::hex('#102b86'))
            ->schema(static::schema())
            ->modalHeading('Exportar Relatório de Pedidos')
            ->modalDescription('Configure os filtros e clique em Exportar para gerar o PDF.')
            ->modalSubmitActionLabel('Exportar PDF')
            ->modalIcon('heroicon-o-document-chart-bar')
            ->modalWidth('2xl')
            ->action(static::actionHandler());
    }

    private static function schema(): array
    {
        return [
            Grid::make()
                ->columns(2)
                ->schema([
                    DatePicker::make('data_inicio')
                        ->label('Data inicial')
                        ->displayFormat('d/m/Y')
                        ->native(false)
                        ->maxDate(fn(Get $get) => $get('data_fim') ?: now()),

                    DatePicker::make('data_fim')
                        ->label('Data final')
                        ->displayFormat('d/m/Y')
                        ->native(false)
                        ->minDate(fn(Get $get) => $get('data_inicio'))
                        ->maxDate(now()),

                    Select::make('escola_id')
                        ->label('Escola')
                        ->options(
                            \App\Models\Escola::query()
                                ->where('ativo', true)
                                ->orderBy('nome')
                                ->pluck('nome', 'id')
                        )
                        ->searchable()
                        ->placeholder('Todas as escolas'),

                    Select::make('tipo_manutencao_id')
                        ->label('Tipo de Manutenção')
                        ->options(
                            TipoManutencao::query()
                                ->where('ativo', true)
                                ->orderBy('nome')
                                ->pluck('nome', 'id')
                        )
                        ->searchable()
                        ->placeholder('Todos os tipos'),

                    Select::make('tipo_status_id')
                        ->label('Status')
                        ->options(
                            TipoStatus::query()
                                ->where('ativo', true)
                                ->orderBy('nome')
                                ->pluck('nome', 'id')
                        )
                        ->searchable()
                        ->placeholder('Todos os status'),

                    Select::make('nivel_prioridade')
                        ->label('Prioridade')
                        ->options(
                            collect(NivelEmergenciaPedido::cases())
                                ->mapWithKeys(fn($case) => [$case->value => $case->label()])
                                ->toArray()
                        )
                        ->placeholder('Todas as prioridades'),
                ]),
        ];
    }

    private static function actionHandler(): \Closure
    {
        return function (array $data): mixed {
            $filtros = array_filter($data, fn($v) => $v !== null && $v !== '');

            foreach (['data_inicio', 'data_fim'] as $campo) {
                if (!empty($filtros[$campo])) {
                    $filtros[$campo] = \Carbon\Carbon::parse($filtros[$campo])->format('Y-m-d');
                }
            }

            return redirect()->away(route('pedidos.relatorio-geral', $filtros));
        };
    }
}