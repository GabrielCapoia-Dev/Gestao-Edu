<?php

namespace App\Filament\Admin\Resources\Roles\Pages;

use App\Filament\Admin\Resources\Roles\RoleResource;
use App\Models\Role;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Spatie\Permission\PermissionRegistrar;

class ManageRoles extends ManageRecords
{
    protected static string $resource = RoleResource::class;

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
                }),
        ];
    }
}
