<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['email_approved']) && $data['email_approved'] && empty($data['email_verified_at'])) {
            $data['email_verified_at'] = now();
        }
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(function ($record) {
                    if ($record->hasRole('Admin')) {
                        return false;
                    }
                })
                ->before(function ($record, $action) {
                    if ($record->hasRole('Admin')) {
                        $action->halt();
                        $this->notify('danger', '❌ Não é permitido excluir o usuário Admin.');
                    }
                }),
        ];
    }

    protected function afterSave(): void
    {
        // ✅ Sincronizar apenas a role
        if (!empty($this->data['role'])) {
            $roleId = is_array($this->data['role'])
                ? $this->data['role'][0]
                : $this->data['role'];

            $role = Role::find($roleId);

            if ($role) {
                $this->record->syncRoles([$role]);
            }
        }

        // ✅ Processar permissões diretas (mesma lógica do table)
        $permissoesSelecionadas = collect($this->data)
            ->filter(fn($_, $key) => str_starts_with($key, 'permissions_'))
            ->flatten()
            ->unique()
            ->values();

        // Se toggle "usar_permissoes_extras" está desativado, remove todas as diretas
        if (empty($this->data['usar_permissoes_extras'])) {
            $this->record->syncPermissions([]);
        } else {
            // Permissões da role (nunca remove)
            $permissoesDaRole = $this->record->roles
                ->flatMap(fn($role) => $role->permissions)
                ->pluck('name')
                ->toArray();

            // Permissões diretas atuais
            $permissoesAtuais = $this->record->getDirectPermissions()->pluck('name');

            // ✅ REMOVER: apenas diretas que foram desmarcadas
            $paraRemover = $permissoesAtuais->diff($permissoesSelecionadas);

            // ✅ ADICIONAR: permissões que não estão como diretas e não vêm da role
            $paraAdicionar = $permissoesSelecionadas
                ->diff($permissoesAtuais)
                ->diff($permissoesDaRole);

            if ($paraRemover->isNotEmpty()) {
                $this->record->revokePermissionTo($paraRemover->toArray());
            }

            if ($paraAdicionar->isNotEmpty()) {
                $this->record->givePermissionTo($paraAdicionar->toArray());
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}