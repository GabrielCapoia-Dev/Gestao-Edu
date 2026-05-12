<?php

namespace App\Filament\Admin\Resources\Turmas\Pages;

use App\Filament\Admin\Resources\Series\SerieResource;
use App\Filament\Admin\Resources\Turmas\TurmaResource;
use App\Models\Serie;
use App\Models\User;
use App\Services\TurmaService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

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
            CreateAction::make('nova_serie')
                ->label('Nova Série')
                ->icon('heroicon-o-clipboard-document-list')
                ->model(Serie::class)
                ->visible(function () {
                    /** @var User */
                    $user = Auth::user();

                    if ($user->hasPermissionTo('Criar Séries')) {
                        return true;
                    }

                    return false;
                })
                ->modalHeading('Criar Série')
                ->schema(
                    fn() => SerieResource::form(Schema::make())
                        ->getComponents()
                )
                ->createAnother(false)
                ->color('primary')
                ->successNotificationTitle('Série criada!'),

            CreateAction::make()
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
