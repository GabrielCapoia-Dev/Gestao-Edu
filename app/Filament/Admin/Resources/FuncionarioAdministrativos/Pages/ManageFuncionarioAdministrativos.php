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
use Filament\Schemas\Schema;

class ManageFuncionarioAdministrativos extends ManageRecords
{
    protected static string $resource = FuncionarioAdministrativoResource::class;

    protected function getHeaderActions(): array
    {

        /** @var Schema */
        $schema = Schema::class;


        return [
            CreateAction::make()
                ->label('Vincular Função')
                ->modalHeading('Vincular Função Administrativa')
                ->modalSubmitActionLabel('Vincular')
                ->visible(fn() => FuncaoAdministrativa::query()->exists())
                ->schema(FuncionarioAdministrativoResource::form($schema)->getComponents())
                ->using(function (array $data): Professor {
                    $professor = Professor::findOrFail($data['professor_id']);

                    $professor->update([
                        'funcao_administrativa_id' => $data['funcao_administrativa_id'],
                        'portaria' => $data['portaria'] ?? null,
                    ]);

                    if (isset($data['turmasFuncao']) && is_array($data['turmasFuncao'])) {
                        $professor->turmasFuncao()->sync($data['turmasFuncao']);
                    }

                    return $professor->fresh();
                })
                ->successNotificationTitle('Função vinculada com sucesso'),


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
