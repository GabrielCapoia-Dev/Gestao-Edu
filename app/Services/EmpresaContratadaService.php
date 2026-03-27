<?php

namespace App\Services;

use App\Models\EmpresaContratada;
use Filament\Forms\Form;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\IconColumn;
use App\Filament\Clusters\AlunoCluster\Resources\AlunoResource;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use App\Services\UserService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;


class EmpresaContratadaService
{
    /*
    |--------------------------------------------------------------------------
    | Formulário
    |--------------------------------------------------------------------------
    */

    public function configurarFormulario(Schema $schema): Schema
    {
        return $schema->components($this->schemaFormulario());
    }

    protected function schemaFormulario(): array
    {
        return [

            Section::make('Dados da Empresa')
                ->columnSpanFull()
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

                    ]),
                ]),

            Section::make('Endereço')
                ->columnSpanFull()
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
            ->recordActions($this->acoesTabela())
            ->toolbarActions($this->acoesEmMassa())
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

            DeleteAction::make()
                ->using(function (EmpresaContratada $record) {

                    if ($record->contratos()->exists()) {

                        Notification::make()
                            ->title('Ação bloqueada')
                            ->body('Esta empresa possui contratos vinculados e não pode ser excluída.')
                            ->danger()
                            ->send();

                        return; // impede o delete
                    }

                    $record->delete();
                }),
        ];
    }

    private function acoesEmMassa(): array
    {
        return [
            DeleteBulkAction::make()
                ->using(function ($records) {

                    foreach ($records as $record) {
                        if ($record->contratos()->exists()) {

                            Notification::make()
                                ->title('Ação bloqueada')
                                ->body('Uma ou mais empresas possuem contratos vinculados.')
                                ->danger()
                                ->send();

                            return;
                        }
                    }

                    foreach ($records as $record) {
                        $record->delete();
                    }
                }),
        ];
    }
}
