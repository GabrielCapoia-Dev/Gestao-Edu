<?php

namespace App\Filament\Admin\Pages;

use App\Models\ExportRequest;
use App\Models\User;
use App\Services\Exports\ExportSessionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use UnitEnum;

class MinhasExportacoes extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.minhas-exportacoes';

    protected static ?string $title = 'Minhas Exportacoes';

    protected static ?string $navigationLabel = 'Minhas Exportacoes';

    protected static ?string $slug = 'minhas-exportacoes';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 90;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'Relatórios';

    public ?string $autoDownload = null;

    public bool $autoDownloadDispatched = false;

    protected $queryString = [
        'autoDownload' => ['except' => null, 'as' => 'download'],
    ];

    public function mount(): void
    {
        if (request()->filled('download')) {
            $this->autoDownload = (string) request()->query('download');
        }
    }

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->can('viewAny', ExportRequest::class) ?? false;
    }

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Relatórios',
            'title' => 'Minhas Exportacoes',
            'description' => 'Acompanhe exportacoes e processamentos em segundo plano.',
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('label')
                    ->label('Solicitação')
                    ->placeholder('Solicitação')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('format')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'processo' ? 'PROCESSO' : strtoupper((string) $state)),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $this->statusLabel($state))
                    ->color(fn (string $state): string => $this->statusColor($state)),

                ViewColumn::make('progress_percentage')
                    ->label('Progresso')
                    ->view('filament.tables.columns.export-progress'),

                TextColumn::make('status_message')
                    ->label('Mensagem')
                    ->placeholder('-')
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('size_bytes')
                    ->label('Tamanho')
                    ->formatStateUsing(fn (?int $state): string => $state ? Number::fileSize($state) : '-')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Solicitado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('finished_at')
                    ->label('Concluído em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        ExportRequest::STATUS_QUEUED => 'Na fila',
                        ExportRequest::STATUS_RUNNING => 'Processando',
                        ExportRequest::STATUS_FINISHED => 'Pronto',
                        ExportRequest::STATUS_FAILED => 'Falha',
                        ExportRequest::STATUS_CANCELLED => 'Cancelado',
                        ExportRequest::STATUS_EXPIRED => 'Expirado',
                    ]),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Baixar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (ExportRequest $record): string => route('exports.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (ExportRequest $record): bool => Auth::user()?->can('download', $record) ?? false),

                Action::make('cancelar')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (ExportRequest $record): void {
                        abort_unless(Auth::user()?->can('cancel', $record) ?? false, 403);

                        $record->requestCancellation();

                        Notification::make()
                            ->title('Processo cancelado')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (ExportRequest $record): bool => Auth::user()?->can('cancel', $record) ?? false),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll(fn (): ?string => $this->hasActiveExports()
                ? ((int) config('performance.livewire_polling.exports_table', 15)).'s'
                : null);
    }

    public function pollAutoDownload(): void
    {
        if (! $this->autoDownload || $this->autoDownloadDispatched) {
            return;
        }

        $exportRequest = ExportRequest::query()->find($this->autoDownload);

        if (! $exportRequest || ! (Auth::user()?->can('view', $exportRequest) ?? false)) {
            $this->autoDownloadDispatched = true;
            $this->autoDownload = null;

            return;
        }

        if ($exportRequest->isFinished() && (Auth::user()?->can('download', $exportRequest) ?? false)) {
            $this->autoDownloadDispatched = true;
            $this->autoDownload = null;

            Notification::make()
                ->title('Arquivo pronto')
                ->body('O download será iniciado automaticamente. O arquivo continua disponível para baixar novamente nesta tela.')
                ->success()
                ->send();

            $this->dispatch('download-url', url: route('exports.download', $exportRequest));

            return;
        }

        if ($exportRequest->isFailed()) {
            $this->autoDownloadDispatched = true;
            $this->autoDownload = null;

            Notification::make()
                ->title('Falha na exportação')
                ->body($exportRequest->error_message ?: 'Não foi possível gerar o arquivo solicitado.')
                ->danger()
                ->send();
        }
    }

    public function hasActiveExports(): bool
    {
        return $this->query()
            ->whereIn('status', [ExportRequest::STATUS_QUEUED, ExportRequest::STATUS_RUNNING])
            ->exists();
    }

    private function query(): Builder
    {
        /** @var User|null $user */
        $user = Auth::user();

        $sessionHash = app(ExportSessionService::class)->currentSessionHash();

        if (! $user || ! $sessionHash) {
            return ExportRequest::query()->whereRaw('1 = 0');
        }

        return ExportRequest::query()
            ->where('user_id', $user->getKey())
            ->where('session_hash', $sessionHash)
            ->whereNull('session_ended_at');
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            ExportRequest::STATUS_QUEUED => 'Na fila',
            ExportRequest::STATUS_RUNNING => 'Processando',
            ExportRequest::STATUS_FINISHED => 'Pronto',
            ExportRequest::STATUS_FAILED => 'Falha',
            ExportRequest::STATUS_CANCELLED => 'Cancelado',
            ExportRequest::STATUS_EXPIRED => 'Expirado',
            default => $status,
        };
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            ExportRequest::STATUS_QUEUED => 'gray',
            ExportRequest::STATUS_RUNNING => 'warning',
            ExportRequest::STATUS_FINISHED => 'success',
            ExportRequest::STATUS_FAILED => 'danger',
            ExportRequest::STATUS_CANCELLED,
            ExportRequest::STATUS_EXPIRED => 'gray',
            default => 'gray',
        };
    }
}
