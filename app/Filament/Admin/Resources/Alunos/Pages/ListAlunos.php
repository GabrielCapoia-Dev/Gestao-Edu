<?php

namespace App\Filament\Admin\Resources\Alunos\Pages;

use App\Exceptions\MatriculaAlunoBloqueadaException;
use App\Filament\Admin\Resources\Alunos\AlunoResource;
use App\Jobs\ImportAlunosMatriculadosJob;
use App\Models\Aluno;
use App\Models\ExportRequest;
use App\Services\AlunoMovimentacaoService;
use App\Services\Alunos\AlunoImportacaoSpreadsheetService;
use App\Services\AlunoTransferenciaPendenteService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Override;

class ListAlunos extends ListRecords
{
    protected static string $resource = AlunoResource::class;

    #[Url(as: 'turma')]
    public ?int $turma = null;

    #[Override]
    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => 'Alunos',
            'description' => 'Gerencie os alunos, adicione novos e mantenha um registro atualizado das informações.',
        ]);
    }

    public function mount(): void
    {
        $this->turma = request()->integer('turma') ?: null;

        parent::mount();

        if ($this->turma) {
            $this->tableFilters['id_turma']['value'] = $this->turma;
        }

        if (request()->filled('pendencia_cgm')) {
            $this->tableSearch = (string) request()->query('pendencia_cgm');
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Html::make(fn (): string => $this->bannerPendenciaProfessor())
                    ->visible(fn (): bool => $this->alunoPendenciaProfessor() !== null),
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportarModeloImportacao')
                ->label('Exportar Modelo')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => Gate::allows('exportTemplate', Aluno::class))
                ->action(fn () => $this->spreadsheetService()->exportarModelo()),

            Actions\Action::make('importarMatriculados')
                ->label('Importar Matriculados')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => ! app(AlunoTransferenciaPendenteService::class)->professorEstaBloqueado(Auth::user())
                    && Gate::allows('import', Aluno::class))
                ->schema([
                    FileUpload::make('arquivo')
                        ->label('Arquivo da planilha')
                        ->disk('local')
                        ->directory('imports/alunos')
                        ->visibility('private')
                        ->storeFiles()
                        ->preserveFilenames()
                        ->acceptedFileTypes([
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->maxSize(10240)
                        ->required()
                        ->helperText('Use a aba Matriculados com Escola, Seriação, Turma, Turno, CGM, Nome do aluno, Data de Nascimento e Sexo. Data da matrícula é opcional.'),
                ])
                ->action(function (array $data): void {
                    $arquivo = $this->normalizarArquivoImportacao($data['arquivo'] ?? null);
                    $processo = $this->criarProcessoImportacao($arquivo);

                    ImportAlunosMatriculadosJob::dispatch(
                        $arquivo,
                        Auth::id(),
                        'local',
                        $processo->getKey(),
                    )->afterCommit();

                    Notification::make()
                        ->title('Importação enviada para processamento')
                        ->body('Acompanhe o andamento em Minhas Exportacoes. Você pode continuar usando o sistema.')
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make()
                ->modalWidth('4xl')
                ->visible(fn (): bool => ! app(AlunoTransferenciaPendenteService::class)->professorEstaBloqueado(Auth::user()))
                ->using(function (array $data): Model {
                    $data = AlunoResource::alunoService()->prepararDadosCadastroAluno($data, Auth::user());

                    unset($data['id_escola'], $data['id_serie']);
                    AlunoResource::alunoService()->validarTurmaPermitida((int) ($data['id_turma'] ?? 0), Auth::user());

                    try {
                        return app(AlunoMovimentacaoService::class)->criarMatricula($data, Auth::user());
                    } catch (MatriculaAlunoBloqueadaException $exception) {
                        Notification::make()
                            ->title('Matrícula impedida')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        throw ValidationException::withMessages([
                            'data.cgm' => $exception->getMessage(),
                        ]);
                    }
                }),
        ];
    }

    public function getTitle(): string
    {
        if ($this->turma) {
            return 'Alunos da Turma';
        }

        return parent::getTitle();
    }

    private function alunoPendenciaProfessor(): ?Aluno
    {
        return app(AlunoTransferenciaPendenteService::class)->pendenciaAtivaParaProfessor(Auth::user());
    }

    private function bannerPendenciaProfessor(): string
    {
        $aluno = $this->alunoPendenciaProfessor();

        if (! $aluno) {
            return '';
        }

        $mensagem = e(sprintf(
            'O aluno %s está com transferência pendente, suas ações estão limitadas enquanto as pendências não forem solucionadas',
            $aluno->nome
        ));

        return <<<HTML
<div class="rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm font-medium text-warning-900 shadow-sm">
    {$mensagem}
</div>
HTML;
    }

    private function spreadsheetService(): AlunoImportacaoSpreadsheetService
    {
        return app(AlunoImportacaoSpreadsheetService::class);
    }

    private function normalizarArquivoImportacao(mixed $arquivo): string
    {
        if (is_array($arquivo)) {
            $arquivo = reset($arquivo);
        }

        return (string) $arquivo;
    }

    private function criarProcessoImportacao(string $arquivo): ExportRequest
    {
        return ExportRequest::query()->create([
            'user_id' => Auth::id(),
            'type' => 'alunos_importacao_planilha',
            'format' => 'processo',
            'label' => 'Importação de alunos por planilha',
            'filters' => ['arquivo' => basename($arquivo)],
            'metadata' => [
                'process_kind' => 'importacao_alunos',
                'arquivo_path' => $arquivo,
                'disk' => 'local',
            ],
            'fingerprint' => hash('sha256', 'alunos_importacao|'.Auth::id().'|'.$arquivo.'|'.Str::uuid()),
            'status' => ExportRequest::STATUS_QUEUED,
            'status_message' => 'Aguardando processamento.',
            'progress_current' => 0,
            'progress_total' => 100,
            'expires_at' => now()->addDays((int) config('exports.expiration_days', 7)),
        ]);
    }
}
