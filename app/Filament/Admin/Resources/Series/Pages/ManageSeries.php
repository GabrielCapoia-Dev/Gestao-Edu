<?php

namespace App\Filament\Admin\Resources\Series\Pages;

use App\Filament\Admin\Resources\ComponenteCurriculars\ComponenteCurricularResource;
use App\Filament\Admin\Resources\Series\SerieResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class ManageSeries extends ManageRecords
{
    protected static string $resource = SerieResource::class;

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => 'Séries',
            'description' => 'Gerencie as séries para organizar o currículo escolar dos alunos.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('componentes_curriculares')
                ->label('Componentes Curriculares')
                ->icon(Heroicon::BookmarkSquare)
                ->color('gray')
                ->url(fn (): string => ComponenteCurricularResource::getUrl('index'))
                ->visible(fn (): bool => Gate::allows('viewAny', ComponenteCurricularResource::getModel())),

            CreateAction::make(),
        ];
    }
}
