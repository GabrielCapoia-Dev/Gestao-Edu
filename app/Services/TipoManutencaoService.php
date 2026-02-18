<?php

namespace App\Services;

use App\Models\User;
use App\Models\TipoManutencao;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;

class TipoManutencaoService
{

    /**
     * Regras Básicas de Permissões para Tipo de Manutenção
     */





    /**
     *  Regras para formulários de tipo de manutenção
     */
    public function configurarFormulario(Form $form): Form
    {
        return $form->schema($this->schemaFormulario());
    }


    protected function schemaFormulario(): array
    {
        return [
            Section::make('Dados Gerais')
                ->schema([
                    Grid::make(2)->schema([

                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->minLength(3)
                            ->maxLength(255),

                        Textarea::make('descricao')
                            ->label('Descrição')
                            ->columnSpanFull(),
                    ]),
                ]),
        ];
    }


    /**
     * Regras para tabelas de tipo de manutenção
     */
    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->paginated([10, 25, 50, 100])
            ->columns($this->colunasTabela())
            ->actions($this->acoesTabela($user))
            ->bulkActions($this->acoesEmMassa($user))
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    private function colunasTabela(): array
    {
        return [
            TextColumn::make('nome')
                ->label('Nome')
                ->sortable()
                ->searchable(),

            TextColumn::make('descricao')
                ->label('Descrição')
                ->limit(50)
                ->toggleable(),

            IconColumn::make('ativo')
                ->label('Ativo')
                ->boolean(),

            TextColumn::make('updated_at')
                ->label('Atualizado')
                ->sortable(),

            TextColumn::make('created_at')
                ->label('Criado')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }


    /**
     * Regras para filtros de tipo de manutenção
     * 
     */


    /**
     * Regras para ações de tipo de manutenção
     * 
     */
    private function acoesTabela(?User $user): array
    {
        return [
            Action::make('historico')
                ->label('Histórico')
                ->icon('heroicon-o-clock')
                ->slideOver()
                ->modalWidth('4xl')
                ->color('warning')
                ->modalHeading('Histórico do Tipo de Manutenção')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->modalContent(
                    fn(TipoManutencao $record) =>
                    view('components.tipo-manutencao.historico', [
                        'historico' => $record->historicoCompleto(),
                    ])
                ),

            EditAction::make()
                ->fillForm(function (TipoManutencao $record): array {
                    return [
                        'nome' => $record->nome,
                        'descricao' => $record->descricao,
                    ];
                })
                ->using(function (TipoManutencao $record, array $data): TipoManutencao {

                    $camposVerificar = ['nome', 'descricao'];

                    $alterou = false;

                    foreach ($camposVerificar as $campo) {
                        $original = $record->{$campo};
                        $novo = $data[$campo] ?? null;

                        if ($original != $novo) {
                            $alterou = true;
                            break;
                        }
                    }

                    if ($alterou) {

                        $record->update(['ativo' => false]);

                        return TipoManutencao::create([
                            ...$data,
                            'ativo' => true,
                            'registro_anterior_id' => $record->id,
                            'alterado_por' => Auth::user()?->name,
                        ]);
                    }

                    return $record;
                }),

            DeleteAction::make(),
        ];
    }


    /**
     * Regras para ações em massa de tipo de manutenção
     * 
     */
    private function acoesEmMassa(?User $user): array
    {
        return [
            DeleteBulkAction::make(),
        ];
    }

    /**
     * Regras para relações de tipo de manutenção
     * 
     */
}
