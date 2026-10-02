<?php

namespace App\Filament\Admin\Resources\SaldosEleitorais\Pages;

use App\Filament\Admin\Resources\SaldosEleitorais\SaldoEleitoralResource;
use App\Models\SaldoEleitoral;
use App\Models\Servidor;
use App\Services\SaldoEleitoralService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListSaldosEleitorais extends ListRecords
{
    protected static string $resource = SaldoEleitoralResource::class;

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Recursos Humanos',
            'title' => 'Saldo Eleitoral',
            'description' => 'Analise solicitações, registre descontos e consulte as movimentações de saldo eleitoral.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('descontar_saldo')
                ->label('Descontar saldo')
                ->icon('heroicon-o-minus-circle')
                ->color('warning')
                ->form([
                    Select::make('servidor_id')
                        ->label('Servidor')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Servidor::query()
                            ->where('nome', 'like', "%{$search}%")
                            ->orderBy('nome')->limit(50)->pluck('nome', 'id')->all())
                        ->getOptionLabelUsing(fn ($value): ?string => Servidor::query()->whereKey($value)->value('nome'))
                        ->required(),
                    TextInput::make('dias')->label('Dias a descontar')->numeric()->integer()->minValue(1)->required(),
                    Textarea::make('observacao')->label('Motivo / observação')->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    app(SaldoEleitoralService::class)->descontar(
                        Servidor::query()->findOrFail((int) $data['servidor_id']),
                        Auth::user(),
                        (int) $data['dias'],
                        $data['observacao'] ?? null,
                    );
                    Notification::make()->title('Desconto registrado')->success()->send();
                }),
            Action::make('exportar_relatorio')
                ->label('Exportar relatório')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => $this->exportarRelatorio()),
        ];
    }

    public function getTabs(): array
    {
        $base = $this->getTableQuery();

        return [
            'adicao' => Tab::make('Adição de saldo')
                ->badge(fn (): int => (clone $base)->where('tipo', SaldoEleitoral::TIPO_ADICAO)->where('status', SaldoEleitoral::STATUS_PENDENTE)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('tipo', SaldoEleitoral::TIPO_ADICAO)),
            'uso' => Tab::make('Uso do saldo')
                ->badge(fn (): int => (clone $base)->where('tipo', SaldoEleitoral::TIPO_USO)->where('status', SaldoEleitoral::STATUS_PENDENTE)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('tipo', SaldoEleitoral::TIPO_USO)),
        ];
    }

    public function getDefaultActiveTab(): ?string
    {
        return 'adicao';
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['servidor:id,nome', 'solicitante:id,name', 'aprovador:id,name']))
            ->columns([
                TextColumn::make('servidor.nome')->label('Servidor')->searchable()->sortable()->weight('bold'),
                TextColumn::make('dias')->label('Dias')->numeric()->sortable(),
                TextColumn::make('status')->label('Status')->badge()->color(fn (string $state): string => match ($state) {
                    SaldoEleitoral::STATUS_APROVADO => 'success',
                    SaldoEleitoral::STATUS_REJEITADO => 'danger',
                    default => 'warning',
                })->formatStateUsing(fn (string $state): string => match ($state) {
                    SaldoEleitoral::STATUS_APROVADO => 'Aprovado',
                    SaldoEleitoral::STATUS_REJEITADO => 'Rejeitado',
                    default => 'Pendente',
                }),
                TextColumn::make('solicitante.name')->label('Solicitado por')->placeholder('—'),
                TextColumn::make('aprovador.name')->label('Analisado por')->placeholder('Pendente'),
                TextColumn::make('observacao')->label('Observação')->limit(80)->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('Solicitado em')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('decidido_em')->label('Decidido em')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
            ])
            ->recordActions([
                Action::make('aprovar')
                    ->label('Aprovar')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->visible(fn (SaldoEleitoral $record): bool => $record->status === SaldoEleitoral::STATUS_PENDENTE)
                    ->action(fn (SaldoEleitoral $record) => $this->decidir($record, true)),
                Action::make('rejeitar')
                    ->label('Rejeitar')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->visible(fn (SaldoEleitoral $record): bool => $record->status === SaldoEleitoral::STATUS_PENDENTE)
                    ->action(fn (SaldoEleitoral $record) => $this->decidir($record, false)),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100]);
    }

    private function decidir(SaldoEleitoral $record, bool $aprovar): void
    {
        app(SaldoEleitoralService::class)->decidir($record, Auth::user(), $aprovar);
        Notification::make()->title($aprovar ? 'Solicitação aprovada' : 'Solicitação rejeitada')->success()->send();
    }

    private function exportarRelatorio(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Servidor', 'Tipo', 'Dias', 'Status', 'Solicitante', 'Responsável', 'Observação', 'Data'], ';');
            SaldoEleitoral::query()->with(['servidor:id,nome', 'solicitante:id,name', 'aprovador:id,name'])
                ->orderBy('created_at')->chunk(500, function ($registros) use ($output): void {
                    foreach ($registros as $registro) {
                        fputcsv($output, [
                            self::csvCell($registro->servidor?->nome),
                            $registro->tipo === SaldoEleitoral::TIPO_ADICAO ? 'Adição' : 'Uso',
                            $registro->dias,
                            $registro->status,
                            self::csvCell($registro->solicitante?->name),
                            self::csvCell($registro->aprovador?->name),
                            self::csvCell($registro->observacao),
                            $registro->created_at?->format('d/m/Y H:i'),
                        ], ';');
                    }
                });
            fclose($output);
        }, 'relatorio-saldo-eleitoral.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function csvCell(?string $value): ?string
    {
        return $value !== null && preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1
            ? "'{$value}"
            : $value;
    }
}
