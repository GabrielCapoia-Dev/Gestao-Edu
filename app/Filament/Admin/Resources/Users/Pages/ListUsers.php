<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\Concerns\HasUsersOverview;
use App\Filament\Admin\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListUsers extends ListRecords
{
    use HasUsersOverview;

    protected static string $resource = UserResource::class;

    protected string $view = 'filament.admin.resources.users.pages.list-users';

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Acesso',
            'title' => 'Usuários',
            'description' => 'Gerencie login, níveis de acesso e permissões. Cargos pedagógicos vêm da ficha em Pessoas.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Novo usuário'),
        ];
    }

    public function getSupportItems(): array
    {
        return [
            [
                'title' => 'Pessoas × Usuários',
                'description' => 'Identidade e cargo (ex.: Professor) ficam em Pessoas. Aqui você controla a conta de acesso e os níveis Spatie.',
            ],
            [
                'title' => 'Roles de professor são imutáveis',
                'description' => 'Se o usuário estiver vinculado a um professor, os níveis do cargo Professor não podem ser removidos — só níveis extras.',
            ],
        ];
    }
}
