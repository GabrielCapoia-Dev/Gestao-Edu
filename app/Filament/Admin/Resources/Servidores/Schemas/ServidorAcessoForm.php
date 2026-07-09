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
            ->description('Login criado automaticamente para professor. Nível Professor fica fixo; extras são opcionais.')
            ->icon('heroicon-o-key')
            ->schema([
                Select::make('roles_professor')
                    ->label('Nível do cargo (fixo)')
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
                    ->label('Níveis adicionais (opcional)')
                    ->helperText('Prefira deixar vazio: nesta fase o padrão é só Professor.')
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
                    ->label('Liberar acesso ao painel')
                    ->helperText('Desative para manter o login pendente de aprovação.')
                    ->default(true)
                    ->onColor('success')
                    ->offColor('danger'),

                Toggle::make('usar_permissoes_extras')
                    ->label('Permissões avulsas (admin)')
                    ->helperText('Só se precisar de exceção pontual além do nível Professor.')
                    ->default(false)
                    ->live()
                    ->visible(fn (): bool => $userService->ehAdmin($user)),

                Section::make('Permissões específicas')
                    ->collapsible()
                    ->collapsed()
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
                                ->columns(2)
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
                    $set('roles_adicionais', []);
                    $set('email_approved', true);
                    $set('usar_permissoes_extras', false);

                    return;
                }

                // Nesta fase: não sugerir extras legados no form de pessoa
                $set('roles_adicionais', []);
                $set('email_approved', (bool) $record->user->email_approved);
                $set('usar_permissoes_extras', false);
            })
            ->columns(2)
            ->columnSpanFull()
            ->collapsible()
            ->collapsed();
    }
}