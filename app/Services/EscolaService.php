<?php

namespace App\Services;

use App\Models\User;
use App\Models\Escola;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Filament\Schemas\Schema;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class EscolaService
{
    public function __construct(
        protected UserService $userService,
    ) {}

    public function podeEditarCodigoEscola(?User $user): bool
    {
        return $user?->hasPermissionTo('Editar Codigo da Escola');
    }

    /** Configura a tabela completa (paginações, colunas, filtros, ações, ordenação). */
    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->columns($this->colunasTabela())
            ->recordActions($this->acoesTabela($user))
            ->groupedBulkActions($this->acoesEmMassa($user))
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
                ->modalWidth('7xl')
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

            DeleteAction::make()
                ->successNotification(null)
                ->using(function (Escola $record) {

                    $possuiVinculo =
                        DB::table('users')->where('id_escola', $record->id)->exists() ||
                        DB::table('turmas')->where('id_escola', $record->id)->exists() ||
                        DB::table('professores')->where('id_escola', $record->id)->exists();

                    if ($possuiVinculo) {
                        Notification::make()
                            ->title('Ação bloqueada')
                            ->body('Esta escola possui vínculos e não pode ser excluída.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $record->delete();

                    Notification::make()
                        ->title('Escola excluída com sucesso')
                        ->success()
                        ->send();
                }),
        ];
    }


    private function acoesEmMassa(?User $user): array
    {
        return [];
    }

    public static function configurarFormulario(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Dados Gerais')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([

                            TextInput::make('codigo')
                                ->label('Código')
                                ->required()
                                ->maxLength(3)
                                ->minLength(3)
                                ->disabled(function (Get $get, $record, ?string $operation) {
                                    // No EDIT
                                    if ($operation !== 'create') {
                                        return false;
                                    }

                                    return !app(EscolaService::class)->podeEditarCodigoEscola(Auth::user());
                                }),

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
                                ->required()
                                ->mask('(99)99999-9999')
                                ->rules(['regex:/^\(\d{2}\)\d{5}-\d{4}$/'])
                                ->validationMessages([
                                    'regex' => 'O telefone deve estar no formato (99)99999-9999',
                                ])
                                ->maxLength(14),
                        ]),
                    ]),
                Section::make('Endereço')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('logradouro')
                                    ->label('Logradouro')
                                    ->maxLength(100)
                                    ->columnSpan(6)
                                    ->disabled(fn(Get $get) => blank($get('cep')))
                                    ->required()
                                    ->minLength(3)
                                    ->rule('regex:/^\p{L}+(?:\s\p{L}+)*$/u')
                                    ->validationMessages([
                                        'regex' => 'Use apenas letras e um espaço simples entre palavras.',
                                    ]),

                                TextInput::make('numero')
                                    ->label('Número')
                                    ->columnSpan(3)
                                    ->maxLength(6)
                                    ->nullable()
                                    ->mask('999999'),

                                TextInput::make('cep')
                                    ->label('CEP')
                                    ->columnSpan(3)
                                    ->mask('99999-999')
                                    ->rules(['regex:/^\d{5}-\d{3}$/'])
                                    ->validationMessages([
                                        'regex' => 'O CEP deve estar no formato 00000-000',
                                    ])
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $cep = preg_replace('/[^0-9]/', '', $state);
                                        if (strlen($cep) !== 8) return;

                                        try {
                                            $response = Http::timeout(5)->get("https://viacep.com.br/ws/{$cep}/json/");
                                            if ($response->successful() && !$response->json('erro')) {
                                                $data = $response->json();
                                                $set('logradouro', $data['logradouro'] ?? '');
                                                $set('bairro', $data['bairro'] ?? '');
                                                $set('cidade', $data['localidade'] ?? '');
                                                $set('estado', $data['uf'] ?? '');
                                            }
                                        } catch (\Exception $e) {
                                        }
                                    }),
                            ]),

                        Grid::make(12)
                            ->schema([
                                TextInput::make('bairro')
                                    ->label('Bairro')
                                    ->columnSpan(3)
                                    ->maxLength(100)
                                    ->minLength(2)
                                    ->rule('regex:/^\p{L}+(?:\s\p{L}+)*$/u')
                                    ->validationMessages([
                                        'regex' => 'Use apenas letras e um espaço simples entre palavras.',
                                    ])
                                    ->required(),

                                TextInput::make('cidade')
                                    ->label('Cidade')
                                    ->maxLength(100)
                                    ->columnSpan(3)
                                    ->required()
                                    ->minLength(3)
                                    ->rule('regex:/^\p{L}+(?:\s\p{L}+)*$/u')
                                    ->validationMessages([
                                        'regex' => 'Use apenas letras e um espaço simples entre palavras.',
                                    ]),

                                TextInput::make('estado')
                                    ->label('UF')
                                    ->maxLength(2)
                                    ->placeholder('PR, SP, RJ...')
                                    ->columnSpan(2)
                                    ->required()
                                    ->rule('regex:/^\p{L}+(?:\s\p{L}+)*$/u')
                                    ->validationMessages([
                                        'regex' => 'Use apenas letras e um espaço simples entre palavras.',
                                    ]),

                                TextInput::make('complemento')
                                    ->label('Complemento')
                                    ->maxLength(100)
                                    ->nullable()
                                    ->columnSpan(4)

                                    ->placeholder('Ex.: Próximo ao Supermercado')
                                    ->minLength(3)
                                    ->rule('regex:/^\p{L}+(?:\s\p{L}+)*$/u')
                                    ->validationMessages([
                                        'regex' => 'Use apenas letras e um espaço simples entre palavras.',
                                    ]),
                            ]),
                    ]),
            ]);
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
