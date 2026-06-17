<?php

namespace Tests\Feature\Servidores;

use App\Filament\Admin\Resources\Servidores\Pages\ManageServidores;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use App\Models\Turma;
use App\Models\User;
use App\Services\ServidorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ServidorFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_backfill_cria_servidor_para_professor_existente_sem_duplicar(): void
    {
        $escola = $this->criarEscola('Escola Backfill');

        $professor = Professor::withoutEvents(fn (): Professor => Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROF-BACKFILL',
            'nome' => 'Professor Backfill',
            'email' => 'professor.backfill@edu.umuarama.pr.gov.br',
        ]));

        $this->assertNull($professor->servidor_id);

        app(ServidorService::class)->backfillProfessores();

        $professor->refresh();
        $funcaoProfessor = FuncaoAdministrativa::professorPadrao();

        $this->assertNotNull($professor->servidor_id);
        $this->assertDatabaseHas('servidores', [
            'id' => $professor->servidor_id,
            'nome' => 'Professor Backfill',
            'matricula' => 'PROF-BACKFILL',
        ]);
        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'servidor_id' => $professor->servidor_id,
            'funcao_administrativa_id' => $funcaoProfessor->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
        ]);

        $servidoresAntes = Servidor::query()->count();

        app(ServidorService::class)->backfillProfessores();

        $this->assertSame($servidoresAntes, Servidor::query()->count());
        $this->assertSame(1, ServidorFuncaoAdministrativa::query()
            ->where('servidor_id', $professor->servidor_id)
            ->where('funcao_administrativa_id', $funcaoProfessor->id)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->count());
    }

    public function test_cria_servidor_auxiliar_sem_usuario_nem_professor(): void
    {
        $funcaoAuxiliar = FuncaoAdministrativa::query()->create([
            'nome' => 'Auxiliar de Serviços Gerais',
            'categoria' => FuncaoAdministrativa::CATEGORIA_OPERACIONAL,
            'ativo' => true,
            'exige_professor' => false,
            'tem_relacao_turma' => false,
        ]);

        $servidor = app(ServidorService::class)->criarServidorComFuncoes([
            'nome' => 'Servidor Auxiliar',
            'matricula' => 'AUX-001',
            'status' => Servidor::STATUS_ATIVO,
        ], [$funcaoAuxiliar->id]);

        $this->assertNull($servidor->user_id);
        $this->assertFalse($servidor->professores()->exists());
        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'servidor_id' => $servidor->id,
            'funcao_administrativa_id' => $funcaoAuxiliar->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
        ]);
    }

    public function test_formulario_exige_matricula_do_servidor(): void
    {
        Permission::findOrCreate('Listar Servidores');
        Permission::findOrCreate('Criar Servidores');
        Permission::findOrCreate('Gerenciar Funções de Servidores');

        $funcaoAuxiliar = FuncaoAdministrativa::query()->create([
            'nome' => 'Auxiliar Administrativo',
            'categoria' => FuncaoAdministrativa::CATEGORIA_OPERACIONAL,
            'ativo' => true,
            'exige_professor' => false,
            'tem_relacao_turma' => false,
        ]);

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo(['Listar Servidores', 'Criar Servidores', 'Gerenciar Funções de Servidores']);

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->callAction('create', [
                'nome' => 'Servidor Sem Matricula',
                'status' => Servidor::STATUS_ATIVO,
                'vinculos_funcionais' => [
                    [
                        'funcao_administrativa_id' => $funcaoAuxiliar->id,
                    ],
                ],
            ])
            ->assertHasActionErrors(['matricula' => 'required']);

        $this->assertDatabaseMissing('servidores', [
            'nome' => 'Servidor Sem Matricula',
        ]);
    }

    public function test_formulario_permite_matricula_repetida_para_servidores(): void
    {
        $funcaoAuxiliar = FuncaoAdministrativa::query()->create([
            'nome' => 'Auxiliar Operacional',
            'categoria' => FuncaoAdministrativa::CATEGORIA_OPERACIONAL,
            'ativo' => true,
            'exige_professor' => false,
            'tem_relacao_turma' => false,
        ]);

        app(ServidorService::class)->criarServidorComFuncoes([
            'nome' => 'Servidor Existente',
            'matricula' => 'MAT-REPETIDA',
            'status' => Servidor::STATUS_ATIVO,
        ], [$funcaoAuxiliar->id]);

        app(ServidorService::class)->criarServidorComFuncoes([
            'nome' => 'Servidor Nova Matricula Repetida',
            'matricula' => 'MAT-REPETIDA',
            'status' => Servidor::STATUS_ATIVO,
        ], [$funcaoAuxiliar->id]);

        $this->assertSame(2, Servidor::query()->where('matricula', 'MAT-REPETIDA')->count());
    }

    public function test_vinculo_funcional_salva_portaria_e_turmas(): void
    {
        $escola = $this->criarEscola('Escola Vinculo Funcional');
        $serie = Serie::query()->create(['codigo' => 'SER-FUNC', 'nome' => 'Serie Funcional']);
        $turma = Turma::query()->create([
            'codigo' => 'TUR-FUNC',
            'nome' => 'A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
        $funcaoCoordenacao = FuncaoAdministrativa::query()->create([
            'nome' => 'Coordenacao Pedagogica',
            'categoria' => FuncaoAdministrativa::CATEGORIA_PEDAGOGICO,
            'ativo' => true,
            'exige_professor' => false,
            'tem_relacao_turma' => true,
            'coordenacao_pedagogica' => true,
        ]);

        $servidor = app(ServidorService::class)->criarServidorComFuncoes([
            'id_escola' => $escola->id,
            'nome' => 'Servidor Coordenacao',
            'matricula' => 'COORD-001',
            'status' => Servidor::STATUS_ATIVO,
        ], [[
            'funcao_administrativa_id' => $funcaoCoordenacao->id,
            'portaria' => '123/2026',
            'turma_ids' => [$turma->id],
        ]]);

        $vinculo = ServidorFuncaoAdministrativa::query()
            ->where('servidor_id', $servidor->id)
            ->where('funcao_administrativa_id', $funcaoCoordenacao->id)
            ->firstOrFail();

        $this->assertSame('123/2026', $vinculo->portaria);
        $this->assertDatabaseHas('servidor_funcao_turma', [
            'servidor_funcao_administrativa_id' => $vinculo->id,
            'turma_id' => $turma->id,
        ]);
    }

    public function test_funcao_professor_cria_ou_vincula_cadastro_pedagogico(): void
    {
        $escola = $this->criarEscola('Escola Professor Servidor');

        $servidor = app(ServidorService::class)->criarServidorComFuncoes([
            'id_escola' => $escola->id,
            'nome' => 'Servidor Professor',
            'matricula' => 'PROF-SERV-001',
            'email' => 'servidor.professor@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [FuncaoAdministrativa::professorPadrao()->id]);

        $this->assertDatabaseHas('professores', [
            'servidor_id' => $servidor->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF-SERV-001',
            'nome' => 'Servidor Professor',
        ]);
    }

    public function test_nao_remove_funcao_professor_com_vinculo_pedagogico_ativo(): void
    {
        $escola = $this->criarEscola('Escola Vinculo Pedagogico');
        $professor = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROF-VINCULO',
            'nome' => 'Professor com Vinculo',
            'email' => 'professor.vinculo@edu.umuarama.pr.gov.br',
        ]);

        $serie = Serie::query()->create(['codigo' => 'SER-VINC', 'nome' => 'Serie Vinculo']);
        $componente = ComponenteCurricular::query()->create(['codigo' => 'COMP-VINC', 'nome' => 'Arte']);
        $turma = Turma::query()->create([
            'codigo' => 'TUR-VINC',
            'nome' => 'A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);

        $turma->componentes()->attach($componente->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(ServidorService::class)->removerFuncao(
            $professor->fresh()->servidor,
            FuncaoAdministrativa::professorPadrao(),
        );
    }

    public function test_listagem_de_servidores_respeita_escopo_por_setor_da_escola(): void
    {
        Permission::findOrCreate('Listar Servidores');

        $root = $this->criarSetor('Secretaria');
        $pedagogico = $this->criarSetor('Pedagogico', $root);
        $administrativo = $this->criarSetor('Administrativo', $root);

        $escolaA = $this->criarEscola('Escola A', $pedagogico);
        $escolaB = $this->criarEscola('Escola B', $administrativo);

        $servidorVisivel = Servidor::query()->create([
            'id_escola' => $escolaA->id,
            'nome' => 'Servidor Visivel',
            'matricula' => 'SERV-A',
            'status' => Servidor::STATUS_ATIVO,
        ]);
        $servidorOculto = Servidor::query()->create([
            'id_escola' => $escolaB->id,
            'nome' => 'Servidor Oculto',
            'matricula' => 'SERV-B',
            'status' => Servidor::STATUS_ATIVO,
        ]);

        $usuario = User::factory()->create([
            'id_escola' => $escolaA->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Listar Servidores');

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->assertCanSeeTableRecords([$servidorVisivel])
            ->assertCanNotSeeTableRecords([$servidorOculto]);
    }

    public function test_policy_de_servidores_exige_permissoes_especificas(): void
    {
        $escola = $this->criarEscola('Escola Policy');
        $servidor = Servidor::query()->create([
            'id_escola' => $escola->id,
            'nome' => 'Servidor Policy',
            'status' => Servidor::STATUS_ATIVO,
        ]);

        $usuario = User::factory()->create([
            'id_escola' => $escola->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $this->assertFalse(Gate::forUser($usuario)->allows('viewAny', Servidor::class));

        Permission::findOrCreate('Listar Servidores');
        Permission::findOrCreate('Editar Servidores');

        $usuario->givePermissionTo(['Listar Servidores', 'Editar Servidores']);

        $this->assertTrue(Gate::forUser($usuario)->allows('viewAny', Servidor::class));
        $this->assertTrue(Gate::forUser($usuario)->allows('update', $servidor));
    }

    private function criarSetor(string $nome, ?Setor $parent = null): Setor
    {
        return Setor::query()->create([
            'nome' => $nome,
            'parent_id' => $parent?->id,
            'ativo' => true,
            'status' => 'Ativo',
            'is_default_root' => $parent === null,
        ]);
    }

    private function criarEscola(string $nome, ?Setor $setor = null): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome.microtime()), 0, 8)),
            'nome' => $nome,
            'setor_id' => $setor?->id,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
            'ativo' => true,
        ]);
    }
}
