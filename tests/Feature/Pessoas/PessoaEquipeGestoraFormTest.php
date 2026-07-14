<?php

namespace Tests\Feature\Pessoas;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PessoaEquipeGestoraFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_converte_estado_do_form_no_payload_do_servico_gestor(): void
    {
        $permissao = Permission::findOrCreate('Gerenciar Funções de Servidores', 'web');
        $usuario = User::factory()->create();
        $usuario->givePermissionTo($permissao);
        $this->actingAs($usuario);

        [$dados, $vinculos] = ServidorResource::prepararDadosPersistencia([
            'nome' => 'Gestora Teste',
            'email' => 'gestora.teste@edu.umuarama.pr.gov.br',
            'cargo' => ServidorResource::CARGO_EQUIPE_GESTORA,
            'id_escola' => 15,
            'cargos_gestores' => ['diretor', 'coordenador'],
            'portaria' => 'PORT-123/2026',
            'data_inicio' => '2026-07-13',
            'diretor_principal' => true,
            'turma_ids' => [8, 9],
            'turmas_principais_ids' => [9],
            'matriculas_professor' => [[
                'id' => 3,
                'matricula' => 'MAT-001',
                'turno' => 'manha',
                'escolas' => [['id_escola' => 15]],
            ]],
        ]);

        $this->assertSame(ServidorResource::CARGO_EQUIPE_GESTORA, $dados['cargo']);
        $this->assertArrayNotHasKey('matriculas_professor', $dados);
        $this->assertSame(15, $vinculos['equipe_gestora']['id_escola']);
        $this->assertSame([
            'id' => 3,
            'matricula' => 'MAT-001',
            'turno' => 'manha',
        ], $vinculos['equipe_gestora']['matriculas'][0]);
        $this->assertSame(['diretor', 'coordenador'], $vinculos['equipe_gestora']['cargos']);
        $this->assertTrue($vinculos['equipe_gestora']['diretor']['principal']);
        $this->assertSame([8, 9], $vinculos['equipe_gestora']['coordenador']['turma_ids']);
        $this->assertSame([9], $vinculos['equipe_gestora']['coordenador']['turmas_principais_ids']);
        $this->assertFalse($vinculos['equipe_gestora']['secretario']);
    }

    public function test_bloqueia_payload_gestor_sem_autorizacao_especifica(): void
    {
        $this->actingAs(User::factory()->create());

        $this->expectException(AuthorizationException::class);

        ServidorResource::prepararDadosPersistencia([
            'cargo' => ServidorResource::CARGO_EQUIPE_GESTORA,
            'matriculas_professor' => [[
                'matricula' => 'MAT-001',
                'turno' => 'integral',
            ]],
        ]);
    }
}
