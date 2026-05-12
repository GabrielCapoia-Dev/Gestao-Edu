<?php

namespace App\Filament\Admin\Resources\Roles\Pages;

use App\Filament\Admin\Resources\Roles\RoleResource;
use App\Models\Role;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Illuminate\Contracts\View\View;
use Spatie\Permission\PermissionRegistrar;

class ManageRoles extends ManageRecords
{
    protected static string $resource = RoleResource::class;

    protected string $view = 'filament.admin.resources.roles.pages.manage-roles';

    public string $search = '';

    public array $expandedRoles = [];


    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Acesso',
            'title' => 'Niveis de Acesso',
            'description' => 'Gerencie os niveis de acesso para organizar as permissoes dos usuarios.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function createAction(): CreateAction
    {
        return CreateAction::make('create')
            ->using(function (array $data): Role {
                $role = Role::create([
                    'name' => $data['name'],
                    'guard_name' => 'web',
                    'setor_id' => $data['setor_id'] ?? null,
                ]);

                $permissoesSelecionadas = collect($data)
                    ->filter(fn($_, $key) => str_starts_with($key, 'permissions_'))
                    ->flatMap(fn($permissions) => is_array($permissions) ? $permissions : [$permissions])
                    ->filter(fn($permission) => filled($permission))
                    ->unique()
                    ->values()
                    ->all();

                $role->syncPermissions($permissoesSelecionadas);

                app(PermissionRegistrar::class)->forgetCachedPermissions();

                return $role;
            })
            ->after(function (): void {
                Notification::make()
                    ->title('Nivel de acesso criado')
                    ->success()
                    ->send();
            });
    }

    public function getOverviewCards(): array
    {
        $stats = $this->getOverviewStats();

        return [
            [
                'label' => 'Niveis cadastrados',
                'value' => number_format($stats['roles'], 0, ',', '.'),
                'description' => 'Perfis reutilizaveis para compor acessos.',
                'icon' => 'heroicon-o-shield-check',
                'tone' => 'amber',
            ],
            [
                'label' => 'Com permissoes',
                'value' => number_format($stats['roles_with_permissions'], 0, ',', '.'),
                'description' => 'Niveis que ja possuem capacidades definidas.',
                'icon' => 'heroicon-o-sparkles',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Catalogo de permissoes',
                'value' => number_format($stats['permissions'], 0, ',', '.'),
                'description' => 'A base que alimenta seus niveis contextuais.',
                'icon' => 'heroicon-o-key',
                'tone' => 'sky',
            ],
            [
                'label' => 'Atualizados hoje',
                'value' => number_format($stats['updated_today'], 0, ',', '.'),
                'description' => 'Mudancas recentes na matriz de acesso.',
                'icon' => 'heroicon-o-clock',
                'tone' => 'rose',
            ],
        ];
    }

    public function getHighlights(): array
    {
        return [
            'Niveis contextuais por tema de trabalho',
            'Permissoes agrupadas para configuracao mais rapida',
            'Criacao e edicao em slide-over sem sair da listagem',
        ];
    }

    public function getSupportItems(): array
    {
        return [
            [
                'title' => 'Modelo mais modular',
                'description' => 'Organize os niveis por contexto, como inventario, merenda, pedidos e cadastros. Depois, combine quantos forem necessarios em cada usuario.',
            ],
            [
                'title' => 'Menos manutencao manual',
                'description' => 'Quando um tema muda, ajuste o nivel correspondente e a alteracao se propaga para todos que usam aquela composicao.',
            ],
        ];
    }

    protected function getOverviewStats(): array
    {
        return [
            'roles' => Role::query()->count(),
            'roles_with_permissions' => Role::query()->has('permissions')->count(),
            'permissions' => Permission::query()->count(),
            'updated_today' => Role::query()->whereDate('updated_at', today())->count(),
        ];
    }

