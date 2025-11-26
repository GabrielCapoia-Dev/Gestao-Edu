<?php

namespace App\Filament\Resources\ProfessorResource\Pages;

use App\Filament\Resources\ProfessorResource;
use App\Services\ProfessorService;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Auth;

class ManageProfessors extends ManageRecords
{
    protected static string $resource = ProfessorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->slideOver()
                ->closeModalByClickingAway(false)
                ->mutateFormDataUsing(function (array $data): array {
                    return app(ProfessorService::class)
                        ->forcarVinculoComEscola($data, Auth::user());
                }),
        ];
    }
}
