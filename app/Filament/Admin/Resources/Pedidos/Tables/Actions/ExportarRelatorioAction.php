<?php

namespace App\Filament\Admin\Resources\Pedidos\Tables\Actions;

use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\Escola;
use App\Models\TipoManutencao;
use App\Models\TipoStatus;
use App\Services\PedidoService;
use App\Services\ProfilePreviewService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Colors\Color;
use Illuminate\Validation\ValidationException;

class ExportarRelatorioAction
{
    public static function make(): Action
    {
        return Action::make('relatorio_geral')
            ->label('Exportar Relatório')
            ->icon('heroicon-o-document-chart-bar')
            ->color(Color::hex('#102b86'))
            ->visible(fn (): bool => static::usuarioEfetivo()?->hasPermissionLike('exportar relatorios') ?? false)
            ->schema(static::schema())
            ->modalHeading('Exportar Relatório de Pedidos')
            ->modalDescription('Configure os filtros. O período é obrigatório para gerar o PDF.')
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
                        ->required()
                        ->validationAttribute('data inicial')
                        ->displayFormat('d/m/Y')
                        ->native(false)
                        ->helperText('Informe o início do período para exportação.')
                        ->maxDate(fn (Get $get) => $get('data_fim') ?: now()),

                    DatePicker::make('data_fim')
                        ->label('Data final')
                        ->required()
                        ->validationAttribute('data final')
                        ->displayFormat('d/m/Y')
                        ->native(false)
                        ->helperText('Informe o fim do período para exportação.')
                        ->minDate(fn (Get $get) => $get('data_inicio'))
                        ->maxDate(now()),

                    Select::make('escola_id')
                        ->label('Escola')
                        ->options(function (): array {
                            $service = app(PedidoService::class);
                            $user = static::usuarioEfetivo();
                            $escolaIds = $service->escolaIdsParaEscopo($user);

                            return Escola::query()
                                ->where('ativo', true)
                                ->when(
                                    ! $service->podeVerTodosOsPedidos($user),
                                    fn ($query) => $query->whereIn('id', $escolaIds)
                                )
                                ->orderBy('nome')
                                ->pluck('nome', 'id')
                                ->toArray();
                        })
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

                    Select::make('tipo_registro')
                        ->label('Tipo de registro')
                        ->options([
                            'principais' => 'Pedidos principais',
                            'adicionais' => 'Pedidos adicionais',
                            'todos' => 'Todos',
                        ])
                        ->placeholder('Todos'),

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
                                ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                                ->toArray()
                        )
                        ->placeholder('Todas as prioridades'),
                ]),
        ];
    }

    private static function actionHandler(): \Closure
    {
        return function (array $data): mixed {
            if (empty($data['data_inicio']) || empty($data['data_fim'])) {
                throw ValidationException::withMessages([
                    'data_inicio' => 'Informe a data inicial para exportar o relatório.',
                    'data_fim' => 'Informe a data final para exportar o relatório.',
                ]);
            }

            $filtros = array_filter($data, fn ($v) => $v !== null && $v !== '');

            foreach (['data_inicio', 'data_fim'] as $campo) {
                if (! empty($filtros[$campo])) {
                    $filtros[$campo] = \Carbon\Carbon::parse($filtros[$campo])->format('Y-m-d');
                }
            }

            return redirect()->away(route('pedidos.relatorio-geral', $filtros));
        };
    }

    private static function usuarioEfetivo(): ?\App\Models\User
    {
        return app(ProfilePreviewService::class)->effectiveUser();
    }
}
