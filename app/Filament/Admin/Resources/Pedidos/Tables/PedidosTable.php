<?php

namespace App\Filament\Admin\Resources\Pedidos\Tables;

use App\Models\Pedido;
use App\Models\User;
use App\Models\Enums\NivelEmergenciaPedido;
use App\Services\PedidoService;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Illuminate\Support\Carbon;
use Filament\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use App\Filament\Admin\Components\SliderRating;
use App\Filament\Admin\Resources\Pedidos\Tables\Actions\ExportarRelatorioAction;


class PedidosTable
{
    public static function configure(Table $table, ?User $user = null): Table
    {
        $service = app(PedidoService::class);

        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->columns(static::columns($user))
            ->filters(static::filters(), layout: FiltersLayout::AboveContent)
            ->recordActions(static::actions($user, $service))
            ->tableActions(static::bulkActions($user))
            ->headerActions(static::headerActions($user));
    }

    /*
    |--------------------------------------------------------------------------
    | HEADER ACTIONS
    |--------------------------------------------------------------------------
    */

    public static function headerActions(?User $user): array
    {
        return [
            ExportarRelatorioAction::make(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | FILTERS
    |--------------------------------------------------------------------------
    */

    public static function filters(): array
    {
        return [
            SelectFilter::make('tipo_status_id')
                ->label('Status')
                ->relationship(
                    name: 'tipoStatus',
                    titleAttribute: 'nome',
                    modifyQueryUsing: fn($query) => $query->where('ativo', true)->orderBy('nome')
                )
                ->searchable()
                ->preload(),

            SelectFilter::make('escola_id')
                ->label('Escola')
                ->relationship('escola', 'nome'),

            SelectFilter::make('tipo_manutencao_id')
                ->label('Tipo')
                ->relationship('tipoManutencao', 'nome'),

            SelectFilter::make('nivel_prioridade')
                ->label('Prioridade')
                ->options(
                    collect(NivelEmergenciaPedido::cases())
                        ->mapWithKeys(fn($case) => [$case->value => $case->label()])
                        ->toArray()
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | COLUMNS
    |--------------------------------------------------------------------------
    */

    public static function columns(?User $user): array
    {
        return [
            Split::make([

                // Bloco 1: Protocolo + Tipo de Manutenção
                Stack::make([
                    TextColumn::make('numero_protocolo')
                        ->label('Protocolo')
                        ->tooltip('Número do protocolo')
                        ->extraAttributes(['class' => 'tooltip-hover-effect cursor-help'])
                        ->searchable()
                        ->sortable()
                        ->weight('bold'),

                    TextColumn::make('tipoManutencao.nome')
                        ->label('Tipo')
                        ->tooltip('Tipo de manutenção')
                        ->extraAttributes(['class' => 'tooltip-hover-effect cursor-help'])
                        ->sortable(),

                    TextColumn::make('tipoManutencao.descricao')
                        ->label('')
                        ->color('gray')
                        ->size('sm'),
                ])->space(1),

                // Bloco 2: Escola + Solicitante
                Stack::make([
                    TextColumn::make('escola.nome')
                        ->label('Escola')
                        ->tooltip('Escola que fez a solicitação')
                        ->extraAttributes(['class' => 'tooltip-hover-effect cursor-help'])
                        ->icon('heroicon-o-building-office-2')
                        ->alignCenter()
                        ->sortable(),

                    TextColumn::make('nome_solicitante')
                        ->label('Solicitante')
                        ->tooltip('Quem fez a solicitação')
                        ->extraAttributes(['class' => 'tooltip-hover-effect cursor-help'])
                        ->icon('heroicon-o-user')
                        ->alignCenter()
                        ->color('gray')
                        ->size('sm'),
                ])->space(1),

                // Bloco 3: Status + Prioridade + Responsável
                Stack::make([
                    TextColumn::make('tipoStatus.nome')
                        ->alignCenter()
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(function (Pedido $record) {
                            $status = $record->tipoStatus?->nome ?? 'Sem status';
                            $setor  = $record->setor?->nome;
                            return $setor ? "{$status} - {$setor}" : $status;
                        })
                        ->color(fn(Pedido $record) => Color::hex($record->tipoStatus?->cor ?? '#6b7280')),

                    TextColumn::make('nivel_prioridade')
                        ->label('Prioridade')
                        ->alignCenter()
                        ->badge()
                        ->color(fn(Pedido $record) => Color::hex(
                            match ($record->nivel_prioridade?->value) {
                                'Emergencial' => '#a10000',
                                'Corretivo'   => '#973f00',
                                'Preventivo'  => '#013891',
                                default       => '#2b2b2b',
                            }
                        )),

                    TextColumn::make('responsavel.name')
                        ->label('Responsável')
                        ->alignCenter()
                        ->tooltip('Responsável atual')
                        ->extraAttributes(['class' => 'tooltip-hover-effect cursor-help'])
                        ->icon('heroicon-o-user-circle')
                        ->color('gray')
                        ->size('sm')
                        ->placeholder('Sem responsável'),
                ])->space(1),

                // Bloco 4: Datas
                Stack::make([
                    TextColumn::make('data_solicitacao')
                        ->label('Solicitado em')
                        ->icon('heroicon-o-calendar')
                        ->tooltip('Data de solicitação')
                        ->extraAttributes(['class' => 'tooltip-hover-effect cursor-help'])
                        ->date('d/m/Y')
                        ->alignCenter()
                        ->sortable(),

                    TextColumn::make('data_prevista')
                        ->label('Previsto para')
                        ->tooltip('Data prevista para entrega')
                        ->extraAttributes(['class' => 'tooltip-hover-effect cursor-help'])
                        ->icon('heroicon-o-clock')
                        ->sortable()
                        ->alignCenter()
                        ->date('d/m/Y')
                        ->color(function (Pedido $record) {
                            if (! $record->data_prevista) {
                                return null;
                            }

                            $prevista = Carbon::parse($record->data_prevista);

                            if ($record->data_entrega) {
                                $entrega = Carbon::parse($record->data_entrega);
                                return $entrega->greaterThan($prevista)
                                    ? Color::hex('#a10000')
                                    : Color::hex('#10b981');
                            }

                            if ($prevista->isPast()) {
                                return Color::hex('#a10000');
                            }

                            return now()->diffInDays($prevista, false) <= 15
                                ? Color::hex('#ff6600')
                                : null;
                        })
                        ->size('sm')
                        ->placeholder('Sem previsão'),

                    TextColumn::make('data_entrega')
                        ->label('Concluído em')
                        ->tooltip('Data de conclusão')
                        ->extraAttributes(['class' => 'tooltip-hover-effect cursor-help'])
                        ->icon('heroicon-o-check-circle')
                        ->date('d/m/Y')
                        ->alignCenter()
                        ->sortable()
                        ->color(fn(Pedido $record) => $record->data_entrega ? 'success' : null)
                        ->size('sm')
                        ->placeholder('Não Concluído'),
                ])->space(1),

                // Bloco 5: Descrição + Última Alteração
                Stack::make([
                    TextColumn::make('descricao_pedido')
                        ->label('Descrição')
                        ->tooltip(fn(Pedido $record) => "Descrição do pedido: {$record->descricao_pedido}")
                        ->extraAttributes(['class' => 'tooltip-hover-effect cursor-help'])
                        ->limit(60)
                        ->wrap()
                        ->color('gray')
                        ->size('sm'),

                    TextColumn::make('ultimoHistorico.descricao_alteracao')
                        ->label('Última Alteração')
                        ->tooltip('Descrição da última alteração realizada')
                        ->extraAttributes(['class' => 'tooltip-hover-effect cursor-help'])
                        ->limit(60)
                        ->wrap()
                        ->color('primary')
                        ->size('sm')
                        ->placeholder('Sem alterações'),
                ])->space(1),

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description('Atualizado em:', position: 'above')
                    ->alignEnd()
                    ->tooltip('Data e hora da última atualização do pedido')
                    ->extraAttributes(['class' => 'tooltip-hover-effect cursor-help'])
                    ->toggleable(isToggledHiddenByDefault: true),
            ]),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ROW ACTIONS
    |--------------------------------------------------------------------------
    */

    public static function actions(?User $user, PedidoService $service): array
    {
        return [

            Action::make('historico')
                ->label('Histórico')
                ->icon('heroicon-o-clock')
                ->color('info')
                ->slideOver()
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->visible(fn() => User::authUser()->hasPermissionTo('Visualizar Histórico de Pedidos'))
                ->modalContent(function (Pedido $record) {
                    $historico = $record->historicos()
                        ->with(['statusAnterior', 'statusNovo', 'usuario', 'setor'])
                        ->orderByDesc('created_at')
                        ->get();

                    return view('components.pedido.historico', [
                        'pedido'    => $record,
                        'historico' => $historico,
                    ]);
                }),

            Action::make('finalizar')
                ->label('Avaliar Pedido')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(function (Pedido $record) {
                    return $record->tipoStatus?->nome === 'Em Manutenção'
                        && User::authUser()->hasPermissionTo('Avaliar Pedidos');
                })
                ->modalHeading('Avaliar Pedido')
                ->modalDescription('Ao avaliar o pedido, ele será concluído. Caso a nota seja 1, o pedido será reaberto automaticamente.')
                ->modalSubmitActionLabel('Confirmar Avaliação')
                ->modalCancelActionLabel('Cancelar')
                ->modalWidth('5xl')
                ->schema([
                    Section::make('Avaliação do Serviço')
                        ->schema([
                            SliderRating::make('valor')
                                ->label('Nota (1 a 5)')
                                ->helperText('Avalie o serviço realizado')
                                ->required()
                                ->columnSpanFull(),

                            Textarea::make('descricao')
                                ->label('Descrição da Avaliação')
                                ->maxLength(1000)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Section::make('Fotos da Conclusão')
                        ->schema([
                            FileUpload::make('fotos_conclusao')
                                ->label('Adicionar Fotos')
                                ->multiple()
                                ->image()
                                ->maxFiles(10)
                                ->storeFileNamesIn('nome_original')
                                ->maxSize(5120)
                                ->directory('pedidos/conclusao')
                                ->disk('public')
                                ->visibility('public')
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->columnSpanFull(),
                        ]),
                ])
                ->action(function (Pedido $record, array $data) use ($user, $service) {
                    $service->avaliarPedido($record, $data, $user);

                    Notification::make()
                        ->title(
                            (int) $data['valor'] === 1
                                ? 'Pedido reaberto para nova execução.'
                                : 'Pedido concluído com sucesso.'
                        )
                        ->success()
                        ->send();
                }),

            Action::make('gerenciar')
                ->label('Gerenciar')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(function (Pedido $record) use ($user, $service) {
                    return $service->podeGerenciarRegistro($record, $user);
                })
                ->action(function (Pedido $record) use ($user, $service) {
                    $service->assumirPedido($record, $user);
                    redirect(route('filament.admin.resources.pedidos.edit', $record));
                })
                ->openUrlInNewTab(false),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | BULK ACTIONS
    |--------------------------------------------------------------------------
    */

    public static function bulkActions(?User $user): array
    {
        return [];
    }
}
