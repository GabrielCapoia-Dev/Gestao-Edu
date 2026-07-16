<?php

namespace Tests\Feature\Pessoas;

use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Enums\SetorAccessCapability;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use App\Models\SetorAcesso;
use App\Models\User;
use App\Services\PessoaEquipeGestoraService;
use App\Services\PessoaManutencaoService;
use App\Services\PessoaProfessorService;
use App\Services\PessoaScopeService;
use App\Services\PedidoService;
use App\Services\ProfessorMovimentacaoService;
use App\Services\ServidorService;
use App\Services\SetorPedidoAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PessoaManutencaoServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('permissoes:criar');
        $this->admin = User::factory()->create(['email_approved' => true]);
        $this->admin->assignRole(Role::findByName('Admin', 'web'));
        $this->actingAs($this->admin);
    }

    public function test_cria_manutencao_sem_escola_com_setor_role_e_matriculas(): void
    {
        $setor = $this->criarSetorCentral('Manutenção Central');

        $pessoa = app(PessoaManutencaoService::class)->criarPessoaManutencao([
            'nome' => 'Pessoa da Manutenção',
            'email' => 'manutencao@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            'setor_id' => $setor->id,
            'matriculas' => [
                ['matricula' => 'MAN-001', 'turno' => 'manha'],
                ['matricula' => 'MAN-002', 'turno' => 'tarde'],
            ],
        ]);

        $this->assertNull($pessoa->id_escola);
        $this->assertSame($setor->id, $pessoa->setor_id);
        $this->assertSame(['MAN-001', 'MAN-002'], $pessoa->matriculas->pluck('matricula')->sort()->values()->all());
        $this->assertTrue($pessoa->user->hasRole('Manutenção'));
        $this->assertTrue($pessoa->user->hasPermissionTo('Listar Todos os Pedidos'));
        $this->assertTrue($pessoa->user->hasPermissionTo('Acessar Escopo Global de Setores'));
        $this->assertTrue(app(PessoaScopeService::class)->hasGlobalAccess($pessoa->user));
        $this->assertTrue(app(PedidoService::class)->podeListarTodos($pessoa->user));
        $this->assertNull($pessoa->user->id_escola);
        $this->assertSame($setor->id, $pessoa->user->setor_id);
        $this->assertSame(0, $pessoa->user->escolas()->count());
        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'servidor_id' => $pessoa->id,
            'setor_id' => $setor->id,
            'id_escola' => null,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'manutencao',
        ]);

        $destino = $this->criarSetorCentral('Destino Operacional');
        $matriz = app(SetorPedidoAccessService::class);
        $this->assertFalse($matriz->can($pessoa->user, SetorAccessCapability::EDITAR, $destino->id));
        $this->assertFalse($matriz->can($pessoa->user, SetorAccessCapability::ENCAMINHAR, $destino->id));

        SetorAcesso::query()->create([
            'setor_origem_id' => $setor->id,
            'setor_alvo_id' => $destino->id,
            'pode_editar' => true,
            'pode_encaminhar' => true,
        ]);

        $this->assertTrue($matriz->can($pessoa->user, SetorAccessCapability::EDITAR, $destino->id));
        $this->assertTrue($matriz->can($pessoa->user, SetorAccessCapability::ENCAMINHAR, $destino->id));
    }

    public function test_troca_setor_cria_nova_vigencia_preserva_matricula_e_role_independente(): void
    {
        $setorA = $this->criarSetorCentral('Manutenção A');
        $setorB = $this->criarSetorCentral('Manutenção B');
        $service = app(PessoaManutencaoService::class);
        $pessoa = $service->criarPessoaManutencao([
            'nome' => 'Pessoa Operacional',
            'email' => 'operacional@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            'setor_id' => $setorA->id,
            'matriculas' => [['matricula' => 'OP-001', 'turno' => 'integral']],
        ]);
        $matriculaId = $pessoa->matriculas->first()->id;
        $vinculoAnteriorId = $pessoa->vinculosAtivos->first()->id;
        $roleIndependente = Role::query()->create(['name' => 'Auditoria Independente', 'guard_name' => 'web']);
        $pessoa->user->assignRole($roleIndependente);

        $atualizada = $service->atualizarPessoaManutencao($pessoa, [
            'nome' => $pessoa->nome,
            'email' => $pessoa->email,
            'status' => Servidor::STATUS_ATIVO,
        ], [
            'setor_id' => $setorB->id,
            'matriculas' => [[
                'id' => $matriculaId,
                'matricula' => 'OP-001',
                'turno' => 'integral',
            ]],
        ]);

        $this->assertSame($matriculaId, $atualizada->matriculas->first()->id);
        $this->assertTrue($atualizada->user->hasRole('Manutenção'));
        $this->assertTrue($atualizada->user->hasRole('Auditoria Independente'));
        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'id' => $vinculoAnteriorId,
            'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
            'setor_id' => $setorA->id,
        ]);
        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'servidor_id' => $pessoa->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'setor_id' => $setorB->id,
        ]);
    }

    public function test_converte_professor_preservando_matricula_e_historico(): void
    {
        [$escola] = $this->criarEscola('Escola Professor');
        $setorManutencao = $this->criarSetorCentral('Triagem Central');
        $pessoa = $this->criarProfessor($escola);
        $matriculaId = $pessoa->matriculas->first()->id;
        $vinculoProfessorId = $pessoa->vinculosAtivos->first()->id;

        $convertida = app(PessoaManutencaoService::class)->converterProfessorParaManutencao(
            $pessoa,
            ['nome' => $pessoa->nome, 'email' => $pessoa->email, 'status' => Servidor::STATUS_ATIVO],
            [
                'setor_id' => $setorManutencao->id,
                'matriculas' => [[
                    'id' => $matriculaId,
                    'matricula' => 'PROF-001',
                    'turno' => 'manha',
                ]],
            ],
        );

        $this->assertSame($matriculaId, $convertida->matriculas->first()->id);
        $this->assertFalse($convertida->professores->first()->ativo);
        $this->assertTrue($convertida->user->hasRole('Manutenção'));
        $this->assertFalse($convertida->user->hasRole('Professor'));
        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'id' => $vinculoProfessorId,
            'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
        ]);
    }

    public function test_converte_manutencao_para_professor_preservando_matricula(): void
    {
        [$escola] = $this->criarEscola('Escola Retorno Professor');
        $setorManutencao = $this->criarSetorCentral('Manutenção Retorno Professor');
        $pessoa = app(PessoaManutencaoService::class)->criarPessoaManutencao([
            'nome' => 'Pessoa Retorno Professor',
            'email' => 'retorno.professor@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            'setor_id' => $setorManutencao->id,
            'matriculas' => [['matricula' => 'RET-PROF-001', 'turno' => 'manha']],
        ]);
        $matriculaId = $pessoa->matriculas->first()->id;
        $this->prepararCargoProfessor();

        $professor = app(ServidorService::class)->atualizarServidorComFuncoes(
            $pessoa,
            ['cargo' => 'professor', 'nome' => $pessoa->nome, 'email' => $pessoa->email, 'status' => Servidor::STATUS_ATIVO],
            ['matriculas_professor' => [[
                'id' => $matriculaId,
                'matricula' => 'RET-PROF-001',
                'turno' => 'manha',
                'escolas' => [[
                    'id_escola' => $escola->id,
                    'vinculos_turma_componente' => [],
                ]],
            ]]],
        );

        $this->assertSame($matriculaId, $professor->matriculas->first()->id);
        $this->assertTrue($professor->professores->first()->ativo);
        $this->assertTrue($professor->user->hasRole('Professor'));
        $this->assertFalse($professor->user->hasRole('Manutenção'));
    }

    public function test_rejeita_setor_inativo_ou_que_exija_escola(): void
    {
        $setorInativo = $this->criarSetorCentral('Setor Inativo');
        $setorInativo->update(['ativo' => false]);
        [, $setorEscolar] = $this->criarEscola('Escola Setor Inválido');
        $service = app(PessoaManutencaoService::class);

        foreach ([$setorInativo, $setorEscolar] as $index => $setor) {
            try {
                $service->criarPessoaManutencao([
                    'nome' => 'Pessoa Inválida '.$index,
                    'email' => "invalida{$index}@edu.umuarama.pr.gov.br",
                    'status' => Servidor::STATUS_ATIVO,
                ], [
                    'setor_id' => $setor->id,
                    'matriculas' => [['matricula' => 'INV-'.$index, 'turno' => 'integral']],
                ]);
                $this->fail('O setor inválido deveria ter sido rejeitado.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('setor_id', $exception->errors());
            }
        }

        $this->assertSame(0, Servidor::query()->where('nome', 'like', 'Pessoa Inválida%')->count());
    }

    public function test_conversao_professor_com_avaliacao_pendente_faz_rollback_completo(): void
    {
        [$escola] = $this->criarEscola('Escola Pendência');
        $setorManutencao = $this->criarSetorCentral('Manutenção Pendência');
        $pessoa = $this->criarProfessor($escola);
        $matriculaId = $pessoa->matriculas->first()->id;

        $this->mock(ProfessorMovimentacaoService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('pendenciasAvaliativas')->once()->andReturn([
                'preenchimentos_pendentes' => 1,
            ]);
        });

        try {
            app(PessoaManutencaoService::class)->converterProfessorParaManutencao(
                $pessoa,
                ['nome' => 'Nome não deve persistir', 'email' => $pessoa->email, 'status' => Servidor::STATUS_ATIVO],
                [
                    'setor_id' => $setorManutencao->id,
                    'matriculas' => [[
                        'id' => $matriculaId,
                        'matricula' => 'PROF-001',
                        'turno' => 'manha',
                    ]],
                ],
            );
            $this->fail('A conversão deveria ter sido bloqueada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('manutencao', $exception->errors());
        }

        $pessoa->refresh();
        $this->assertNotSame('Nome não deve persistir', $pessoa->nome);
        $this->assertTrue($pessoa->professores()->where('ativo', true)->exists());
        $this->assertSame($matriculaId, $pessoa->matriculas()->first()->id);
        $this->assertFalse($pessoa->vinculosAtivos()->whereHas('funcaoAdministrativa', fn ($query) => $query->manutencao())->exists());
    }

    public function test_converte_equipe_gestora_para_manutencao_e_retorna_preservando_matricula(): void
    {
        [$escola] = $this->criarEscola('Escola Gestora');
        $setorManutencao = $this->criarSetorCentral('Manutenção Gestora');
        $pessoa = app(PessoaEquipeGestoraService::class)->criarPessoaEquipeGestora([
            'nome' => 'Diretora Gestora',
            'email' => 'diretora@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            'id_escola' => $escola->id,
            'matriculas' => [['matricula' => 'GEST-001', 'turno' => 'integral']],
            'cargos' => ['diretor'],
            'diretor' => true,
            'coordenador' => false,
            'secretario' => false,
            'portaria' => 'PORT-001',
        ]);
        $matriculaId = $pessoa->matriculas->first()->id;

        $manutencao = app(PessoaManutencaoService::class)->converterEquipeGestoraParaManutencao(
            $pessoa,
            ['nome' => $pessoa->nome, 'email' => $pessoa->email, 'status' => Servidor::STATUS_ATIVO],
            [
                'setor_id' => $setorManutencao->id,
                'matriculas' => [[
                    'id' => $matriculaId,
                    'matricula' => 'GEST-001',
                    'turno' => 'integral',
                ]],
            ],
        );

        $retorno = app(ServidorService::class)->atualizarServidorComFuncoes(
            $manutencao,
            ['cargo' => 'equipe_gestora', 'nome' => $manutencao->nome, 'email' => $manutencao->email, 'status' => Servidor::STATUS_ATIVO],
            ['equipe_gestora' => [
                'id_escola' => $escola->id,
                'matriculas' => [[
                    'id' => $matriculaId,
                    'matricula' => 'GEST-001',
                    'turno' => 'integral',
                ]],
                'cargos' => ['diretor'],
                'diretor' => true,
                'coordenador' => false,
                'secretario' => false,
                'portaria' => 'PORT-002',
            ]],
        );

        $this->assertSame($matriculaId, $retorno->matriculas->first()->id);
        $this->assertTrue($retorno->user->hasRole('Equipe Gestora'));
        $this->assertFalse($retorno->user->hasRole('Manutenção'));
        $this->assertTrue($retorno->vinculosAtivos()->whereHas('funcaoAdministrativa', fn ($query) => $query->equipeGestora())->exists());
    }

    private function criarSetorCentral(string $nome): Setor
    {
        return Setor::query()->create([
            'nome' => $nome,
            'contexto' => 'central',
            'exige_vinculo_escola' => false,
            'status' => 'Ativo',
            'ativo' => true,
        ]);
    }

    /** @return array{0: Escola, 1: Setor} */
    private function criarEscola(string $nome): array
    {
        $setor = Setor::query()->create([
            'nome' => 'Setor '.$nome,
            'contexto' => 'escolar',
            'exige_vinculo_escola' => true,
            'status' => 'Ativo',
            'ativo' => true,
        ]);
        $escola = Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'setor_id' => $setor->id,
            'email' => 'escola@edu.umuarama.pr.gov.br',
            'telefone' => '(44) 99999-9999',
            'ativo' => true,
        ]);

        return [$escola, $setor];
    }

    private function criarProfessor(Escola $escola): Servidor
    {
        $this->prepararCargoProfessor();

        return app(PessoaProfessorService::class)->criarPessoaProfessor([
            'nome' => 'Professor de Conversão',
            'email' => 'professor.conversao@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [[
            'matricula' => 'PROF-001',
            'turno' => 'manha',
            'escolas' => [[
                'id_escola' => $escola->id,
                'vinculos_turma_componente' => [],
            ]],
        ]]);
    }

    private function prepararCargoProfessor(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'Professor', 'guard_name' => 'web']);
        $role->syncPermissions([
            Permission::findOrCreate('Acessar Painel', 'web'),
            Permission::findOrCreate('Visualizar Tela de Inicio', 'web'),
        ]);
        $funcao = FuncaoAdministrativa::professorPadrao();
        $funcao->forceFill(['concede_acesso_sistema' => true])->save();
        $funcao->rolesPadrao()->sync([$role->id]);
    }
}
