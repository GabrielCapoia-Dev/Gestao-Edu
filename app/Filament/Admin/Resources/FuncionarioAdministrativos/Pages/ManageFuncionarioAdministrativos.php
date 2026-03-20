<?php

namespace App\Filament\Admin\Resources\FuncionarioAdministrativos\Pages;

use App\Filament\Admin\Resources\FuncionarioAdministrativos\FuncionarioAdministrativoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use App\Models\Professor;


class ManageFuncionarioAdministrativos extends ManageRecords
{
    protected static string $resource = FuncionarioAdministrativoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Vincular Função')
                ->modalHeading('Vincular Função Administrativa')
                ->modalSubmitActionLabel('Vincular')
                ->using(function (array $data): Professor {
                    // Busca o professor selecionado
                    $professor = Professor::findOrFail($data['professor_id']);
                    
                    // Atualiza com a função administrativa
                    $professor->update([
                        'funcao_administrativa_id' => $data['funcao_administrativa_id'],
                        'portaria' => $data['portaria'] ?? null,
                    ]);
                    
                    // Sincroniza as turmas se houver
                    if (isset($data['turmasFuncao']) && is_array($data['turmasFuncao'])) {
                        $professor->turmasFuncao()->sync($data['turmasFuncao']);
                    }
                    
                    return $professor->fresh();
                }),
        ];
    }
}
