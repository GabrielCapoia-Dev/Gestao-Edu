<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use App\Models\FeedbackPedido as FeedbackPedidoModel;
use App\Models\User;
use Filament\Forms;
use Livewire\Attributes\Computed;
use Filament\Actions;
use App\Models\Enums\NivelEmergenciaPedido;

class FeedbackPedido extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $slug = 'feedback-pedidos';
    protected static string $view = 'filament.pages.feedback-pedido';
    protected static ?string $navigationGroup = 'Manutenção';

    // Estado de filtros que será observado pelo widget
    public array $chartFilters = [];

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Widgets\FeedbackMediaMensalChart::class,
            \App\Filament\Widgets\FeedbackQuantidadePorNotaChart::class,
        ];
    }

    public function canView(): bool
    {
        return User::authUser()->hasPermissionTo('Visualizar Feedback de Pedidos');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FeedbackPedidoModel::query()
                    ->with(['pedido.escola', 'pedido.tipoManutencao'])
            )
            ->paginated([10, 25, 50, 100])
            ->columns([
                Tables\Columns\TextColumn::make('pedido.numero_protocolo')
                    ->label('Protocolo')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('pedido.escola.nome')
                    ->label('Escola')
                    ->sortable(),

                Tables\Columns\TextColumn::make('valor')
                    ->label('Nota')
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state >= 4 => 'success',
                        $state >= 3 => 'warning',
                        default     => 'danger',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('descricao')
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Avaliado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('valor')
                    ->label('Nota')
                    ->options([
                        '1' => '⭐',
                        '2' => '⭐⭐',
                        '3' => '⭐⭐⭐',
                        '4' => '⭐⭐⭐⭐',
                        '5' => '⭐⭐⭐⭐⭐',
                    ])
                    ->query(function ($query, array $data) {
                        $this->updateChartFilters('valor', $data['value'] ?? null);
                        return $query->when($data['value'] ?? null, fn($q) => $q->where('valor', $data['value']));
                    }),

                Tables\Filters\SelectFilter::make('pedido.nivel_prioridade')
                    ->label('Nível de Prioridade')
                    ->options(
                        collect(NivelEmergenciaPedido::cases())
                            ->mapWithKeys(fn($case) => [
                                $case->value => $case->label(),
                            ])
                            ->toArray()
                    )
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        $this->updateChartFilters('nivel_prioridade', $value);

                        return $query->when(
                            $value,
                            fn($q) => $q->whereHas(
                                'pedido',
                                fn($subquery) => $subquery->where('nivel_prioridade', $value)
                            )
                        );
                    }),

                Tables\Filters\SelectFilter::make('pedido.tipo_manutencao_id')
                    ->label('Tipo de Manutenção')
                    ->options(
                        \App\Models\TipoManutencao::pluck('nome', 'id')->toArray()
                    )
                    ->query(function ($query, array $data) {
                        $this->updateChartFilters('tipo_manutencao_id', $data['value'] ?? null);
                        return $query->when($data['value'] ?? null, fn($q) => $q->whereHas('pedido', fn($subquery) => $subquery->where('tipo_manutencao_id', $data['value'])));
                    }),

                Tables\Filters\SelectFilter::make('pedido.escola_id')
                    ->label('Escola')
                    ->options(
                        \App\Models\Escola::pluck('nome', 'id')->toArray()
                    )
                    ->query(function ($query, array $data) {
                        $this->updateChartFilters('escola_id', $data['value'] ?? null);
                        return $query->when($data['value'] ?? null, fn($q) => $q->whereHas('pedido', fn($subquery) => $subquery->where('escola_id', $data['value'])));
                    }),

                Tables\Filters\SelectFilter::make('pedido.empresa_contratada_id')
                    ->label('Empresa Contratada')
                    ->options(
                        \App\Models\EmpresaContratada::pluck('nome', 'id')->toArray()
                    )
                    ->query(function ($query, array $data) {
                        $this->updateChartFilters('empresa_contratada_id', $data['value'] ?? null);
                        return $query->when($data['value'] ?? null, fn($q) => $q->whereHas('pedido', fn($subquery) => $subquery->where('empresa_contratada_id', $data['value'])));
                    }),

                Tables\Filters\Filter::make('mes')
                    ->label('Mês')
                    ->form([
                        Forms\Components\Select::make('mes')
                            ->label('Mês')
                            ->options([
                                '01' => 'Janeiro',
                                '02' => 'Fevereiro',
                                '03' => 'Março',
                                '04' => 'Abril',
                                '05' => 'Maio',
                                '06' => 'Junho',
                                '07' => 'Julho',
                                '08' => 'Agosto',
                                '09' => 'Setembro',
                                '10' => 'Outubro',
                                '11' => 'Novembro',
                                '12' => 'Dezembro',
                            ])
                            ->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        $this->updateChartFilters('mes', $data['mes'] ?? null);
                        return $query->when($data['mes'] ?? null, fn($q) => $q->whereMonth('created_at', $data['mes']));
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function updateChartFilters(string $filterKey, mixed $value): void
    {
        // Disparar evento de loading
        $this->dispatch('chart-loading-start');

        if ($value === null) {
            unset($this->chartFilters[$filterKey]);
        } else {
            $this->chartFilters[$filterKey] = $value;
        }

        $this->dispatch('update-chart-filters', filters: $this->chartFilters);

        // Disparar evento de fim de loading
        $this->dispatch('chart-loading-end');
    }

    public function getTotalAvaliacoes(): int
    {
        return $this->getFilteredTableQuery()->count();
    }

    public function getMediaGeral(): float
    {
        return round(
            $this->getFilteredTableQuery()->avg('valor') ?? 0,
            2
        );
    }

    public function getPercentualSatisfacao(): int
    {
        $query = $this->getFilteredTableQuery();

        $total = (clone $query)->count();

        if ($total === 0) {
            return 0;
        }

        $positivos = (clone $query)
            ->where('valor', '>=', 3)
            ->count();

        return round(($positivos / $total) * 100);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_geral')
                ->label('📊 Relatório Geral')
                ->url(fn() => $this->gerarUrlExportacao('geral'))
                ->openUrlInNewTab(),

            Actions\Action::make('export_listagem')
                ->label('📋 Listagem')
                ->url(fn() => $this->gerarUrlExportacao('listagem'))
                ->openUrlInNewTab(),

            Actions\Action::make('export_graficos')
                ->label('📈 Gráficos')
                ->url(fn() => $this->gerarUrlExportacao('graficos'))
                ->openUrlInNewTab(),

            Actions\Action::make('export_terceirizada')
                ->label('🏦 Terceirizada')
                ->url(fn() => $this->gerarUrlExportacao('terceirizada'))
                ->openUrlInNewTab(),
        ];
    }

    private function gerarUrlExportacao(string $tipo): string
    {
        $params = [];

        // Adicionar filtros à URL
        if (!empty($this->chartFilters)) {
            foreach ($this->chartFilters as $key => $value) {
                if ($value !== null) {
                    match ($key) {
                        'valor' => $params['valor'] = $value,
                        'nivel_prioridade' => $params['nivel_prioridade'] = $value,
                        'tipo_manutencao_id' => $params['tipo_manutencao_id'] = $value,
                        'escola_id' => $params['escola_id'] = $value,
                        'empresa_contratada_id' => $params['empresa_contratada_id'] = $value,
                        'mes' => $params['mes'] = $value,
                        'periodo' => $params['data_inicio'] = $value['inicio'] ?? null,
                        'periodo' => $params['data_fim'] = $value['fim'] ?? null,
                        default => null,
                    };
                }
            }
        }

        return route('feedback-pedidos.export-' . $tipo, array_filter($params));
    }
}
