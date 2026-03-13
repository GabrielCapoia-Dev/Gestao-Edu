<?php

namespace App\Services;

use App\Models\User;
use App\Models\Setor;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Support\Facades\Auth;
use App\Models\Escola;
use App\Models\IgnoredUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use App\Services\UserService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;

class SetorService
{
    /* =========================
     * TABELA
     * ========================= */

    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->columns($this->colunasTabela())
            ->recordActions($this->acoesTabela())
            ->toolbarActions($this->acoesEmMassa())
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    private function colunasTabela(): array
    {
        return [
            TextColumn::make('nome')
                ->label('Nome')
                ->searchable()
                ->sortable(),

            TextColumn::make('status')
                ->label('Status')
                ->sortable(),

            TextColumn::make('alterado_por')
                ->label('Alterado por')
                ->toggleable(),

            IconColumn::make('ativo')
                ->label('Ativo')
                ->boolean(),

            TextColumn::make('updated_at')
                ->label('Atualizado')
                ->since()
                ->sortable(),
        ];
    }

    private function acoesTabela(): array
    {
        return [
            Action::make('historico')
                ->label('Histórico')
                ->icon('heroicon-o-clock')
                ->slideOver()
                ->modalWidth('4xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->modalContent(fn (Setor $record) =>
                    view('components.setor.historico', [
                        'historico' => $record->historicoCompleto(),
                    ])
                ),

            EditAction::make()
                ->fillForm(fn (Setor $record) => [
                    'nome' => $record->nome,
                    'status' => $record->status,
                ])
                ->using(function (Setor $record, array $data): Setor {

                    $alterou = false;

                    foreach (['nome', 'status'] as $campo) {
                        if ($record->{$campo} != ($data[$campo] ?? null)) {
                            $alterou = true;
                            break;
                        }
                    }

                    if ($alterou) {

                        $record->update(['ativo' => false]);

                        return Setor::create([
                            ...$data,
                            'ativo' => true,
                            'registro_anterior_id' => $record->id,
                        ]);
                    }

                    return $record;
                }),

            DeleteAction::make(),
        ];
    }

    private function acoesEmMassa(): array
    {
        return [
            DeleteBulkAction::make(),
        ];
    }

    /* =========================
     * FORM
     * ========================= */

    public function configurarFormulario(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dados Gerais')
                ->schema([
                    Grid::make(2)->schema([

                        TextInput::make('nome')
                            ->required()
                            ->minLength(3)
                            ->maxLength(255),

                        Select::make('status')
                            ->required()
                            ->options([
                                'Ativo' => 'Ativo',
                                'Inativo' => 'Inativo',
                            ]),
                    ]),
                ]),
        ]);
    }
}
