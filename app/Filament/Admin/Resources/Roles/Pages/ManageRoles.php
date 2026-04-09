<?php

namespace App\Filament\Admin\Resources\Roles\Pages;

use App\Filament\Admin\Resources\Roles\RoleResource;
use App\Models\Role;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class ManageRoles extends ManageRecords
{
    protected static string $resource = RoleResource::class;

    protected string $view = 'filament.admin.resources.roles.pages.manage-roles';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): Role {
                    $role = Role::create([
                        'name' => $data['name'],
                        'guard_name' => 'web',
                    ]);

                    $permissoesSelecionadas = collect($data)
                        ->filter(fn ($_ , $key) => str_starts_with($key, 'permissions_'))
                        ->flatMap(fn ($permissions) => is_array($permissions) ? $permissions : [$permissions])
                        ->filter(fn ($permission) => filled($permission))
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
                }),
        ];
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
}
