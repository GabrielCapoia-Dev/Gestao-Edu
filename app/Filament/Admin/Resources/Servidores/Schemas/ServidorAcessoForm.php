<?php

namespace App\Filament\Admin\Resources\Servidores\Schemas;

use App\Models\Role;
use App\Models\Servidor;
use App\Services\PessoaAcessoService;
use App\Services\UserService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

class ServidorAcessoForm
{
    public static function section(): Section
    {
        $user = Auth::user();
        $userService = app(UserService::class);
        $acessoService = app(PessoaAcessoService::class);

        return Section::make('Acesso ao sistema')
            ->description('Ao cadastrar como professor, o sistema cria o usuário automaticamente. Os níveis de acesso de professor não podem ser removidos.')
            ->schema([
                Select::make('roles_professor')
                    ->label('Níveis de acesso do professor')
                    ->options(fn (): array => Role::query()
                        ->whereIn('id', $acessoService->rolesImutaveisProfessor()->all())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->multiple()
                    ->default(fn (): array => $acessoService->rolesImutaveisProfessor()->all())
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),

                Select::make('roles_adicionais')
                    ->label('Níveis de acesso adicionais')
                    ->helperText('Opcional. Os níveis do professor permanecem fixos.')
                    ->options(fn (): array => $userService->opcoesDeRolesParaSelect($user))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->disableOptionWhen(
                        fn (string $value): bool => $acessoService->rolesImutaveisProfessor()->contains((int) $value)
                    )
                    ->columnSpanFull(),

                Toggle::make('email_approved')
                    ->label('Verificação de acesso')
                    ->helperText('Ative para permitir o acesso ao sistema.')
                    ->default(true)
                    ->onColor('success')
                    ->offColor('danger'),

                Toggle::make('usar_permissoes_extras')
                    ->label('Permissões adicionais')
                    ->helperText('Conceda permissões específicas além dos níveis de acesso.')
                    ->default(false)
                    ->live()
                    ->visible(fn (): bool => $userService->ehAdmin($user)),

                Section::make('Permissões específicas')
                    ->collapsible()
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => $get('usar_permissoes_extras') === true && $userService->ehAdmin($user))
                    ->schema(function (Get $get, ?Servidor $record) use ($userService, $user, $acessoService): array {
                        if (! $user || ! $userService->ehAdmin($user)) {
                            return [];
                        }

                        $todasPermissoes = Permission::orderBy('name')->get();
                        $roleIds = collect($acessoService->rolesImutaveisProfessor()->all())
                            ->merge(collect($get('roles_adicionais') ?? []))
                            ->map(fn ($id): int => (int) $id)
                            ->unique()
                            ->values()
                            ->all();

                        $permissoesDaRole = Role::query()
                            ->with('permissions')
                            ->whereIn('id', $roleIds)
                            ->get()
                            ->flatMap(fn ($role) => $role->permissions)
                            ->pluck('name')
                            ->unique()
                            ->toArray();

                        $permissoesDiretas = $record?->user?->getDirectPermissions()
                            ->pluck('name')
                            ->toArray() ?? [];

                        $agrupadas = $todasPermissoes->groupBy(fn ($perm) => explode(' ', $perm->name)[0]);
                        $schema = [];

                        foreach ($agrupadas as $grupo => $permissoes) {
                            $schema[] = CheckboxList::make("permissions_{$grupo}")
                                ->label($grupo)
                                ->options($permissoes->pluck('name', 'name')->toArray())
                                ->columns(3)
                                ->afterStateHydrated(function (callable $set) use (
                                    $grupo,
                                    $permissoes,
                                    $permissoesDaRole,
                                    $permissoesDiretas
                                ): void {
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
            ])
            ->afterStateHydrated(function (callable $set, ?Servidor $record) use ($acessoService): void {
                if (! $record?->user) {
                    return;
                }

                $imutaveis = $acessoService->rolesImutaveisProfessor();
                $rolesAdicionais = $record->user->roles
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->diff($imutaveis)
                    ->values()
                    ->all();

                $set('roles_adicionais', $rolesAdicionais);
                $set('email_approved', (bool) $record->user->email_approved);
                $set('usar_permissoes_extras', $record->user->getDirectPermissions()->isNotEmpty());
            })
            ->columns(2)
            ->columnSpanFull();
    }
}