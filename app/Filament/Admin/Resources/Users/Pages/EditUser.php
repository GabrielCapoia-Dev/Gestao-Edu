<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['email_approved']) && empty($data['email_verified_at'])) {
            $data['email_verified_at'] = now();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Sincronizar role
        if (! empty($this->data['role'])) {
            $roleId = is_array($this->data['role'])
                ? $this->data['role'][0]
                : $this->data['role'];

            $role = Role::find($roleId);

            if ($role) {
                $this->record->syncRoles([$role]);
            }
        }

        // Permissões diretas
        $permissoesSelecionadas = collect($this->data)
            ->filter(fn($_, $key) => str_starts_with($key, 'permissions_'))
            ->flatten()
            ->unique()
            ->values();

        if (empty($this->data['usar_permissoes_extras'])) {
            $this->record->syncPermissions([]);
        } else {
            $permissoesDaRole = $this->record->roles
                ->flatMap(fn($role) => $role->permissions)
                ->pluck('name')
                ->toArray();

            $permissoesAtuais = $this->record->getDirectPermissions()->pluck('name');

            $paraRemover = $permissoesAtuais->diff($permissoesSelecionadas);
            $paraAdicionar = $permissoesSelecionadas->diff($permissoesAtuais)->diff($permissoesDaRole);

            if ($paraRemover->isNotEmpty()) {
                $this->record->revokePermissionTo($paraRemover->toArray());
            }

            if ($paraAdicionar->isNotEmpty()) {
                $this->record->givePermissionTo($paraAdicionar->toArray());
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}