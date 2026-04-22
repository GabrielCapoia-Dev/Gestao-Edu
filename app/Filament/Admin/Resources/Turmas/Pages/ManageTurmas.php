<?php

namespace App\Filament\Admin\Resources\Turmas\Pages;

use App\Filament\Admin\Resources\Turmas\TurmaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use App\Filament\Admin\Resources\Series\SerieResource;
use App\Models\Serie;
use App\Services\TurmaService;
use App\Services\ProfessorEscolaVinculoService;
use Filament\Actions;
use Illuminate\Support\Facades\Auth;
use Filament\Schemas\Schema;

class ManageTurmas extends ManageRecords
{
    protected static string $resource = TurmaResource::class;
    protected TurmaService $turmaService;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make('nova_serie')
                ->label('Nova Série')
                ->icon('heroicon-o-clipboard-document-list')
                ->model(Serie::class)
                ->visible(function () {
                    /** @var \App\Models\User */
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

            Actions\CreateAction::make()
                ->using(function (array $data) {

                    $componentes = $data['componentes'] ?? [];
                    unset($data['componentes']);

                    $turma = static::getModel()::create($data);
                    $professoresIds = [];

                    foreach ($componentes as $componente) {
                        if (! isset($componente['componente_curricular_id'])) {
                            continue;
                        }

                        $professorId = isset($componente['professor_id']) ? (int) $componente['professor_id'] : null;
                        if ($professorId) {
                            $professoresIds[] = $professorId;
                        }

                        $turma->componentes()->syncWithoutDetaching([
                            $componente['componente_curricular_id'] => [
                                'professor_id' => $professorId,
                                'tem_professor' => filled($professorId),
                            ],
                        ]);
                    }

                    if ($professoresIds !== []) {
                        app(ProfessorEscolaVinculoService::class)->sincronizarPorProfessores($professoresIds);
                    }

                    return $turma;
                }),

        ];
    }
}
