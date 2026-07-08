<?php

namespace Tests\Feature\Pessoas;

use App\Models\FuncaoAdministrativa;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use App\Models\User;
use App\Services\PessoaScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PessoaScopeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_escopo_agrega_setores_dos_vinculos_ativos(): void
    {
        $setorPai = Setor::query()->create([
            'nome' => 'Pedagógico',
            'status' => 'ativo',
            'ativo' => true,
            'contexto' => 'central',
        ]);

        $setorFilho = Setor::query()->create([
            'nome' => 'Escola A',
            'parent_id' => $setorPai->id,
            'status' => 'ativo',
            'ativo' => true,
            'contexto' => 'escolar',
            'exige_vinculo_escola' => true,
        ]);

        app(\App\Services\SetorHierarchyService::class)->refreshNode($setorPai->fresh());
        app(\App\Services\SetorHierarchyService::class)->refreshNode($setorFilho->fresh());

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $servidor = Servidor::query()->create([
            'user_id' => $user->id,
            'nome' => $user->name,
            'status' => Servidor::STATUS_ATIVO,
        ]);

        $funcao = FuncaoAdministrativa::query()->create([
            'codigo' => 'professor_teste',
            'nome' => 'Professor',
            'categoria' => 'pedagogico',
            'ativo' => true,
            'exige_professor' => true,
        ]);

        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $servidor->id,
            'funcao_administrativa_id' => $funcao->id,
            'matricula' => '12345',
            'setor_id' => $setorFilho->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
        ]);

        $scope = app(PessoaScopeService::class);

        $this->assertTrue($scope->usaEscopoPorVinculos($user));
        $this->assertContains($setorFilho->id, $scope->visibleSetorIds($user));
    }
}