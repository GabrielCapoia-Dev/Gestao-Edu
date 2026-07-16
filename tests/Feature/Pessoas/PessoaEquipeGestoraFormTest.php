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
            'turma_ids' => [8, 9],
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
        $this->assertSame([8, 9], $vinculos['equipe_gestora']['coordenador']['turma_ids']);
        $this->assertFalse($vinculos['equipe_gestora']['secretario']);
        $this->assertArrayNotHasKey('data_inicio', $vinculos['equipe_gestora']);
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

    public function test_converte_estado_do_form_no_payload_de_manutencao_sem_lotacao_escolar(): void
    {
        $permissao = Permission::findOrCreate('Gerenciar Vínculos Estruturais de Pessoas', 'web');
        $usuario = User::factory()->create();
        $usuario->givePermissionTo($permissao);
        $this->actingAs($usuario);

        [$dados, $vinculos] = ServidorResource::prepararDadosPersistencia([
            'nome' => 'Manutenção Teste',
            'email' => 'manutencao.teste@edu.umuarama.pr.gov.br',
            'cargo' => ServidorResource::CARGO_MANUTENCAO,
            'setor_manutencao_id' => 20,
            'matriculas_professor' => [[
                'id' => 7,
                'matricula' => 'MAN-007',
                'turno' => 'integral',
                'escolas' => [['id_escola' => 99]],
            ]],
        ]);

        $this->assertSame(ServidorResource::CARGO_MANUTENCAO, $dados['cargo']);
        $this->assertArrayNotHasKey('matriculas_professor', $dados);
        $this->assertSame(20, $vinculos['manutencao']['setor_id']);
        $this->assertSame([[
            'id' => 7,
            'matricula' => 'MAN-007',
            'turno' => 'integral',
        ]], $vinculos['manutencao']['matriculas']);
        $this->assertArrayNotHasKey('id_escola', $vinculos['manutencao']);
    }
}
