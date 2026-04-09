<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Services\UserService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected string $view = 'filament.admin.resources.users.pages.edit-user';

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

    public function getOverviewCards(): array
    {
        $record = $this->getRecord()->loadMissing(['roles', 'permissions', 'escola', 'setor']);

        return [
            [
                'label' => 'Niveis ativos',
                'value' => number_format($record->roles->count(), 0, ',', '.'),
                'description' => $record->roles->pluck('name')->join(', ') ?: 'Nenhum nivel vinculado.',
                'icon' => 'heroicon-o-shield-check',
                'tone' => 'amber',
            ],
            [
                'label' => 'Permissoes extras',
                'value' => number_format($record->getDirectPermissions()->count(), 0, ',', '.'),
                'description' => 'Permissoes aplicadas diretamente ao usuario.',
                'icon' => 'heroicon-o-key',
                'tone' => 'rose',
            ],
            [
                'label' => 'Escola',
                'value' => $record->escola ? Str::limit($record->escola->nome, 24) : 'Nao vinculada',
                'description' => 'Escopo operacional principal.',
                'icon' => 'heroicon-o-building-library',
                'tone' => 'sky',
            ],
            [
                'label' => 'Setor',
                'value' => $record->setor ? Str::limit($record->setor->nome, 22) : 'Nao vinculado',
                'description' => 'Organizacao interna do acesso.',
                'icon' => 'heroicon-o-building-office-2',
                'tone' => 'emerald',
            ],
        ];
    }

    public function getHighlights(): array
    {
        return [
            'Ajuste niveis sem perder o historico do usuario',
            'Mantenha permissoes diretas apenas para casos especiais',
            'Revise escola, setor e liberacao de acesso no mesmo fluxo',
        ];
    }

    public function getSupportItems(): array
    {
        $record = $this->getRecord()->loadMissing(['roles', 'permissions']);

        return [
            [
                'title' => 'Situacao atual do acesso',
                'description' => $record->email_approved
                    ? 'O usuario esta com acesso liberado ao sistema.'
                    : 'O usuario ainda nao esta liberado para acessar o sistema.',
            ],
            [
                'title' => 'Composicao recomendada',
                'description' => $record->roles->count() > 1
                    ? 'Este usuario ja combina mais de um nivel. Revise apenas se os temas ainda fazem sentido juntos.'
                    : 'Se o usuario acumular responsabilidades, voce pode adicionar mais niveis sem substituir o acesso atual.',
            ],
        ];
    }
}
