<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Services\UserService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

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
        app(UserService::class)->sincronizarAcessosDoUsuario($this->record, $this->data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}
