<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
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
        return $this->previousUrl
            ?? ServidorResource::getUrl('index', ['tab' => 'usuarios']);
    }

    public function getOverviewCards(): array
    {
        $record = $this->getRecord()->loadMissing(['roles', 'permissions', 'escola', 'setor']);

        return [
            [
                'label' => 'Níveis ativos',
                'value' => number_format($record->roles->count(), 0, ',', '.'),
                'description' => $record->roles->pluck('name')->join(', ') ?: 'Nenhum nivel vinculado.',
                'icon' => 'heroicon-o-shield-check',
                'tone' => 'amber',
            ],
            [
                'label' => 'Permissões extras',
                'value' => number_format($record->getDirectPermissions()->count(), 0, ',', '.'),
                'description' => 'Permissões aplicadas diretamente ao usuário.',
                'icon' => 'heroicon-o-key',
                'tone' => 'rose',
            ],
            [
                'label' => 'Escola',
                'value' => $record->escola ? Str::limit($record->escola->nome, 24) : 'Não vinculada',
                'description' => 'Escopo operacional principal.',
                'icon' => 'heroicon-o-building-library',
                'tone' => 'sky',
            ],
            [
                'label' => 'Setor',
                'value' => $record->setor ? Str::limit($record->setor->nome, 22) : 'Não vinculado',
                'description' => 'Organizacao interna do acesso.',
                'icon' => 'heroicon-o-building-office-2',
                'tone' => 'emerald',
            ],
        ];
    }

    public function getHighlights(): array
    {
        return [
            'Ajuste níveis sem perder o histórico do usuário',
            'Mantenha permissões diretas apenas para casos especiais',
            'Revise escola, setor e liberacao de acesso no mesmo fluxo',
        ];
    }

    public function getSupportItems(): array
    {
        $record = $this->getRecord()->loadMissing(['roles', 'permissions']);

        return [
            [
                'title' => 'Situação atual do acesso',
                'description' => $record->email_approved
                    ? 'O usuário está com acesso liberado ao sistema.'
                    : 'O usuário ainda não está liberado para acessar o sistema.',
            ],
            [
                'title' => 'Composicao recomendada',
                'description' => $record->roles->count() > 1
                    ? 'Este usuário já combina mais de um nível. Revise apenas se os temas ainda fazem sentido juntos.'
                    : 'Se o usuário acumular responsabilidades, você pode adicionar mais níveis sem substituir o acesso atual.',
            ],
        ];
    }
}
