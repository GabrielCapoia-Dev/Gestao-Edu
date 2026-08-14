<?php

namespace App\Filament\Admin\Resources\Escolas\Pages;

use App\Filament\Admin\Resources\Escolas\EscolaResource;
use App\Models\Escola;
use App\Models\User;
use App\Services\Escolas\EscolaLotacaoSpreadsheetService;
use App\Services\Exports\ExportRequestService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Throwable;

class ManageEscolas extends ManageRecords
{
    protected static string $resource = EscolaResource::class;

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => 'Escolas',
            'description' => 'Gerencie as escolas da rede educacional, adicione novas instituições e mantenha um registro atualizado das informações.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importar_lotacoes')
                ->label('Importar lotações')
                ->icon('heroicon-o-document-arrow-up')
                ->color('primary')
                ->authorize(fn (): bool => Gate::allows('updateAny', Escola::class))
                ->modalHeading('Importar lotações em massa')
                ->modalDescription('A escola pode aparecer em várias linhas, mas cada número de lotação deve aparecer apenas uma vez na planilha inteira.')
                ->modalSubmitActionLabel('Importar lotações')
                ->schema([
                    FileUpload::make('arquivo')
                        ->label('Arquivo da planilha')
                        ->disk('local')
                        ->directory('imports/lotacoes')
                        ->visibility('private')
                        ->storeFiles()
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->maxSize(5120)
                        ->required()
                        ->helperText('Use as colunas A: Escola, B: Número da lotação e C: Nome da lotação. A primeira linha deve conter esses cabeçalhos.'),
                ])
                ->action(function (array $data): void {
                    /** @var User|null $user */
                    $user = auth()->user();

                    if (! $user) {
                        abort(403);
                    }

                    $arquivo = $data['arquivo'] ?? null;
                    $arquivo = is_array($arquivo) ? reset($arquivo) : $arquivo;

                    try {
                        $resultado = app(EscolaLotacaoSpreadsheetService::class)->importar(
                            (string) $arquivo,
                            $user,
                        );
                        $criadas = $resultado['criadas'] === 1
                            ? '1 lotação criada'
                            : "{$resultado['criadas']} lotações criadas";
                        $atualizadas = $resultado['atualizadas'] === 1
                            ? '1 lotação atualizada'
                            : "{$resultado['atualizadas']} lotações atualizadas";

                        Notification::make()
                            ->title('Importação de lotações concluída')
                            ->body("{$criadas} e {$atualizadas}.")
                            ->success()
                            ->send();
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()
                            ->title('Não foi possível importar as lotações')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Não foi possível importar as lotações')
                            ->body('O arquivo não pôde ser processado. Verifique o formato e tente novamente.')
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('exportar_escolas_xlsx')
                ->label('Exportar XLSX')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->authorize(fn (): bool => Gate::allows('viewAny', Escola::class))
                ->requiresConfirmation()
                ->modalHeading('Exportar escolas em XLSX')
                ->modalDescription('A planilha será gerada em segundo plano com todas as escolas disponíveis no seu escopo de acesso.')
                ->modalSubmitActionLabel('Enviar para a fila')
                ->action(function (): void {
                    /** @var User|null $user */
                    $user = auth()->user();

                    if (! $user) {
                        return;
                    }

                    try {
                        $request = app(ExportRequestService::class)->queue(
                            user: $user,
                            type: 'escolas_xlsx',
                            format: 'xlsx',
                            label: 'XLSX de escolas',
                            metadata: ['source' => 'escolas.header_action'],
                        );

                        Notification::make()
                            ->title($request->wasRecentlyCreated
                                ? 'Exportação enviada para a fila'
                                : 'Exportação já está em andamento')
                            ->body('Acompanhe o progresso em Minhas Exportações.')
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Não foi possível iniciar a exportação')
                            ->body('Tente novamente em alguns instantes.')
                            ->danger()
                            ->send();
                    }
                }),

            CreateAction::make()
                ->mutateDataUsing(function (array $data): array {
                    $data['ativo'] = true;

                    return $data;
                }),
        ];
    }
}
