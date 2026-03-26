<?php

namespace App\Filament\Admin\Resources\FuncionarioAdministrativos\Pages;

use App\Filament\Admin\Resources\FuncionarioAdministrativos\FuncionarioAdministrativoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use App\Models\Professor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Actions\Action;
use App\Models\FuncaoAdministrativa;

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
            Action::make('criarFuncao')
                ->label('Nova Função Administrativa')
                ->icon('heroicon-o-plus')
                ->modalHeading('Cadastrar Função Administrativa')
                ->modalSubmitActionLabel('Salvar')
                ->schema([
                    TextInput::make('nome')
                        ->required()
                        ->maxLength(255),

                    Toggle::make('tem_relacao_turma')
                        ->required(),
                ])
                ->action(function (array $data) {
                    FuncaoAdministrativa::create($data);
                })
                ->successNotificationTitle('Função criada com sucesso'),

        ];
    }
}
