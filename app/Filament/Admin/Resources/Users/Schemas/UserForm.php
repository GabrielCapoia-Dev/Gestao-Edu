<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Models\Role;
use App\Models\User;
use App\Services\PessoaAcessoService;
use App\Services\UserService;
use App\Services\UserSetorAccessService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;
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

            Components\Section::make('Vínculo com pessoa / cargo')
                ->description('Identidade e cargo pedagógico são cadastrados em Pessoas. Aqui aparece o espelho para orientar os níveis de acesso.')
                ->schema([
                    Placeholder::make('pessoa_cargo_resumo')
                        ->label('Situação')
                        ->content(function (?User $record): HtmlString {
                            if (! $record) {
                                return new HtmlString('Novo usuário: após salvar, vincule a ficha em <strong>Pessoas</strong> se for professor ou outro cargo.');
                            }

                            $record->loadMissing(['servidores.professores', 'professores']);
                            $acesso = app(PessoaAcessoService::class);
                            $ehProfessor = $acesso->usuarioEhProfessor($record);
                            $pessoa = $record->servidores->first()?->nome
                                ?? $record->professores->first()?->nome
                                ?? '—';

                            $cargo = $ehProfessor ? 'Professor' : 'Sem cargo pedagógico';
                            $rolesTravadas = $ehProfessor
                                ? $acesso->rolesImutaveisProfessor()->count().' nível(is) do cargo travado(s)'
                                : 'Nenhum nível travado por cargo';

                            return new HtmlString(
                                '<div class="space-y-1 text-sm">'
                                .'<div><span class="font-medium">Pessoa:</span> '.e($pessoa).'</div>'
                                .'<div><span class="font-medium">Cargo:</span> '.e($cargo).'</div>'
                                .'<div class="text-gray-500">'.e($rolesTravadas).'</div>'
                                .'</div>'
                            );
                        }),
                ])
                ->columnSpanFull()
                ->collapsible(),

            TextInput::make('codigo')->label('Código')->disabled()->dehydrated(false)->visible(false),
            TextInput::make('name')
                ->label('Nome:')
                ->required()
                ->minLength(3)
                ->maxLength(150)
                ->rule('regex:/^[\p{L}\p{N}][\p{L}\p{N}\'.\- ]*[\p{L}\p{N}.]?$/u')
                ->validationMessages([
                    'regex' => 'Use letras, espaços, hífen ou apóstrofo.',
                ]),
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
                ->visible(fn (?User $record, string $context): bool => $context === 'create'
                    || ($record && Gate::allows('resetPassword', $record)))
                ->validationMessages([
                    'max' => 'A senha deve ter no máximo 30 caracteres.',
                ]),

            Select::make('roles')
                ->label('Níveis de acesso adicionais')
                ->helperText('O nível funcional do cargo é preservado automaticamente.')
                ->options(fn () => $service->opcoesDeRolesParaSelect($user))
                ->multiple()
                ->searchable()
                ->live()
                ->preload()
                ->default(fn (?User $record): array => $record ? $service->idsNiveisAdicionais($record) : [])
                ->afterStateHydrated(function (callable $set, ?User $record) use ($service): void {
                    if ($record) {
                        $set('roles', $service->idsNiveisAdicionais($record));
                    }
                })
                ->dehydrated(true)
                ->visible(fn (?User $record): bool => $record
                    ? Gate::allows('applyPermissions', $record)
                    : Gate::allows('applyPermissionsAny', User::class)),

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
                ->visible(fn (?User $record): bool => $record
                    ? Gate::allows('applyPermissions', $record)
                    : Gate::allows('applyPermissionsAny', User::class))
                ->live(),

            Components\Section::make('Permissões específicas')
                ->collapsible()
                ->columnSpanFull()
                ->description('As permissões herdadas dos níveis de acesso ficam destacadas.')
                ->visible(fn(Get $get) => $get('usar_permissoes_extras') === true)
                ->schema(function (Get $get, ?User $record) use ($service, $user) {
                    if (! $user || ($record
                        ? ! Gate::allows('applyPermissions', $record)
                        : ! Gate::allows('applyPermissionsAny', User::class))) {
                        return [];
                    }

                    $todasPermissoes = $user->hasRole('Admin')
                        ? Permission::orderBy('name')->get()
                        : Permission::query()
                            ->whereIn('name', $user->getAllPermissions()->pluck('name'))
                            ->where('name', '!=', 'Aplicar Permissoes')
                            ->orderBy('name')
                            ->get();

                    $roleIds = collect($get('roles') ?? $record?->roles->pluck('id')->all() ?? [])
                        ->filter(fn($roleId) => filled($roleId))
                        ->map(fn($roleId) => (int) $roleId)
                        ->unique()
                        ->values()
                        ->all();

                    $permissoesDaRole = Role::query()
                        ->with('permissions')
                        ->whereIn('id', $roleIds)
                        ->get()
                        ->flatMap(fn($role) => $role->permissions)
                        ->pluck('name')
                        ->unique()
                        ->toArray();

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
                            $helperText = 'Herdadas dos níveis: ' . implode(', ', $permissoesDoGrupoNaRole);
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
                ->description('Aqui mostra se o usuário está vinculado a uma escola.')
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
                ->visible(fn (): bool => Gate::allows('editSchool', User::class)),

            Components\Section::make('Vínculo com Setor')
                ->icon('heroicon-o-building-office')
                ->description('Aqui mostra se o usuário está vinculado a um setor.')
                ->schema([
                    Select::make('setor_id')
                        ->label('Setor')
                        ->options(fn () => app(UserSetorAccessService::class)->optionsForSelect($user))
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
                ->visible(fn (): bool => Gate::allows('editSetor', User::class)),

        ]);
    }
}
