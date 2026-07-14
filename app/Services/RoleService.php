<?php

namespace App\Services;

class RoleService
{
    public function adminRole($record): bool
    {
        return $record->name === 'Admin';
    }

    public function bloquearCampo($record, $context): bool
    {
        if ($context === 'create') {
            return false;
        }

        return $this->roleProtegida($record, ['Admin', 'SecretÃ¡rio', 'Administrativo']);
    }

    public function bloquearCampoEdit($record, $context): bool
    {
        if ($context === 'create') {
            return false;
        }

        return $this->roleProtegida($record, ['Admin']);
    }

    public function bloquearExclusao($record): bool
    {
        return $this->roleProtegida($record, ['Admin', 'SecretÃ¡rio', 'Administrativo']);
    }

    public function bloquearSelecaoBulkActions($record): bool
    {
        return ! $this->roleProtegida($record, ['Admin', 'SecretÃ¡rio', 'Administrativo']);
    }

    /** @param list<string> $nomes */
    private function roleProtegida($record, array $nomes): bool
    {
        if (app(PessoaAcessoService::class)->roleFuncionalGerenciada($record)) {
            return true;
        }

        return in_array($record->name, $nomes, true);
    }
}