    public function getDesktopRoles(): Collection
    {
        $roles = Role::query()
            ->with(['setor', 'permissions' => fn($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        $search = Str::of($this->search)->trim()->lower()->toString();

        if (blank($search)) {
            return $roles->map(fn(Role $role): array => $this->mapRoleToCard($role));
        }

        return $roles
            ->filter(function (Role $role) use ($search): bool {
                if (str_contains(Str::lower(Str::ascii($role->name)), Str::ascii($search))) {
                    return true;
                }

                return $role->permissions->contains(function ($permission) use ($search): bool {
                    return str_contains(
                        Str::lower(Str::ascii((string) $permission->name)),
                        Str::ascii($search),
                    );
                });
            })
            ->map(fn(Role $role): array => $this->mapRoleToCard($role))
            ->values();
    }

    public function canCreateRoles(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->hasPermissionLike('criar niveis de acesso')
            || $user->hasPermissionLike('aplicar permissoes');
    }

    public function canManageRole(Role $role): array
    {
        $user = Auth::user();

        $canEdit = $user
            && $user->hasPermissionLike('aplicar permissoes')
            && $user->hasPermissionLike('editar niveis de acesso')
            && ! app(\App\Services\RoleService::class)->bloquearCampoEdit($role, 'edit');

        $canDelete = $user
            && $user->hasPermissionLike('excluir niveis de acesso')
            && ! app(\App\Services\RoleService::class)->bloquearExclusao($role);

        return [
            'edit' => (bool) $canEdit,
            'delete' => (bool) $canDelete,
        ];
    }

    protected function mapRoleToCard(Role $role): array
    {
        $permissions = $role->permissions
            ->pluck('name')
            ->values();

        $groupedPermissions = $permissions
            ->groupBy(function (string $permission): string {
                return Str::of($permission)->before(' ')->headline()->toString();
            })
            ->sortKeys()
            ->map(function (Collection $group, string $groupName): array {
                return [
                    'name' => $groupName,
                    'count' => $group->count(),
                    'items' => $group
                        ->sort()
                        ->values()
                        ->map(fn(string $permission): array => [
                            'label' => $permission,
                            'icon' => $this->resolvePermissionIcon($permission),
                        ]),
                ];
            })
            ->values();

        $permissionsByPrefix = $role->permissions
            ->groupBy(fn($permission) => Str::of($permission->name)->before(' ')->headline())
            ->map(fn($group) => $group->count())
            ->sortDesc();

        return [
            'id' => (string) $role->getKey(),
            'name' => $role->name,
            'permissions' => $permissions,
            'grouped_permissions' => $groupedPermissions,
            'permission_count' => $permissions->count(),
            'setor' => $role->setor?->nome,
            'summary' => $permissionsByPrefix->take(3)->map(
                fn(int $total, string $group) => "{$group}: {$total}"
            )->values(),
            'palette' => $this->resolvePalette($role),
            'actions' => $this->canManageRole($role),
            'expanded' => $this->isRoleExpanded((string) $role->getKey()),
        ];
    }

    public function toggleRoleExpansion(string $roleId): void
    {
        if ($this->isRoleExpanded($roleId)) {
            unset($this->expandedRoles[$roleId]);

            return;
        }

        $this->expandedRoles[$roleId] = true;
    }

    protected function isRoleExpanded(string $roleId): bool
    {
        return (bool) ($this->expandedRoles[$roleId] ?? false);
    }

    protected function resolvePalette(Role $role): array
    {
        $palettes = [
            ['accent' => 'indigo', 'icon' => 'heroicon-o-user-group'],
            ['accent' => 'amber', 'icon' => 'heroicon-o-academic-cap'],
            ['accent' => 'rose', 'icon' => 'heroicon-o-building-office-2'],
            ['accent' => 'emerald', 'icon' => 'heroicon-o-rectangle-stack'],
            ['accent' => 'sky', 'icon' => 'heroicon-o-chart-bar-square'],
            ['accent' => 'violet', 'icon' => 'heroicon-o-cog-6-tooth'],
        ];

        $index = abs(crc32(Str::ascii($role->name))) % count($palettes);

        return $palettes[$index];
    }

    protected function resolvePermissionIcon(string $permission): string
    {
        $normalized = Str::lower(Str::ascii($permission));

        return match (true) {
            str_contains($normalized, 'listar'),
            str_contains($normalized, 'visualizar'),
            str_contains($normalized, 'ver') => 'heroicon-o-list-bullet',
            str_contains($normalized, 'criar'),
            str_contains($normalized, 'cadastrar') => 'heroicon-o-plus-circle',
            str_contains($normalized, 'editar'),
            str_contains($normalized, 'atualizar') => 'heroicon-o-pencil-square',
            str_contains($normalized, 'excluir'),
            str_contains($normalized, 'remover') => 'heroicon-o-trash',
            str_contains($normalized, 'exportar') => 'heroicon-o-document-arrow-down',
            str_contains($normalized, 'importar') => 'heroicon-o-document-arrow-up',
            str_contains($normalized, 'filtrar') => 'heroicon-o-funnel',
            str_contains($normalized, 'vincular'),
            str_contains($normalized, 'gerenciar') => 'heroicon-o-link',
            default => 'heroicon-o-check-badge',
        };
    }
}
