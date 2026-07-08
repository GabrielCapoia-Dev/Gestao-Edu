<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
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
            'description' => 'Gerencie os usuários e permissões de acesso.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function mount(): void
    {
        $this->redirect(
            ServidorResource::getUrl('index', ['activeTab' => 'usuarios']),
            navigate: false,
        );
    }

    public function getSupportItems(): array
    {
        return [
            [
                'title' => 'Fluxo mais claro para a equipe',
                'description' => 'A tabela continua com as mesmas ações, filtros e bulk actions, agora dentro de um contexto visual mais facil de ler.',
            ],
            [
                'title' => 'Combinacao de acesso sem retrabalho',
                'description' => 'Use níveis tematicos para montar o pacote de acesso ideal e recorra a permissões extras apenas quando houver uma excecao real.',
            ],
        ];
    }
}
