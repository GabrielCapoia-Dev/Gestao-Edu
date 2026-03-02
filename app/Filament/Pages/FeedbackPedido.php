<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;
use App\Models\FeedbackPedido as FeedbackPedidoModel;
use App\Models\User;

class FeedbackPedido extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $slug = 'feedback-pedidos';
    protected static string $view = 'filament.pages.feedback-pedido';
    protected static ?string $navigationGroup = 'Manutenção';

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Widgets\FeedbackDistribuicaoChart::class,
            \App\Filament\Widgets\FeedbackMediaMensalChart::class,
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
            ->defaultSort('created_at', 'desc');
    }

    // =========================
    // MÉTRICAS
    // =========================

    public function getMediaGeral(): float
    {
        return round(
            FeedbackPedidoModel::avg('valor') ?? 0,
            2
        );
    }

    public function getTotalAvaliacoes(): int
    {
        return FeedbackPedidoModel::count();
    }

    public function getPercentualSatisfacao(): int
    {
        $total = $this->getTotalAvaliacoes();

        if ($total === 0) {
            return 0;
        }

        $positivos = FeedbackPedidoModel::where('valor', '>=', 4)->count();

        return round(($positivos / $total) * 100);
    }
}
