<?php

namespace App\Filament\Admin\Pages;

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
use App\Models\Enums\ResultadoFeedbackPedido;
use BackedEnum;
use UnitEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;


class FeedbackPedido extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'feedback-pedidos';
    protected string $view = 'filament.pages.feedback-pedido';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::Star;
    protected static string|UnitEnum|null $navigationGroup = 'Manutencao';
    protected static ?string $navigationParentItem = 'Pedidos';
    public static ?string $navigationLabel = 'Feedback de Pedidos';

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.pages.gestao-avaliacoes-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Manutenção',
            'title' => "Feedback de Pedidos",
            'description' => 'Gerencie o feedback dos pedidos, gerando relatórios detalhados para cada pedido.',
        ]);
    }


    public array $chartFilters = [];

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Widgets\FeedbackMediaMensalChart::class,
            \App\Filament\Widgets\FeedbackQuantidadePorNotaChart::class,
        ];
    }

    public static function canAccess(): bool
    {
        return User::authUser()->hasPermissionTo('Visualizar Feedback de Pedidos') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FeedbackPedidoModel::query()
                    ->with(['pedido.escola', 'pedido.tipoManutencao', 'itens.problema'])
            )
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5)
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

                Tables\Columns\TextColumn::make('itens_resumo')
                    ->label('Por problema')
                    ->state(fn(FeedbackPedidoModel $record): string => $record->itens
                        ->map(fn($item) => ($item->problema?->texto_problema ?? 'Problema') . ': ' . $item->valor . '/5')
                        ->take(3)
                        ->join(' | '))
                    ->limit(90)
                    ->wrap()
                    ->placeholder('Sem itens'),

                Tables\Columns\TextColumn::make('reabrir_pedido')
                    ->label('Reaberto')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state ? 'Sim' : 'Não')
                    ->color(fn($state) => $state ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Avaliado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('valor')
                    ->label('Nota')
                    ->options([
                        '1' => '1 estrela',
                        '2' => '2 estrelas',
                        '3' => '3 estrelas',
                        '4' => '4 estrelas',
                        '5' => '5 estrelas',
                    ])
                    ->query(function ($query, array $data) {
                        $this->updateChartFilters('valor', $data['value'] ?? null);
                        return $query->when($data['value'] ?? null, fn($q) => $q->where('valor', $data['value']));
                    }),

                Tables\Filters\SelectFilter::make('pedido.nivel_prioridade')
                    ->label('Nivel de Prioridade')
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
                    ->label('Tipo de Manutencao')
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
                        \App\Models\EmpresaContratada::query()
                            ->doSetorDoUsuario(Auth::user())
                            ->pluck('nome', 'id')
                            ->toArray()
                    )
                    ->query(function ($query, array $data) {
                        $this->updateChartFilters('empresa_contratada_id', $data['value'] ?? null);
                        return $query->when($data['value'] ?? null, fn($q) => $q->whereHas('pedido', fn($subquery) => $subquery->where('empresa_contratada_id', $data['value'])));
                    }),

                Tables\Filters\SelectFilter::make('resultado')
                    ->label('Resultado por problema')
                    ->options(
                        collect(ResultadoFeedbackPedido::cases())
                            ->mapWithKeys(fn(ResultadoFeedbackPedido $resultado) => [$resultado->value => $resultado->label()])
                            ->toArray()
                    )
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        return $query->when(
                            $value,
                            fn($q) => $q->whereHas('itens', fn($itemQuery) => $itemQuery->where('resultado', $value))
                        );
                    }),

                Tables\Filters\Filter::make('mes')
                    ->label('Mes')
                    ->form([
                        Forms\Components\Select::make('mes')
                            ->label('Mes')
                            ->options([
                                '01' => 'Janeiro',
                                '02' => 'Fevereiro',
                                '03' => 'Marco',
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
                ->label('Relatorio Geral')
                ->visible(fn() => User::authUser()?->hasPermissionLike('exportar relatorios') ?? false)
                ->url(fn() => $this->gerarUrlExportacao('geral'))
                ->openUrlInNewTab(),

            Actions\Action::make('export_listagem')
                ->label('Listagem')
                ->visible(fn() => User::authUser()?->hasPermissionLike('exportar relatorios') ?? false)
                ->url(fn() => $this->gerarUrlExportacao('listagem'))
                ->openUrlInNewTab(),

            Actions\Action::make('export_graficos')
                ->label('Graficos')
                ->visible(fn() => User::authUser()?->hasPermissionLike('exportar relatorios') ?? false)
                ->url(fn() => $this->gerarUrlExportacao('graficos'))
                ->openUrlInNewTab(),

            Actions\Action::make('export_terceirizada')
                ->label('Terceirizada')
                ->visible(fn() => User::authUser()?->hasPermissionLike('exportar relatorios') ?? false)
                ->url(fn() => $this->gerarUrlExportacao('terceirizada'))
                ->openUrlInNewTab(),
        ];
    }

    private function gerarUrlExportacao(string $tipo): string
    {
        $params = [];

        // Adicionar filtros a URL
        if (!empty($this->chartFilters)) {
            foreach ($this->chartFilters as $key => $value) {
                if ($value !== null) {
                    match ($key) {
                        'valor' => $params['valor'] = $value,
                        'nivel_prioridade' => $params['nivel_prioridade'] = $value,
                        'tipo_manutencao_id' => $params['tipo_manutencao_id'] = $value,
                        'escola_id' => $params['escola_id'] = $value,
                        'empresa_contratada_id' => $params['empresa_contratada_id'] = $value,
                        'resultado' => $params['resultado'] = $value,
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
    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }
}
