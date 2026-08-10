<?php

namespace App\Filament\Admin\Resources\Users\Concerns;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait HasUsersOverview
{
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

    protected function getOverviewStats(): array
    {
        $query = $this->getScopedUsersQuery();

        return [
            'total' => (clone $query)->count(),
            'approved' => (clone $query)->canAuthenticate()->count(),
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
