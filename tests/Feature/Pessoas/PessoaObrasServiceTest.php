<?php

namespace Tests\Feature\Pessoas;

use App\Filament\Admin\Resources\Servidores\Pages\ManageServidores;
use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Livewire\Pessoas\PessoaForm;
use App\Models\Enums\SetorAccessCapability;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use App\Models\SetorAcesso;
use App\Models\User;
use App\Services\PessoaObrasService;
use App\Services\PessoaProfessorService;
use App\Services\PessoaScopeService;
use App\Services\ProfessorMovimentacaoService;
use App\Services\ServidorService;
use App\Services\SetorPedidoAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PessoaObrasServiceTest extends TestCase
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

    public function test_cria_obras_sem_escola_com_setor_role_matriculas_e_escopo_restrito(): void
    {
        $setor = $this->criarSetorCentral('Obras Regional');
        $destino = $this->criarSetorCentral('Destino Autorizado');

        $pessoa = app(PessoaObrasService::class)->criarPessoaObras([
            'nome' => 'Pessoa de Obras',
            'email' => 'obras@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            'setor_id' => $setor->id,
            'matriculas' => [
                ['matricula' => 'OBR-001', 'turno' => 'manha'],
                ['matricula' => 'OBR-002', 'turno' => 'tarde'],
            ],
        ]);

        $this->assertSame(['OBR-001', 'OBR-002'], $pessoa->matriculas->pluck('matricula')->sort()->values()->all());
        $this->assertTrue($pessoa->user->hasRole('Obras'));
        $this->assertFalse($pessoa->user->hasRole('Manutenção'));
        $this->assertFalse($pessoa->user->hasPermissionTo('Listar Todos os Pedidos'));
        $this->assertFalse($pessoa->user->hasPermissionTo('Acessar Escopo Global de Setores'));
        $this->assertFalse(app(PessoaScopeService::class)->hasGlobalAccess($pessoa->user));
        $this->assertNull($pessoa->id_escola);
        $this->assertNull($pessoa->user->id_escola);
        $this->assertSame($setor->id, $pessoa->user->setor_id);
        $this->assertSame(0, $pessoa->user->escolas()->count());
        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'servidor_id' => $pessoa->id,
            'setor_id' => $setor->id,
            'id_escola' => null,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'obras',
            'portaria' => null,
        ]);

        $matriz = app(SetorPedidoAccessService::class);
        $this->assertFalse($matriz->can($pessoa->user, SetorAccessCapability::LISTAR, $destino->id));
        SetorAcesso::query()->create([
            'setor_origem_id' => $setor->id,
            'setor_alvo_id' => $destino->id,
            'pode_listar' => true,
        ]);
        $this->assertTrue($matriz->can($pessoa->user, SetorAccessCapability::LISTAR, $destino->id));
    }

    public function test_troca_setor_preserva_matricula_historico_e_role_independente(): void
    {
        $setorA = $this->criarSetorCentral('Obras A');
        $setorB = $this->criarSetorCentral('Obras B');
        $service = app(PessoaObrasService::class);
        $pessoa = $service->criarPessoaObras([
            'nome' => 'Pessoa Operacional',
            'email' => 'obras.operacional@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            'setor_id' => $setorA->id,
            'matriculas' => [['matricula' => 'OBR-100', 'turno' => 'integral']],
        ]);
        $matriculaId = $pessoa->matriculas->first()->id;
        $vinculoAnteriorId = $pessoa->vinculosAtivos->first()->id;
        $roleIndependente = Role::query()->create(['name' => 'Auditoria Independente Obras', 'guard_name' => 'web']);
        $pessoa->user->assignRole($roleIndependente);

        $atualizada = $service->atualizarPessoaObras($pessoa, [
            'nome' => $pessoa->nome,
            'email' => $pessoa->email,
            'status' => Servidor::STATUS_ATIVO,
        ], [
            'setor_id' => $setorB->id,
            'matriculas' => [[
                'id' => $matriculaId,
                'matricula' => 'OBR-100',
                'turno' => 'integral',
            ]],
        ]);

        $this->assertSame($matriculaId, $atualizada->matriculas->first()->id);
        $this->assertTrue($atualizada->user->hasRole('Obras'));
        $this->assertTrue($atualizada->user->hasRole($roleIndependente));
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

    public function test_mudanca_de_obras_para_transporte_remove_role_anterior_e_preserva_role_independente(): void
    {
        $setor = $this->criarSetorCentral('Obras para Transporte');
        $pessoa = app(PessoaObrasService::class)->criarPessoaObras([
            'nome' => 'Pessoa convertida para Transporte',
            'email' => 'obras.transporte@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            'setor_id' => $setor->id,
            'matriculas' => [['matricula' => 'OBR-TRANS-1', 'turno' => 'integral']],
        ]);
        $vinculoObrasId = $pessoa->vinculosAtivos->first()->id;
        $roleIndependente = Role::query()->create([
            'name' => 'Auditoria Independente Transporte',
            'guard_name' => 'web',
        ]);
        $pessoa->user->assignRole($roleIndependente);

        $atualizada = app(ServidorService::class)->atualizarServidorComFuncoes($pessoa, [
            'cargo' => 'transporte',
            'nome' => $pessoa->nome,
            'email' => $pessoa->email,
            'status' => Servidor::STATUS_ATIVO,
        ], [
            'transporte' => ['matricula' => 'TRANS-1'],
        ]);

        $this->assertTrue($atualizada->user->hasRole('Transporte'));
        $this->assertFalse($atualizada->user->hasRole('Obras'));
        $this->assertTrue($atualizada->user->hasRole($roleIndependente));
        $this->assertSame('Transporte', ServidorResource::cargoLabel($atualizada));
        $this->assertNull($atualizada->setor_id);
        $this->assertNull($atualizada->user->setor_id);
        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'id' => $vinculoObrasId,
            'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
        ]);
        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'servidor_id' => $pessoa->id,
            'funcao_administrativa_id' => FuncaoAdministrativa::transportePadrao()->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
        ]);
    }

    public function test_conversoes_entre_professor_obras_manutencao_e_equipe_gestora_preservam_matricula(): void
    {
        [$escola] = $this->criarEscola('Escola Conversões Obras');
        $setorObras = $this->criarSetorCentral('Obras Conversões');
        $setorManutencao = $this->criarSetorCentral('Manutenção Conversões');
        $pessoa = $this->criarProfessor($escola);
        $matriculaId = $pessoa->matriculas->first()->id;
        $service = app(ServidorService::class);

        $pessoa = $service->atualizarServidorComFuncoes($pessoa, [
            'cargo' => 'obras', 'nome' => $pessoa->nome, 'email' => $pessoa->email, 'status' => Servidor::STATUS_ATIVO,
        ], ['obras' => [
            'setor_id' => $setorObras->id,
            'matriculas' => [['id' => $matriculaId, 'matricula' => 'PROF-OBR-1', 'turno' => 'manha']],
        ]]);
        $this->assertTrue($pessoa->user->hasRole('Obras'));
        $this->assertFalse($pessoa->user->hasRole('Professor'));

        $pessoa = $service->atualizarServidorComFuncoes($pessoa, [
            'cargo' => 'manutencao', 'nome' => $pessoa->nome, 'email' => $pessoa->email, 'status' => Servidor::STATUS_ATIVO,
        ], ['manutencao' => [
            'setor_id' => $setorManutencao->id,
            'matriculas' => [['id' => $matriculaId, 'matricula' => 'PROF-OBR-1', 'turno' => 'manha']],
        ]]);
        $this->assertTrue($pessoa->user->hasRole('Manutenção'));
        $this->assertFalse($pessoa->user->hasRole('Obras'));

        $pessoa = $service->atualizarServidorComFuncoes($pessoa, [
            'cargo' => 'obras', 'nome' => $pessoa->nome, 'email' => $pessoa->email, 'status' => Servidor::STATUS_ATIVO,
        ], ['obras' => [
            'setor_id' => $setorObras->id,
            'matriculas' => [['id' => $matriculaId, 'matricula' => 'PROF-OBR-1', 'turno' => 'manha']],
        ]]);

        $pessoa = $service->atualizarServidorComFuncoes($pessoa, [
            'cargo' => 'equipe_gestora', 'nome' => $pessoa->nome, 'email' => $pessoa->email, 'status' => Servidor::STATUS_ATIVO,
        ], ['equipe_gestora' => [
            'id_escola' => $escola->id,
            'matriculas' => [['id' => $matriculaId, 'matricula' => 'PROF-OBR-1', 'turno' => 'manha']],
            'cargos' => ['diretor'],
            'diretor' => true,
            'coordenador' => false,
            'secretario' => false,
            'portaria' => 'PORT-OBR-1',
        ]]);
        $this->assertTrue($pessoa->user->hasRole('Equipe Gestora'));
        $this->assertFalse($pessoa->user->hasRole('Obras'));

        $pessoa = $service->atualizarServidorComFuncoes($pessoa, [
            'cargo' => 'obras', 'nome' => $pessoa->nome, 'email' => $pessoa->email, 'status' => Servidor::STATUS_ATIVO,
        ], ['obras' => [
            'setor_id' => $setorObras->id,
            'matriculas' => [['id' => $matriculaId, 'matricula' => 'PROF-OBR-1', 'turno' => 'manha']],
        ]]);

        $pessoa = $service->atualizarServidorComFuncoes($pessoa, [
            'cargo' => 'professor', 'nome' => $pessoa->nome, 'email' => $pessoa->email, 'status' => Servidor::STATUS_ATIVO,
        ], ['matriculas_professor' => [[
            'id' => $matriculaId,
            'matricula' => 'PROF-OBR-1',
            'turno' => 'manha',
            'escolas' => [['id_escola' => $escola->id, 'vinculos_turma_componente' => []]],
        ]]]);

        $this->assertSame($matriculaId, $pessoa->matriculas->first()->id);
        $this->assertTrue($pessoa->user->hasRole('Professor'));
        $this->assertFalse($pessoa->user->hasRole('Obras'));
    }

    public function test_conversao_de_professor_com_avaliacao_pendente_faz_rollback(): void
    {
        [$escola] = $this->criarEscola('Escola Pendência Obras');
        $setor = $this->criarSetorCentral('Obras Pendência');
        $pessoa = $this->criarProfessor($escola);
        $matriculaId = $pessoa->matriculas->first()->id;

        $this->mock(ProfessorMovimentacaoService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('pendenciasAvaliativas')->once()->andReturn(['preenchimentos_pendentes' => 1]);
        });

        try {
            app(ServidorService::class)->atualizarServidorComFuncoes($pessoa, [
                'cargo' => 'obras', 'nome' => 'Nome não persistido', 'email' => $pessoa->email, 'status' => Servidor::STATUS_ATIVO,
            ], ['obras' => [
                'setor_id' => $setor->id,
                'matriculas' => [['id' => $matriculaId, 'matricula' => 'PROF-OBR-1', 'turno' => 'manha']],
            ]]);
            $this->fail('A conversão deveria ter sido bloqueada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('obras', $exception->errors());
        }

        $pessoa->refresh();
        $this->assertNotSame('Nome não persistido', $pessoa->nome);
        $this->assertTrue($pessoa->professores()->where('ativo', true)->exists());
        $this->assertFalse($pessoa->vinculosAtivos()->whereHas('funcaoAdministrativa', fn ($query) => $query->obras())->exists());
    }

    public function test_rejeita_setor_inativo_ou_que_exija_escola(): void
    {
        $setorInativo = $this->criarSetorCentral('Obras Inativo');
        $setorInativo->update(['ativo' => false]);
        [, $setorEscolar] = $this->criarEscola('Escola Setor Obras Inválido');

        foreach ([$setorInativo, $setorEscolar] as $index => $setor) {
            try {
                app(PessoaObrasService::class)->criarPessoaObras([
                    'nome' => 'Obras Inválida '.$index,
                    'email' => "obras.invalida{$index}@edu.umuarama.pr.gov.br",
                    'status' => Servidor::STATUS_ATIVO,
                ], [
                    'setor_id' => $setor->id,
                    'matriculas' => [['matricula' => 'OBR-INV-'.$index, 'turno' => 'integral']],
                ]);
                $this->fail('O setor inválido deveria ter sido rejeitado.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('setor_id', $exception->errors());
            }
        }
    }

    public function test_formulario_filtro_identificacao_e_modal_reconhecem_cargo_obras(): void
    {
        $setor = $this->criarSetorCentral('Obras Formulário');

        Livewire::actingAs($this->admin)
            ->test(PessoaForm::class, ['pessoaId' => null])
            ->set('nome', 'Pessoa Obras Formulário')
            ->set('email', 'obras.formulario@edu.umuarama.pr.gov.br')
            ->set('cargo', ServidorResource::CARGO_OBRAS)
            ->set('setorObrasId', $setor->id)
            ->set('matriculas', [
                'm-obras' => [
                    'id' => null,
                    'matricula' => 'OBR-FORM-1',
                    'turno' => 'integral',
                    'escolas' => [],
                ],
            ])
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertDispatched('pessoa-form-salvo');

        $pessoa = Servidor::query()->where('email', 'obras.formulario@edu.umuarama.pr.gov.br')->firstOrFail();
        $this->assertSame('Obras', ServidorResource::cargoLabel($pessoa));

        Livewire::actingAs($this->admin)
            ->test(ManageServidores::class)
            ->filterTable('cargo', [ServidorResource::CARGO_OBRAS])
            ->assertCanSeeTableRecords([$pessoa])
            ->mountTableAction('view', $pessoa)
            ->assertSee('Obras')
            ->assertSee('Obras Formulário')
            ->assertSee('Perfis');
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
            'nome' => 'Professor para Obras',
            'email' => 'professor.obras@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [[
            'matricula' => 'PROF-OBR-1',
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
