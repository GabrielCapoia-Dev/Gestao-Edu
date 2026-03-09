<?php

namespace App\Services;

use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use App\Models\Aluno;
use App\Models\Turma;
use App\Models\User;
use App\Models\Serie;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextArea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Set;
use App\Models\Professor;

class CategoriaService
{

    public function configurarFormulario(Form $form, ?User $user): Form
    {
        return $form
            ->schema($this->schemaFormulario());
    }

    protected function schemaFormulario(): array
    {
        return [
            TextInput::make('nome')
                ->label('Nome')
                ->required()
                ->minLength(3)
                ->maxLength(100)
                ->rule('regex:/^[\p{L}\p{N}]+(?: [\p{L}\p{N}]+)*$/u')
                ->validationMessages([
                    'regex' => 'Use apenas letras, sem caracteres especiais.',
                ]),

            TextArea::make('descricao')
                ->label('Descrição')

        ];
    }


    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->paginated([10, 25, 50, 100])
            ->columns($this->colunasTabela($user))
            ->filters($this->filtrosTabela(), layout: FiltersLayout::AboveContent)
            ->actions($this->acoesTabela($user))
            ->bulkActions($this->acoesEmMassa($user));
    }


    protected function colunasTabela(?User $user): array
    {
        return [
            TextColumn::make('nome')->label('Nome')->sortable()->searchable(),
            TextColumn::make('descricao')->label('Descrição')->sortable()->searchable(),
            TextColumn::make('created_at')->label('Criado em')->date()->sortable()->searchable(),
            TextColumn::make('updated_at')->label('Atualizado em')->date()->sortable()->searchable(),
        ];
    }

    protected function filtrosTabela(): array
    {
        return [];
    }

    protected function acoesTabela(?User $user): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function acoesEmMassa(?User $user): array
    {
        return [
            DeleteBulkAction::make(),
        ];
    }
}
