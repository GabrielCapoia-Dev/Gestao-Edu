<?php

namespace App\Filament\Admin\Resources\Turmas\Pages;

use App\Filament\Admin\Pages\ExportarAvaliacoes;
use App\Filament\Admin\Resources\Alunos\AlunoResource;
use App\Filament\Admin\Resources\Series\SerieResource;
use App\Filament\Admin\Resources\Turmas\TurmaResource;
use App\Services\TurmaService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class ManageTurmas extends ManageRecords
{
    protected static string $resource = TurmaResource::class;

    protected TurmaService $turmaService;

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => 'Turmas',
            'description' => 'Organize séries, turnos e vínculos de alunos e professores por unidade escolar.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('series')
                ->label('Séries')
                ->icon(Heroicon::ClipboardDocumentList)
                ->color('gray')
                ->url(fn (): string => SerieResource::getUrl('index'))
                ->visible(fn (): bool => Gate::allows('viewAny', SerieResource::getModel())),

            Action::make('alunos')
                ->label('Alunos')
                ->icon(Heroicon::AcademicCap)
                ->color('gray')
                ->url(fn (): string => AlunoResource::getUrl('index'))
                ->visible(fn (): bool => Gate::allows('viewAny', AlunoResource::getModel())),

            Action::make('exportar_avaliacoes')
                ->label('Exportar Avaliações')
                ->icon(Heroicon::ArrowDownTray)
                ->color('gray')
                ->url(fn (): string => ExportarAvaliacoes::getUrl())
                ->visible(fn (): bool => ExportarAvaliacoes::canAccess()),

            CreateAction::make()
                ->label('Criar Turma')
                ->icon(Heroicon::Plus)
                ->using(function (array $data) {

                    $componentes = $data['componentes'] ?? [];
                    unset($data['componentes']);

                    $turma = static::getModel()::create($data);
                    app(TurmaService::class)->salvarComponentes($turma, $componentes);

                    return $turma;
                }),

        ];
    }
}
