<?php

namespace App\Filament\Admin\Resources\Alunos\Pages;

use App\Exceptions\MatriculaAlunoBloqueadaException;
use App\Filament\Admin\Resources\Alunos\AlunoResource;
use App\Services\AlunoMovimentacaoService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditAluno extends EditRecord
{
    protected static string $resource = AlunoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        try {
            app(AlunoMovimentacaoService::class)->bloquearSeCgmAtivo(
                (string) ($data['cgm'] ?? ''),
                (int) ($data['id_turma'] ?? 0),
                Auth::user(),
                $this->record
            );
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

        return $data;
    }
}
