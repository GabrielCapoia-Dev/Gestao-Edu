<?php

namespace Tests\Feature\Avaliacoes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfessorBulkPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_professor_e_equipe_gestora_podem_preencher_avaliacoes_em_massa(): void
    {
        foreach (['Professor', 'Equipe Gestora'] as $roleName) {
            $this->assertTrue(
                Role::query()->where('name', $roleName)->firstOrFail()->hasPermissionTo('Preencher Avaliações em Massa'),
            );
        }
    }
}
