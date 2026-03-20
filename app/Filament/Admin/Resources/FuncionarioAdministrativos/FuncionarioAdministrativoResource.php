<?php

namespace App\Filament\Admin\Resources\FuncionarioAdministrativos;

use App\Filament\Admin\Resources\FuncionarioAdministrativos\Pages\ManageFuncionarioAdministrativos;
use App\Models\FuncionarioAdministrativo;
use Filament\Tables\Filters\SelectFilter;
use App\Models\FuncaoAdministrativa;
use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Actions\BulkActionGroup;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Models\EquipeGestora;
use UnitEnum;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;
use App\Models\Escola;
use App\Models\IgnoredUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Professor;
use App\Models\Turma;
use App\Services\UserService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Infolists\Components\TextEntry;


class FuncionarioAdministrativoResource extends Resource
{
    protected static ?string $model = EquipeGestora::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Briefcase;
    public static ?string $modelLabel = 'Equipe Gestora';
    protected static string|UnitEnum|null $navigationGroup = "Gestão Escolar";
    public static ?string $pluralModelLabel = 'Equipe Gestora';
    public static ?string $slug = 'equipe-gestora';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Formulário de CRIAÇÃO - seleciona professor existente
                Section::make('Selecionar Professor')
                    ->schema([
                        Select::make('professor_id')
                            ->label('Professor')
                            ->options(function () {
                                $user = Auth::user();
                                $userService = app(UserService::class);

                                $query = Professor::query()
                                    ->whereNull('funcao_administrativa_id');

                                // Aplica filtro por escola se não for admin
                                if (!$userService->ehAdmin($user) && $user->id_escola) {
                                    $query->where('id_escola', $user->id_escola);
                                }

                                return $query->get()
                                    ->mapWithKeys(fn($prof) => [
                                        $prof->id => "{$prof->nome} - {$prof->matricula} ({$prof->escola->nome})"
                                    ]);
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if ($state) {
                                    $professor = Professor::with('escola')->find($state);
                                    if ($professor) {
                                        $set('id_escola', $professor->id_escola);
                                    }
                                }
                                $set('turmasFuncao', []);
                            })
                            ->placeholder('Selecione um professor para vincular')
                            ->helperText('Apenas professores sem função administrativa são listados')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->visible(fn(?Professor $record) => $record === null),
                // Formulário de EDIÇÃO - mostra dados do professor (readonly)
                Section::make('Dados do Professor')
                    ->schema([
                        TextEntry::make('nome_display')
                            ->label('Nome')
                            ->content(fn(?Professor $record) => $record?->nome ?? '—'),

                        TextEntry::make('matricula_display')
                            ->label('Matrícula')
                            ->content(fn(?Professor $record) => $record?->matricula ?? '—'),

                        TextEntry::make('escola_display')
                            ->label('Escola')
                            ->content(fn(?Professor $record) => $record?->escola?->nome ?? '—'),

                        TextEntry::make('email_display')
                            ->label('E-mail')
                            ->content(fn(?Professor $record) => $record?->email ?? 'Não informado'),
                    ])
                    ->columns(2)
                    ->visible(fn(?Professor $record) => $record !== null),


                // Função Administrativa (comum para criar e editar)
                Section::make('Função Administrativa')
                    ->afterStateHydrated(function (?EquipeGestora $record, Set $set) {
                        if (!$record?->portaria) {
                            return;
                        }

                        [$numero, $ano] = explode('/', $record->portaria);

                        $set('portaria_numero', $numero);
                        $set('portaria_ano', $ano);
                    })
                    ->schema([
                        Select::make('funcao_administrativa_id')
                            ->label('Função Administrativa')
                            ->relationship('funcaoAdministrativa', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('turmasFuncao', []);
                                $set('selecionar_todas_turmas', false);
                            })
                            ->columnSpan(1),

                        Grid::make(2)
                            ->columnSpan(1)
                            ->schema([
                                TextInput::make('portaria_numero')
                                    ->label('Nº Portaria')
                                    ->placeholder('001')
                                    ->required()
                                    ->numeric()
                                    ->maxLength(10)
                                    ->suffix('/')
                                    ->dehydrated(false)
                                    ->columnSpan(1),

                                TextInput::make('portaria_ano')
                                    ->label('Ano Portaria')
                                    ->placeholder('2026')
                                    ->required()
                                    ->numeric()
                                    ->minLength(4)
                                    ->maxLength(4)
                                    ->dehydrated(false)
                                    ->rules(['integer', 'min:2000'])
                                    ->columnSpan(1),
                            ]),

                        Hidden::make('portaria')
                            ->dehydrateStateUsing(function (Get $get) {
                                $numero = $get('portaria_numero');
                                $ano = $get('portaria_ano');

                                if (!$numero || !$ano) {
                                    return null;
                                }

                                return "{$numero}/{$ano}";
                            })
                            ->required(),

                        Checkbox::make('selecionar_todas_turmas')
                            ->label('Unidade com apenas um(a) coordenador(a)')
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, bool $state, ?Professor $record) {
                                if ($state) {
                                    $idEscola = $record?->id_escola ?? $get('id_escola');

                                    if ($idEscola) {
                                        $todasTurmas = Turma::where('id_escola', $idEscola)
                                            ->pluck('id')
                                            ->toArray();

                                        $set('turmasFuncao', $todasTurmas);
                                    }
                                } else {
                                    $set('turmasFuncao', []);
                                }
                            })
                            ->visible(function (Get $get): bool {
                                $funcaoId = $get('funcao_administrativa_id');

                                if (!$funcaoId) {
                                    return false;
                                }

                                $funcao = FuncaoAdministrativa::find($funcaoId);
                                return $funcao?->tem_relacao_turma ?? false;
                            })
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        Select::make('turmasFuncao')
                            ->label('Turmas Vinculadas à Função')
                            ->relationship('turmasFuncao', 'nome')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function (Get $get, ?Professor $record) {
                                $idEscola = $record?->id_escola ?? $get('id_escola');

                                if (!$idEscola) {
                                    return [];
                                }

                                return Turma::where('id_escola', $idEscola)
                                    ->with(['serie'])
                                    ->get()
                                    ->mapWithKeys(fn($turma) => [
                                        $turma->id => "{$turma->serie->nome} - {$turma->nome} ({$turma->turno})"
                                    ]);
                            })
                            ->visible(function (Get $get): bool {
                                $funcaoId = $get('funcao_administrativa_id');

                                if (!$funcaoId) {
                                    return false;
                                }

                                $funcao = FuncaoAdministrativa::find($funcaoId);
                                return $funcao?->tem_relacao_turma ?? false;
                            })
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->columns(2),

