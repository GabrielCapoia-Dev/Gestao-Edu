<?php

namespace App\Services;

use App\Models\User;
use App\Models\Escola;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\ViewField;

class EscolaService
{
    public function __construct(
        protected UserService $userService,
    ) {}

    /** Configura a tabela completa (paginações, colunas, filtros, ações, ordenação). */
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

            TextColumn::make('codigo')
                ->label('Código')
                ->sortable()
                ->searchable(),

            TextColumn::make('nome')
                ->label('Nome')
                ->wrap()
                ->sortable()
                ->searchable(),

            TextColumn::make('email')
                ->label('Email')
                ->toggleable(),

            TextColumn::make('telefone')
                ->label('Telefone')
                ->toggleable(),

            TextColumn::make('cidade')
                ->label('Cidade')
                ->toggleable(),

            TextColumn::make('estado')
                ->label('UF')
                ->toggleable(),

            TextColumn::make('updated_at')
                ->label('Atualizado')
                ->since()
                ->sortable(),

            TextColumn::make('created_at')
                ->label('Criado')
                ->since()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }


    private function acoesTabela(?User $user): array
    {
        return [
            Action::make('historico')
                ->label('Histórico')
                ->icon('heroicon-o-clock')
                ->slideOver()
                ->color('warning')
                ->modalHeading('Histórico da Escola')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->modalContent(function (Escola $record) {

                    $historico = Escola::where('codigo', $record->codigo)
                        ->orderByDesc('created_at')
                        ->get();

                    return view('components.escola.historico', [
                        'historico' => $historico,
                    ]);
                }),

            EditAction::make()
                ->fillForm(function (Escola $record): array {
                    return [
                        'codigo' => $record->codigo,
                        'nome' => $record->nome,
                        'email' => $record->email,
                        'telefone' => $record->telefone,
                        'logradouro' => $record->logradouro,
                        'numero' => $record->numero,
                        'bairro' => $record->bairro,
                        'cep' => $record->cep,
                        'cidade' => $record->cidade,
                        'estado' => $record->estado,
                        'complemento' => $record->complemento,
                    ];
                })
                ->using(function (Escola $record, array $data): Escola {

                    $camposVerificar = [
                        'nome',
                        'email',
                        'telefone',
                        'logradouro',
                        'numero',
                        'bairro',
                        'cep',
                        'cidade',
                        'estado',
                        'complemento',
                    ];

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

                        // Desativa registro atual
                        $record->update(['ativo' => false]);

                        // Cria nova versão
                        return Escola::create([
                            ...$data,
                            'codigo' => $record->codigo, // mantém código original
                            'ativo' => true,
                            'registro_anterior_id' => $record->id,
                        ]);
                    }

                    return $record;
                }),

            DeleteAction::make(),
        ];
    }


    private function acoesEmMassa(?User $user): array
    {
        return [];
    }

    // Configura o formulário completo (campos, ações, etc.)
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

                        TextInput::make('codigo')
                            ->label('Código')
                            ->required()
                            ->maxLength(3)
                            ->minLength(3),

                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->minLength(3)
                            ->maxLength(100),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(150),

                        TextInput::make('telefone')
                            ->label('Telefone')
                            ->tel()
                            ->maxLength(20),
                    ]),
                ]),

            Section::make('Endereço')
                ->schema([
                    Grid::make(3)->schema([

                        TextInput::make('logradouro')
                            ->label('Logradouro')
                            ->maxLength(150),

                        TextInput::make('numero')
                            ->label('Número')
                            ->maxLength(20),

                        TextInput::make('bairro')
                            ->label('Bairro')
                            ->maxLength(100),

                        TextInput::make('cep')
                            ->label('CEP')
                            ->maxLength(9),

                        TextInput::make('cidade')
                            ->label('Cidade')
                            ->maxLength(100),

                        TextInput::make('estado')
                            ->label('Estado')
                            ->maxLength(2),

                        TextInput::make('complemento')
                            ->label('Complemento')
                            ->columnSpanFull(),
                    ]),
                ]),
        ];
    }


    /** Opções de escolas conforme perfil: Admin vê todas; secretário só a sua. */
    public function opcoesDeEscolasParaUsuario(?User $user): array
    {
        if (app(UserService::class)->ehAdmin($user) || empty($user?->id_escola)) {
            return $this->opcoesDeEscolas();
        }

        return Escola::query()
            ->whereKey($user->id_escola)
            ->pluck('nome', 'id')
            ->toArray();
    }

    /** Opções de escolas ordenadas. */
    public function opcoesDeEscolas(): array
    {
        return Escola::query()
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }
}
