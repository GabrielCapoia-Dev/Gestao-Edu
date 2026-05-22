<?php

namespace App\Filament\Admin\Resources\Alunos\Pages;

use App\Exceptions\MatriculaAlunoBloqueadaException;
use App\Filament\Admin\Resources\Alunos\AlunoResource;
use App\Models\Aluno;
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
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Override;

class ListAlunos extends ListRecords
{
    protected static string $resource = AlunoResource::class;

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
        parent::mount();

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
                ->visible(fn (): bool => Auth::user()?->hasPermissionTo('Criar Alunos') ?? false)
                ->action(fn () => $this->spreadsheetService()->exportarModelo()),

            Actions\Action::make('importarMatriculados')
                ->label('Importar Matriculados')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => ! app(AlunoTransferenciaPendenteService::class)->professorEstaBloqueado(Auth::user())
                    && (Auth::user()?->hasPermissionTo('Criar Alunos') ?? false))
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
                        ->helperText('Use a aba Matriculados com Escola, Seriacao, Turma, Turno, CGM, Nome do Aluno e Data de Nascimento.'),
                ])
                ->action(function (array $data): void {
                    try {
                        $resultado = $this->spreadsheetService()->importar(
                            $data['arquivo'],
                            Auth::user()
                        );

                        Notification::make()
                            ->title('Importacao concluida')
                            ->body("{$resultado['total_importado']} aluno(s) importado(s). Series criadas: {$resultado['series_criadas']}. Turmas criadas: {$resultado['turmas_criadas']}. Pendentes: {$resultado['total_pendente']}. CGMs duplicados ignorados: {$resultado['duplicados_ignorados']}.")
                            ->success()
                            ->send();
                    } catch (InvalidArgumentException|ValidationException $exception) {
                        Notification::make()
                            ->title('Nao foi possivel importar a planilha')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Actions\CreateAction::make()
                ->modalWidth('4xl')
                ->visible(fn (): bool => ! app(AlunoTransferenciaPendenteService::class)->professorEstaBloqueado(Auth::user()))
                ->using(function (array $data): Model {
                    unset($data['id_escola'], $data['id_serie']);
                    AlunoResource::alunoService()->validarTurmaPermitida((int) ($data['id_turma'] ?? 0), Auth::user());

                    try {
                        return app(AlunoMovimentacaoService::class)->criarMatricula($data, Auth::user());
                    } catch (MatriculaAlunoBloqueadaException $exception) {
                        Notification::make()
                            ->title('Matricula impedida')
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
        if (request()->filled('turma')) {
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
            'O aluno %s esta com transferencia pendente, suas ações estão limitadas enquanto as pendencias não forem solucionadas',
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
}