                // Campo oculto para armazenar id_escola (usado nas options de turmas)
                Hidden::make('id_escola'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->modifyQueryUsing(function (Builder $query) {
                $user = Auth::user();
                $query = app(UserService::class)->aplicarFiltroPorEscolaDoUsuario($query, $user);

                return $query->whereNotNull('funcao_administrativa_id');
            })
            ->columns([
                TextColumn::make('escola.nome')
                    ->label('Escola')
                    ->sortable()
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('matricula')
                    ->label('Matrícula')
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->wrap(),

                TextColumn::make('funcaoAdministrativa.nome')
                    ->label('Função')
                    ->sortable(),

                TextColumn::make('portaria')
                    ->label('Portaria')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('turmas_funcao_count')
                    ->label('Turmas')
                    ->counts('turmasFuncao')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                TextColumn::make('turmas_vinculadas')
                    ->label('Turmas Vinculadas')
                    ->badge()
                    ->separator(',')
                    ->wrap()
                    ->getStateUsing(
                        fn($record) => $record->turmasFuncao()
                            ->with('serie')
                            ->get()
                            ->map(fn($t) => "{$t->serie->nome} - {$t->nome}")
                            ->toArray()
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('telefone')
                    ->label('Telefone')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('id_escola')
                    ->label('Escola')
                    ->relationship('escola', 'nome')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('funcao_administrativa_id')
                    ->label('Função')
                    ->relationship('funcaoAdministrativa', 'nome')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn($record) => "Detalhes - {$record->nome}")
                    ->modalWidth('3xl')
                    ->schema([
                        Section::make('Informações do Funcionário')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('escola.nome')
                                    ->label('Escola'),
                                \Filament\Infolists\Components\TextEntry::make('matricula')
                                    ->label('Matrícula')
                                    ->copyable(),
                                \Filament\Infolists\Components\TextEntry::make('nome')
                                    ->label('Nome')
                                    ->copyable(),
                                \Filament\Infolists\Components\TextEntry::make('funcaoAdministrativa.nome')
                                    ->label('Função')
                                    ->badge()
                                    ->color('warning'),
                                \Filament\Infolists\Components\TextEntry::make('portaria')
                                    ->label('Portaria')
                                    ->placeholder('Não informada'),
                                \Filament\Infolists\Components\TextEntry::make('email')
                                    ->label('E-mail')
                                    ->copyable()
                                    ->placeholder('Não informado'),
                                \Filament\Infolists\Components\TextEntry::make('telefone')
                                    ->label('Telefone')
                                    ->placeholder('Não informado'),
                            ])
                            ->columns(3),

                        Section::make('Turmas Vinculadas')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('turmas_funcao_info')
                                    ->label('')
                                    ->getStateUsing(function ($record) {
                                        $turmas = $record->turmasFuncao()->with('serie')->get();

                                        if ($turmas->isEmpty()) {
                                            return 'Nenhuma turma vinculada';
                                        }

                                        $html = '<div class="flex flex-wrap gap-2">';
                                        foreach ($turmas as $turma) {
                                            $html .= '<span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-primary-100 text-primary-800 dark:bg-primary-800 dark:text-primary-100">'
                                                . e($turma->serie->nome) . ' - ' . e($turma->nome)
                                                . '</span>';
                                        }
                                        $html .= '</div>';

                                        return $html;
                                    })
                                    ->html()
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn($record) => $record->funcaoAdministrativa?->tem_relacao_turma),
                    ]),

                EditAction::make(),

                // Action para remover função administrativa (volta a ser professor)
                Action::make('remover_funcao')
                    ->label('Remover Função')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(function (): bool {
                        /** @var \App\Models\User $user */
                        $user = Auth::user();
                        return $user->hasPermissionTo('Excluir Equipe Gestora');
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Remover Função Administrativa')
                    ->modalDescription(fn($record) => "Tem certeza que deseja remover a função administrativa de {$record->nome}? Ele voltará a aparecer na lista de professores.")
                    ->modalSubmitActionLabel('Sim, remover função')
                    ->action(function ($record) {
                        // Remove turmas vinculadas
                        $record->turmasFuncao()->detach();

                        // Remove função administrativa
                        $record->update([
                            'funcao_administrativa_id' => null,
                            'portaria' => null,
                        ]);
                    }),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFuncionarioAdministrativos::route('/'),
        ];
    }
}
