<?php

namespace App\Filament\Admin\Resources\BalancosEstoque\Pages;

use App\Filament\Admin\Resources\BalancosEstoque\BalancoEstoqueResource;
use App\Models\BalancoEstoque;
use App\Models\Enums\TipoItem;
use App\Services\Estoque\BalancoEstoqueService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ViewBalancoEstoque extends ViewRecord
{
    protected static string $resource = BalancoEstoqueResource::class;

    protected ?Collection $itensInicioCache = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportarRelatorio')
                ->label('Exportar Relatório')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => $this->pode('Listar Balanços de Estoque'))
                ->url(fn (): string => route('balancos-estoque.relatorio.pdf', ['balanco' => $this->getRecord()]))
                ->openUrlInNewTab(),

            Action::make('iniciar')
                ->label('Iniciar')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->modalWidth('7xl')
                ->visible(fn (): bool => $this->getRecord()->isAgendado() && $this->pode('Iniciar Balanços de Estoque'))
                ->disabled(fn (): bool => $this->itensDisponiveisParaInicio()->where('bloqueado', false)->isEmpty())
                ->schema([
                    Select::make('tipos_filtro')
                        ->label('Filtrar itens por tipo')
                        ->options(fn (): array => $this->opcoesTiposInicio())
                        ->multiple()
                        ->live()
                        ->dehydrated(false)
                        ->placeholder('Todos os tipos')
                        ->helperText('Os grupos abaixo ficam organizados por tipo. Use "Selecionar tudo" em cada grupo para marcar todos os itens daquele tipo.'),
                    Hidden::make('item_ids_state')
                        ->default(fn (): array => $this->itensDisponiveisParaInicio()
                            ->where('bloqueado', false)
                            ->pluck('item_id')
                            ->map(fn (mixed $id): int => (int) $id)
                            ->all()),
                    Group::make()
                        ->schema(fn (Get $get): array => $this->schemaItensInicioPorTipo($get))
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    try {
                        $this->service()->iniciar(
                            $this->getRecord(),
                            collect($data['item_ids_state'] ?? [])
                                ->map(fn (mixed $id): int => (int) $id)
                                ->filter()
                                ->unique()
                                ->values()
                                ->all(),
                            Auth::user(),
                        );

                        $this->refreshRecordState();

                        Notification::make()
                            ->title('Balanço iniciado com sucesso.')
                            ->success()
                            ->send();
                    } catch (\DomainException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('adiar')
                ->label('Adiar')
                ->icon('heroicon-o-clock')
                ->color('info')
                ->visible(fn (): bool => $this->getRecord()->isAgendado() && $this->pode('Adiar Balanços de Estoque'))
                ->schema([
                    DateTimePicker::make('data_agendada')
                        ->label('Nova data agendada')
                        ->required()
                        ->seconds(false)
                        ->default(fn (): ?string => $this->getRecord()->data_agendada?->format('Y-m-d H:i:s'))
                        ->native(false),
                    Textarea::make('motivo')
                        ->label('Motivo do adiamento')
                        ->required()
                        ->rows(4)
                        ->maxLength(1500),
                ])
                ->action(function (array $data): void {
                    try {
                        $this->service()->adiar($this->getRecord(), $data['data_agendada'], (string) $data['motivo'], Auth::user());
                        $this->refreshRecordState();

                        Notification::make()
                            ->title('Balanço adiado com sucesso.')
                            ->success()
                            ->send();
                    } catch (\DomainException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('cancelar')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => ! $this->getRecord()->isConcluido() && ! $this->getRecord()->isCancelado() && $this->pode('Cancelar Balanços de Estoque'))
                ->schema([
                    Textarea::make('motivo')
                        ->label('Motivo do cancelamento')
                        ->required()
                        ->rows(4)
                        ->maxLength(1500),
                ])
                ->action(function (array $data): void {
                    try {
                        $this->service()->cancelar($this->getRecord(), (string) $data['motivo'], Auth::user());
                        $this->refreshRecordState();

                        Notification::make()
                            ->title('Balanço cancelado com sucesso.')
                            ->success()
                            ->send();
                    } catch (\DomainException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('concluir')
                ->label('Concluir')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Concluir balanço')
                ->modalDescription('As quantidades contadas serão aplicadas no estoque e gerarão movimentações de reajuste.')
                ->visible(fn (): bool => $this->getRecord()->isEmAndamento() && $this->pode('Concluir Balanços de Estoque'))
                ->action(function (): void {
                    try {
                        $this->service()->concluir($this->getRecord(), Auth::user());
                        $this->refreshRecordState();

                        Notification::make()
                            ->title('Balanço concluído com sucesso.')
                            ->success()
                            ->send();
                    } catch (\DomainException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function service(): BalancoEstoqueService
    {
        return app(BalancoEstoqueService::class);
    }

    protected function refreshRecordState(): void
    {
        /** @var BalancoEstoque $record */
        $record = BalancoEstoqueResource::getEloquentQuery()->findOrFail($this->getRecord()->getKey());
        $this->record = $record;
        $this->itensInicioCache = null;
    }

    protected function itensDisponiveisParaInicio(): Collection
    {
        if ($this->itensInicioCache instanceof Collection) {
            return $this->itensInicioCache;
        }

        /** @var BalancoEstoque $record */
        $record = $this->getRecord();

        return $this->itensInicioCache = $this->service()->itensDisponiveisParaInicio($record);
    }

    protected function schemaItensInicioPorTipo(Get $get): array
    {
        return $this->itensAgrupadosPorTipoParaInicio($get)
            ->map(function (Collection $itens, string $tipo): Section {
                $itemIdsDoTipo = $itens
                    ->pluck('item_id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->all();

                return Section::make($this->labelTipoInicio($tipo))
                    ->description($this->descricaoTipoInicio($itens))
                    ->collapsible()
                    ->schema([
                        CheckboxList::make("item_ids_por_tipo.{$tipo}")
                            ->label('Itens')
                            ->options($itens->mapWithKeys(
                                fn (array $item): array => [
                                    $item['item_id'] => $item['item']->nome,
                                ]
                            )->toArray())
                            ->descriptions($itens->mapWithKeys(
                                fn (array $item): array => [
                                    $item['item_id'] => $this->descricaoItemInicio($item),
                                ]
                            )->toArray())
                            ->disableOptionWhen(fn (string $value): bool => $this->itemInicioBloqueado((int) $value))
                            ->default(fn (Get $get): array => $this->idsSelecionadosDoTipo($get, $itemIdsDoTipo))
                            ->afterStateUpdated(function (?array $state, Get $get, callable $set) use ($itemIdsDoTipo): void {
                                $selecionadosGlobais = collect($get('item_ids_state') ?? [])
                                    ->map(fn (mixed $id): int => (int) $id)
                                    ->filter()
                                    ->unique()
                                    ->values();

                                $selecionadosAtualizados = $selecionadosGlobais
                                    ->diff($itemIdsDoTipo)
                                    ->merge(
                                        collect($state ?? [])
                                            ->map(fn (mixed $id): int => (int) $id)
                                            ->filter()
                                            ->values()
                                    )
                                    ->unique()
                                    ->values()
                                    ->all();

                                $set('item_ids_state', $selecionadosAtualizados);
                            })
                            ->bulkToggleable()
                            ->columns(2),
                    ])
                    ->columnSpanFull();
            })
            ->values()
            ->all();
    }

    protected function itensAgrupadosPorTipoParaInicio(Get $get): Collection
    {
        $tiposFiltrados = collect($get('tipos_filtro') ?? [])
            ->filter()
            ->values();

        return $this->itensDisponiveisParaInicio()
            ->groupBy(fn (array $item): string => $item['item']->tipo_item?->value ?? 'sem_tipo')
            ->sortKeys()
            ->filter(fn (Collection $itens, string $tipo): bool => $tiposFiltrados->isEmpty() || $tiposFiltrados->contains($tipo))
            ->map(fn (Collection $itens): Collection => $itens->sortBy(fn (array $item): string => mb_strtolower((string) $item['item']->nome))->values());
    }

    protected function opcoesTiposInicio(): array
    {
        return $this->itensDisponiveisParaInicio()
            ->groupBy(fn (array $item): string => $item['item']->tipo_item?->value ?? 'sem_tipo')
            ->mapWithKeys(fn (Collection $itens, string $tipo): array => [$tipo => $this->labelTipoInicio($tipo)])
            ->sortKeys()
            ->toArray();
    }

    protected function idsSelecionadosDoTipo(Get $get, array $itemIdsDoTipo): array
    {
        return collect($get('item_ids_state') ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->intersect($itemIdsDoTipo)
            ->values()
            ->all();
    }

    protected function descricaoTipoInicio(Collection $itens): string
    {
        $total = $itens->count();
        $bloqueados = $itens->where('bloqueado', true)->count();
        $disponiveis = $total - $bloqueados;

        return "{$total} item(ns) neste tipo, {$disponiveis} disponível(is) e {$bloqueados} bloqueado(s).";
    }

    protected function labelTipoInicio(string $tipo): string
    {
        if ($tipo === 'sem_tipo') {
            return 'Sem tipo definido';
        }

        return TipoItem::tryFrom($tipo)?->label() ?? ucfirst(str_replace('_', ' ', $tipo));
    }

    protected function descricaoItemInicio(array $item): string
    {
        $descricao = 'Saldo atual: ' . number_format((float) $item['saldo_sistema'], 3, ',', '.');
        $unidade = strtoupper($item['item']->unidade_medida?->value ?? '');

        if ($unidade !== '') {
            $descricao .= ' | Unidade: ' . $unidade;
        }

        if ($item['bloqueado']) {
            $descricao .= ' | Bloqueado em ' . ($item['balanco_codigo'] ?? 'outro balanço');
        }

        return $descricao;
    }

    protected function itemInicioBloqueado(int $itemId): bool
    {
        $item = $this->itensDisponiveisParaInicio()->firstWhere('item_id', $itemId);

        return (bool) ($item['bloqueado'] ?? false);
    }

    protected function pode(string $permissao): bool
    {
        return Auth::user()?->hasPermissionTo($permissao) ?? false;
    }
}
