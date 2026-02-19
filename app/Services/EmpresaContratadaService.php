<?php

namespace App\Services;

use App\Models\EmpresaContratada;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

class EmpresaContratadaService
{
    /*
    |--------------------------------------------------------------------------
    | Formulário
    |--------------------------------------------------------------------------
    */

    public function configurarFormulario(Form $form): Form
    {
        return $form->schema($this->schemaFormulario());
    }

    protected function schemaFormulario(): array
    {
        return [

            Section::make('Dados da Empresa')
                ->schema([
                    Grid::make(2)->schema([

                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('cnpj')
                            ->label('CNPJ')
                            ->required()
                            ->mask('99.999.999/9999-99')
                            ->regex('/^\d{2}\.\d{3}\.\d{3}\/\d{4}\-\d{2}$/')
                            ->maxLength(18)
                            ->validationMessages([
                                'regex' => 'CNPJ inválido. Use o formato 00.000.000/0000-00',
                            ]),

                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('responsavel')
                            ->label('Responsável')
                            ->maxLength(255),

                        TextInput::make('telefone')
                            ->tel()
                            ->maxLength(20),

                        TextInput::make('numero_contrato')
                            ->label('Nº Contrato')
                            ->maxLength(255),
                    ]),
                ]),

            Section::make('Endereço')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('cep')->maxLength(10),
                        TextInput::make('logradouro')->columnSpan(2),

                        TextInput::make('numero'),
                        TextInput::make('complemento'),
                        TextInput::make('bairro'),

                        TextInput::make('cidade'),
                        TextInput::make('estado')->maxLength(2),
                    ]),
                ]),

            Toggle::make('ativo')
                ->label('Empresa Ativa')
                ->default(true),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Tabela
    |--------------------------------------------------------------------------
    */

    public function configurarTabela(Table $table): Table
    {
        return $table
            ->query(EmpresaContratada::query()->where('ativo', true))
            ->columns($this->colunasTabela())
            ->actions($this->acoesTabela())
            ->bulkActions($this->acoesEmMassa())
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    private function colunasTabela(): array
    {
        return [

            TextColumn::make('nome')
                ->label('Empresa')
                ->searchable()
                ->sortable(),

            TextColumn::make('cnpj')
                ->label('CNPJ')
                ->searchable(),

            TextColumn::make('responsavel')
                ->label('Responsável')
                ->searchable(),

            TextColumn::make('telefone')
                ->label('Telefone'),

            IconColumn::make('ativo')
                ->label('Ativa')
                ->boolean(),

            TextColumn::make('updated_at')
                ->label('Atualizado')
                ->since()
                ->sortable(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Ações
    |--------------------------------------------------------------------------
    */

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
                ->modalContent(
                    fn(EmpresaContratada $record) =>
                    view('components.empresas.historico', [
                        'historico' => $record->historico,
                    ])
                ),

            EditAction::make()
                ->using(function (EmpresaContratada $record, array $data): EmpresaContratada {

                    $campos = [
                        'nome',
                        'cnpj',
                        'email',
                        'responsavel',
                        'telefone',
                        'numero_contrato',
                        'cep',
                        'logradouro',
                        'numero',
                        'complemento',
                        'bairro',
                        'cidade',
                        'estado'
                    ];

                    $alterou = false;

                    foreach ($campos as $campo) {
                        if ($record->{$campo} != ($data[$campo] ?? null)) {
                            $alterou = true;
                            break;
                        }
                    }

                    if ($alterou) {

                        $record->update(['ativo' => false]);

                        return EmpresaContratada::create([
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

    private function acoesEmMassa(): array
    {
        return [
            DeleteBulkAction::make(),
        ];
    }
}
