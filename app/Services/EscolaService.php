<?php

namespace App\Services;

use App\Filament\Admin\Actions\VincularSetorBulkAction;
use App\Filament\Admin\Resources\Lotacoes\LotacaoResource;
use App\Models\Escola;
use App\Models\LocalTrabalho;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Layout\Grid as TableGrid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

class EscolaService
{
    public function __construct(
        protected UserService $userService,
    ) {}

    public function podeEditarCodigoEscola(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('editCodigo', LocalTrabalho::class);
    }

    /** Configura a tabela completa (paginações, colunas, filtros, ações, ordenação). */
    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['setor:id,nome,parent_id,path'])
                ->withCount('lotacoes'))
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5)
            ->columns($this->colunasTabela())
            ->filters([
                SelectFilter::make('nao_e_escola')
                    ->label('Tipo')
                    ->options([
                        '0' => 'Escola',
                        '1' => 'Local não escolar',
                    ]),
            ])
            ->recordAction(null)
            ->recordUrl(null)
            ->recordActions($this->acoesTabela($user), RecordActionsPosition::AfterContent)
            ->groupedBulkActions($this->acoesEmMassa($user))
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    private function colunasTabela(): array
    {
        return [
            TextColumn::make('nome')
                ->label('Nome')
                ->description(fn (LocalTrabalho $record): string => filled($record->codigo)
                    ? "Código: {$record->codigo}"
                    : 'Código não informado')
                ->wrap()
                ->sortable()
                ->searchable(['nome', 'codigo'])
                ->weight('bold')
                ->extraAttributes(['class' => 'local-card-name'], merge: true),

            TableGrid::make([
                'default' => 1,
                'sm' => 2,
                'lg' => 3,
                'xl' => 4,
            ])
                ->schema([
                    TextColumn::make('nao_e_escola')
                        ->label('Tipo')
                        ->description('Tipo', position: 'above')
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Local não escolar' : 'Escola')
                        ->color(fn (bool $state): string => $state ? 'warning' : 'info')
                        ->extraAttributes(['class' => 'local-card-field'], merge: true),

                    TextColumn::make('lotacoes_count')
                        ->label('Lotações')
                        ->description('Lotações', position: 'above')
                        ->badge()
                        ->icon('heroicon-o-rectangle-stack')
                        ->formatStateUsing(fn (int|string|null $state): string => match ((int) $state) {
                            0 => 'Nenhuma lotação',
                            1 => '1 lotação',
                            default => ((int) $state).' lotações',
                        })
                        ->color(fn (int|string|null $state): string => (int) $state > 0 ? 'success' : 'gray')
                        ->sortable(['lotacoes_count'])
                        ->extraAttributes(['class' => 'local-card-field local-card-field--lotacoes'], merge: true),

                    TextColumn::make('setor.nome_completo')
                        ->label('Setor')
                        ->description('Setor', position: 'above')
                        ->icon('heroicon-o-map-pin')
                        ->placeholder('—')
                        ->wrap()
                        ->toggleable()
                        ->extraAttributes(['class' => 'local-card-field'], merge: true),

                    TextColumn::make('email')
                        ->label('E-mail')
                        ->description('E-mail', position: 'above')
                        ->icon('heroicon-o-envelope')
                        ->placeholder('—')
                        ->wrap()
                        ->toggleable()
                        ->extraAttributes(['class' => 'local-card-field local-card-field--contact'], merge: true),

                    TextColumn::make('telefone')
                        ->label('Telefone')
                        ->description('Telefone', position: 'above')
                        ->icon('heroicon-o-phone')
                        ->placeholder('—')
                        ->toggleable()
                        ->extraAttributes(['class' => 'local-card-field local-card-field--contact'], merge: true),

                    TextColumn::make('localizacao_resumo')
                        ->label('Localização')
                        ->description('Localização', position: 'above')
                        ->icon('heroicon-o-map')
                        ->state(fn (LocalTrabalho $record): string => collect([
                            $record->bairro,
                            collect([$record->cidade, $record->estado])->filter()->implode('/'),
                        ])->filter()->implode(' · ') ?: '—')
                        ->wrap()
                        ->toggleable()
                        ->extraAttributes(['class' => 'local-card-field'], merge: true),

                    TextColumn::make('updated_at')
                        ->label('Atualizado em')
                        ->description('Atualizado em', position: 'above')
                        ->dateTime('d/m/Y H:i')
                        ->sortable()
                        ->extraAttributes(['class' => 'local-card-field'], merge: true),

                    TextColumn::make('created_at')
                        ->label('Criado em')
                        ->description('Criado em', position: 'above')
                        ->dateTime('d/m/Y H:i')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->extraAttributes(['class' => 'local-card-field'], merge: true),
                ])
                ->extraAttributes(['class' => 'local-card-main-grid']),
        ];
    }

    private function acoesTabela(?User $user): array
    {
        return [
            Action::make('lotacoes')
                ->label('Lotações')
                ->icon('heroicon-o-rectangle-stack')
                ->color('gray')
                ->url(fn (LocalTrabalho $record): string => LotacaoResource::getUrl('index', [
                    'tableFilters' => [
                        'escola_id' => ['value' => $record->getKey()],
                    ],
                ])),

            EditAction::make()
                ->fillForm(function (LocalTrabalho $record): array {
                    return [
                        'codigo' => $record->codigo,
                        'nao_e_escola' => $record->nao_e_escola,
                        'nome' => $record->nome,
                        'email' => $record->email,
                        'telefone' => $record->telefone,
                        'setor_id' => $record->setor_id,
                        'logradouro' => $record->logradouro,
                        'numero' => $record->numero,
                        'bairro' => $record->bairro,
                        'cep' => $record->cep,
                        'cidade' => $record->cidade,
                        'estado' => $record->estado,
                        'complemento' => $record->complemento,
                    ];
                })
                ->using(fn (LocalTrabalho $record, array $data): LocalTrabalho => $this->atualizarEmLinha($record, $data)),

            DeleteAction::make()
                ->successNotification(null)
                ->using(function (LocalTrabalho $record) {

                    $possuiVinculo =
                        DB::table('users')->where('id_escola', $record->id)->exists() ||
                        DB::table('escola_user')->where('escola_id', $record->id)->exists() ||
                        DB::table('turmas')->where('id_escola', $record->id)->exists() ||
                        DB::table('professores')->where('id_escola', $record->id)->exists() ||
                        DB::table('pedidos')->where('escola_id', $record->id)->exists();

                    if ($possuiVinculo) {
                        Notification::make()
                            ->title('Ação bloqueada')
                            ->body('Este local de trabalho possui vínculos e não pode ser excluído.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $record->delete();

                    Notification::make()
                        ->title('Local de trabalho excluído com sucesso')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function acoesEmMassa(?User $user): array
    {
        return [
            VincularSetorBulkAction::make(
                ability: 'update',
                arguments: LocalTrabalho::class,
                recordsLabel: 'locais de trabalho selecionados',
                updateRecord: function (LocalTrabalho $record, int $setorId): void {
                    $this->atualizarEmLinha($record, [
                        'nome' => $record->nome,
                        'email' => $record->email,
                        'telefone' => $record->telefone,
                        'setor_id' => $setorId,
                        'logradouro' => $record->logradouro,
                        'numero' => $record->numero,
                        'bairro' => $record->bairro,
                        'cep' => $record->cep,
                        'cidade' => $record->cidade,
                        'estado' => $record->estado,
                        'complemento' => $record->complemento,
                    ]);
                },
            ),
        ];
    }

    public function atualizarEmLinha(LocalTrabalho $record, array $data): LocalTrabalho
    {
        $camposVerificar = [
            'nome',
            'email',
            'telefone',
            'setor_id',
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

        if (! $alterou) {
            return $record;
        }

        $record->fill($data);
        $record->ativo = true;
        $record->save();

        return $record->fresh();
    }

    public static function configurarFormulario(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Dados Gerais')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([

                            Toggle::make('nao_e_escola')
                                ->label('Não é uma escola')
                                ->default(false)
                                ->disabled(fn (?LocalTrabalho $record): bool => $record !== null)
                                ->helperText(fn (?LocalTrabalho $record): string => $record
                                    ? 'A classificação não pode ser alterada após o cadastro.'
                                    : 'Marque para cadastrar um local de trabalho que não pertence ao contexto pedagógico.')
                                ->columnSpanFull(),

                            TextInput::make('nome')
                                ->label('Nome')
                                ->required()
                                ->minLength(3)
                                ->maxLength(100),

                            TextInput::make('email')
                                ->label('E-mail')
                                ->required()
                                ->email()
                                ->maxLength(150),

                            TextInput::make('telefone')
                                ->label('Telefone')
                                ->mask('(99)99999-9999')
                                ->rules(['regex:/^\(\d{2}\)\d{5}-\d{4}$/'])
                                ->validationMessages([
                                    'regex' => 'O telefone deve estar no formato (99)99999-9999',
                                ])
                                ->maxLength(14),

                            Select::make('setor_id')
                                ->label('Setor')
                                ->options(fn () => app(UserSetorAccessService::class)->optionsForSelect(auth()->user()))
                                ->default(fn () => app(UserSetorAccessService::class)->primarySetorId(auth()->user()))
                                ->searchable()
                                ->required(),
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
                                    ->disabled(fn (Get $get) => blank($get('cep')))
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
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $cep = preg_replace('/[^0-9]/', '', $state);
                                        if (strlen($cep) !== 8) {
                                            return;
                                        }

                                        try {
                                            $response = Http::timeout(5)->get("https://viacep.com.br/ws/{$cep}/json/");
                                            if ($response->successful() && ! $response->json('erro')) {
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
                                    ]),

                                TextInput::make('cidade')
                                    ->label('Cidade')
                                    ->maxLength(100)
                                    ->columnSpan(3)
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

                Section::make('Lotações')
                    ->description('Cadastre os códigos de lotação vinculados a este local de trabalho.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('lotacoes')
                            ->label('Lotações')
                            ->relationship()
                            ->schema([
                                TextInput::make('codigo')
                                    ->label('Código')
                                    ->required()
                                    ->maxLength(100)
                                    ->distinct(),

                                TextInput::make('nome')
                                    ->label('Nome da lotação')
                                    ->required()
                                    ->maxLength(150),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Adicionar lotação')
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** Opções de escolas conforme perfil: Admin vê todas; secretário só a sua. */
    public function opcoesDeEscolasParaUsuario(?User $user): array
    {
        $access = app(UserSetorAccessService::class);

        if ($access->hasGlobalAccess($user)) {
            return $this->opcoesDeEscolas();
        }

        $setorIds = $access->visibleSetorIds($user);

        if ($setorIds !== []) {
            return Escola::query()
                ->where('ativo', true)
                ->whereIn('setor_id', $setorIds)
                ->orderBy('nome')
                ->pluck('nome', 'id')
                ->toArray();
        }

        return Escola::query()
            ->where('ativo', true)
            ->whereKey($user->id_escola)
            ->pluck('nome', 'id')
            ->toArray();
    }

    /** Opções de escolas ordenadas. */
    public function opcoesDeEscolas(): array
    {
        return Escola::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }
}
