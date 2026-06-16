<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use App\Services\UserService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class ListUsers extends ListRecords
{
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
        parent::mount();

        /** @var \App\Models\User $admin */
        $admin = Auth::user();

        app(UserService::class)->sincronizarIgnoradosParaAdmin($admin);

        $this->dispatch('refresh-navigation');
    }

    public function getOverviewCards(): array
    {
        $stats = $this->getOverviewStats();

        return [
            [
                'label' => 'Usuários visíveis',
                'value' => number_format($stats['total'], 0, ',', '.'),
                'description' => 'Dentro do seu escopo atual de gestao.',
                'icon' => 'heroicon-o-users',
                'tone' => 'sky',
            ],
            [
                'label' => 'Acessos liberados',
                'value' => number_format($stats['approved'], 0, ',', '.'),
                'description' => 'Com verificacao de acesso ativa.',
                'icon' => 'heroicon-o-check-badge',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Múltiplos níveis',
                'value' => number_format($stats['multi_role'], 0, ',', '.'),
                'description' => 'Usuários com dois ou mais níveis.',
                'icon' => 'heroicon-o-shield-check',
                'tone' => 'amber',
            ],
            [
                'label' => 'Permissões extras',
                'value' => number_format($stats['direct_permissions'], 0, ',', '.'),
                'description' => 'Com excecoes alem dos níveis de acesso.',
                'icon' => 'heroicon-o-key',
                'tone' => 'rose',
            ],
        ];
    }

    public function getHighlights(): array
    {
        return [
            'Múltiplos níveis por usuário',
            'Permissões diretas para excecoes controladas',
            'Edição em massa para grupos de usuários',
        ];
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

    protected function getOverviewStats(): array
    {
        $query = $this->getScopedUsersQuery();

        return [
            'total' => (clone $query)->count(),
            'approved' => (clone $query)->where('email_approved', true)->count(),
            'multi_role' => (clone $query)->has('roles', '>', 1)->count(),
            'direct_permissions' => (clone $query)->has('permissions')->count(),
        ];
    }

    protected function getScopedUsersQuery(): Builder
    {
        return app(UserService::class)->listarUsuariosQuery(
            User::query(),
            Auth::user(),
        );
    }
}
