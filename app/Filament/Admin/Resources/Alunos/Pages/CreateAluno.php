<?php

namespace App\Filament\Admin\Resources\Alunos\Pages;

use App\Exceptions\MatriculaAlunoBloqueadaException;
use App\Filament\Admin\Resources\Alunos\AlunoResource;
use App\Services\AlunoMovimentacaoService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateAluno extends CreateRecord
{
    protected static string $resource = AlunoResource::class;

    protected function handleRecordCreation(array $data): Model
    {
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
    }
}
