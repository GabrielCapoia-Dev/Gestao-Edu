<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Models\User;
use App\Services\UserService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Spatie\Permission\Models\Permission;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        $service = app(UserService::class);

        return $schema->components([

            TextInput::make('codigo')->label('Código')->disabled()->dehydrated()->numeric()->visible(false)->minValue(100),
            TextInput::make('name')->label('Nome:')->required()->minLength(3)->maxLength(100)->rule('regex:/^[\p{L}\p{N}]+(?: [\p{L}\p{N}]+)*$/u')->validationMessages(['regex' => 'Use apenas letras, sem caracteres especiais.',]),
            TextInput::make('email')->label('E-mail')->unique(ignoreRecord: true)->email()->required(),

            TextInput::make('password')
                ->label('Senha')
                ->password()
                ->revealable()
                ->helperText('Mín. 8 e máx. 30 caracteres. Deve conter letras maiúsculas, minúsculas, números e caracteres especiais.')
                ->minLength(8)
                ->maxLength(30)
                ->rules([
                    'nullable',
                    'max:30',
                    PasswordRule::min(8)
                        ->mixedCase()
                        ->numbers()
                        ->symbols(),
                ])
                ->dehydrateStateUsing(fn($state) => filled($state) ? Hash::make($state) : null)
                ->dehydrated(fn($state) => filled($state))
                ->required(fn(string $context): bool => $context === 'create')
                ->validationMessages([
                    'max' => 'A senha deve ter no máximo 30 caracteres.',
                ]),

            Select::make('role')
                ->label('Nivel de acesso')
                ->helperText('Necessário para delimitar as acoes do usuario no sistema.')
                ->relationship('roles', 'name', function (Builder $query) use ($service, $user) {
                    return $service->opcoesDeRoles($query, $user);
                })
                ->preload()
                ->required()
                ->disabled(
                    fn(string $context, ?User $record) =>
                    $service->desabilitarCampoRole($user, $record, $context)
                ),

            Toggle::make('email_approved')
                ->label('Verificação de acesso')
                ->helperText('Ative para permittir o acesso ao sistema.')
                ->onColor('success')
                ->offColor('danger')
                ->onIcon('heroicon-s-check')
                ->offIcon('heroicon-s-x-mark')
                ->default(true)
                ->visible(
                    fn(?User $record, string $context) =>
                    $service->podeVerToggleAprovacaoEmail($user, $record, $context)
                ),

            Toggle::make('usar_permissoes_extras')
                ->label('Permissões adicionais')
                ->helperText('Ative para conceder permissões específicas além do nível de acesso.')
                ->default(
                    fn(?User $record) =>
                    $record?->getDirectPermissions()->isNotEmpty()
                )
                ->onColor('warning')
                ->offColor('info')
                ->onIcon('heroicon-s-lock-open')
                ->offIcon('heroicon-s-lock-closed')
                ->disabled(fn() => ! $service->ehAdmin($user))
                ->live(),

            Components\Section::make('Permissões específicas')
                ->collapsible()
                ->columnSpanFull()
                ->description('Permissões herdadas do nível de acesso já vêm marcadas.')
                ->visible(fn(Get $get) => $get('usar_permissoes_extras') === true)
                ->schema(function (?User $record) use ($service, $user) {
                    if (! $user || ! $service->ehAdmin($user)) {
                        return [];
                    }

                    $todasPermissoes = Permission::orderBy('name')->get();

                    $permissoesDaRole = $record?->roles
                        ->flatMap(fn($role) => $role->permissions)
                        ->pluck('name')
                        ->toArray() ?? [];

                    $permissoesDiretas = $record?->getDirectPermissions()
                        ->pluck('name')
                        ->toArray() ?? [];

                    $agrupadas = $todasPermissoes->groupBy(function ($perm) {
                        return explode(' ', $perm->name)[0];
                    });

                    $schema = [];

                    foreach ($agrupadas as $grupo => $permissoes) {
                        $permissoesDoGrupoNaRole = collect($permissoes)
                            ->filter(fn($p) => in_array($p->name, $permissoesDaRole))
                            ->pluck('name')
                            ->toArray();

                        $helperText = '';
                        if (! empty($permissoesDoGrupoNaRole)) {
                            $helperText = '🔒 Herança da role: ' . implode(', ', $permissoesDoGrupoNaRole);
                        }

                        $schema[] = CheckboxList::make("permissions_{$grupo}")
                            ->label($grupo)
                            ->options($permissoes->pluck('name', 'name')->toArray())
                            ->columns(3)
                            ->helperText($helperText)
                            ->afterStateHydrated(function (callable $set) use (
                                $grupo,
                                $permissoes,
                                $permissoesDaRole,
                                $permissoesDiretas
                            ) {
                                $valoresMarcados = collect($permissoesDaRole)
                                    ->merge($permissoesDiretas)
                                    ->intersect($permissoes->pluck('name'))
                                    ->values()
                                    ->toArray();

                                $set("permissions_{$grupo}", $valoresMarcados);
                            })
                            ->dehydrated(true);
                    }

                    return $schema;
                }),


            Components\Section::make('Vínculo com Escola')
                ->icon('heroicon-o-identification')
                ->description('Aqui mostra se o usuário esta vinculado a uma escola.')
                ->schema([
                    Select::make('id_escola')
                        ->label('Escola')
                        ->options(fn() => $service->opcoesDeEscolasParaCampo($user))
                        ->searchable()
                        ->preload()
                        ->afterStateHydrated(function ($state, callable $set, ?User $record, string $operation) use ($service, $user) {
                            $set('id_escola', $service->escolaInicialParaForm($record, $user, $operation));
                        })
                        ->default(fn(?User $record) => $service->escolaInicialParaForm($record, $user, 'create'))
                        ->disabled(fn(string $operation) => $service->deveTravarCampoEscola($user, $operation))
                        ->dehydrated(true),
                ])
                ->visible(fn() => $user->hasPermissionTo('Editar Escola do Usuario')),

            Components\Section::make('Vínculo com Setor')
                ->icon('heroicon-o-building-office')
                ->description('Aqui mostra se o usuário esta vinculado a um setor.')
                ->schema([
                    Select::make('setor_id')
                        ->label('Setor')
                        ->relationship(
                            name: 'setor',
                            titleAttribute: 'nome',
                            modifyQueryUsing: fn($query) => $query->where('ativo', true)
                        )
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->visible(fn() => $service->podeVisualizarSetor($user))
                        ->disabled(fn(string $context) => ! $service->podeEditarSetor($user, $context))
                        ->dehydrated(
                            fn() => $service->podeEditarSetor($user, 'edit')
                                || $service->podeEditarSetor($user, 'create')
                        ),
                ])
                ->visible(fn() => $user->hasPermissionTo('Editar Setor do Usuário')),

        ]);
    }
}
