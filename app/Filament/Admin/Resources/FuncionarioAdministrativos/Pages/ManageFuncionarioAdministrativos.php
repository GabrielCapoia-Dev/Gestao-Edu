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
use Illuminate\Contracts\View\View;

class ManageFuncionarioAdministrativos extends ManageRecords
{
    protected static string $resource = FuncionarioAdministrativoResource::class;

    public function getHeader(): ?View
{
    return view('filament.admin.resources.turmas.pages.manage-turmas-header', [
        'actions' => $this->getCachedHeaderActions(),

        'eyebrow' => 'Pedagógico',
        'title' => "Equipe Gestora",
        'description' => 'Gerencie a Equipe Gestora vinculados à escola, atribua funções e mantenha um registro atualizado da equipe.',
    ]);
}

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Vincular Função')
                ->modalHeading('Vincular Função Administrativa')
                ->modalSubmitActionLabel('Vincular')
                ->visible(fn() => FuncaoAdministrativa::query()->exists())
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
