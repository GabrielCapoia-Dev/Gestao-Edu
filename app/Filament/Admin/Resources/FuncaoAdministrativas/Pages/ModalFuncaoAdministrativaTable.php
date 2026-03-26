<?php

namespace App\Livewire;

use App\Models\FuncaoAdministrativa;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Livewire\Component;

class ModalFuncaoAdministrativaTable extends Component implements HasTable, HasForms, HasActions
{
    use InteractsWithTable;
    use InteractsWithForms;
    use InteractsWithActions;

    public function table(Table $table): Table
    {
        return $table
            ->query(FuncaoAdministrativa::query())
            ->columns([
                TextColumn::make('nome')
                    ->searchable(),
                IconColumn::make('tem_relacao_turma')
                    ->boolean()
                    ->label('Relação com Turma'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nova Função')
                    ->model(FuncaoAdministrativa::class)
                    ->schema([
                        TextInput::make('nome')->required()->maxLength(255),
                        Toggle::make('tem_relacao_turma')->required(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema([
                        TextInput::make('nome')->required()->maxLength(255),
                        Toggle::make('tem_relacao_turma')->required(),
                    ]),
                DeleteAction::make(),
            ]);
    }

    public function render()
    {
        return view('livewire.funcao-administrativa-manager');
    }
}
