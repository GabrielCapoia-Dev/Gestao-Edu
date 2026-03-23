<?php

namespace App\Filament\Admin\Resources\Professors\Pages;

use App\Filament\Admin\Resources\Professors\ProfessorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use App\Services\ProfessorService;
use Illuminate\Support\Facades\Auth;


class ManageProfessors extends ManageRecords
{
    protected static string $resource = ProfessorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->slideOver()
                ->closeModalByClickingAway(false)
                ->mutateDataUsing(function (array $data): array {
                    return app(ProfessorService::class)
                        ->forcarVinculoComEscola($data, Auth::user());
                }),
        ];
    }
}
