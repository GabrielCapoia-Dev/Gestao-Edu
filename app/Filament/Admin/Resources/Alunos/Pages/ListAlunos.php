<?php

namespace App\Filament\Admin\Resources\Alunos\Pages;

use App\Exceptions\MatriculaAlunoBloqueadaException;
use App\Filament\Admin\Resources\Alunos\AlunoResource;
use App\Services\AlunoMovimentacaoService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ListAlunos extends ListRecords
{
    protected static string $resource = AlunoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->slideOver()
                ->modalWidth('3xl')
                ->using(function (array $data): Model {
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
}
