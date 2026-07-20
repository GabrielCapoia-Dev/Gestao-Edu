<?php

namespace App\Support\Dashboard;

final readonly class DashboardUserContext
{
    /**
     * @param list<int> $roleIds
     * @param list<int> $permissionIds
     * @param list<int> $funcaoAdministrativaIds
     * @param list<int> $escolaIds
     * @param list<int> $setorIds
     * @param list<int> $setorVisivelIds
     */
    public function __construct(
        public int $userId,
        public bool $escopoGlobal,
        public array $roleIds,
        public array $permissionIds,
        public array $funcaoAdministrativaIds,
        public array $escolaIds,
        public array $setorIds,
        public array $setorVisivelIds,
    ) {}
}
