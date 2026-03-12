<?php

namespace App\Filament\Admin\Resources\Turmas\Pages;

use App\Filament\Admin\Resources\Turmas\TurmaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use App\Filament\Admin\Resources\Series\SerieResource;
use App\Models\Serie;
use App\Services\TurmaService;
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
                ->label('Nova Turma')
                ->icon('heroicon-o-users')
                ->mutateDataUsing(function (array $data): array {
                    /** @var \App\Services\TurmaService $service */
                    $service = app(TurmaService::class);

                    $data = $service->aplicarCodigo($data);
                    $data = $service->forcarVinculoComEscola($data, Auth::user());

                    return $data;
                }),
        ];
    }
}
